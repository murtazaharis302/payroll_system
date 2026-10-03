<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PayrollPeriod;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\Company;
use App\Models\PayHeader;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SSSReportExport;
use App\Exports\PhilHealthReportExport;
use App\Exports\PagIBIGReportExport;
use App\Exports\WithholdingTaxReportExport;
use App\Exports\PayrollMasterSheetExport;
use ZipArchive;

trait ReportHistoryActions
{
    public function deductionHistory(Request $request)
    {
        try {
            $periodId = $request->input('payroll_period_id') ?: $request->input('from_period_id');

            if (!$periodId) {
                $latest = \App\Models\PayrollPeriod::orderBy('period_end', 'desc')->first();
                $periodId = $latest?->id;
            }

            if (!$periodId) {
                return back()->with('error', 'No payroll period available.');
            }

            $period    = \App\Models\PayrollPeriod::findOrFail($periodId);
            $companyId = $this->resolveCompanyId($request) ?? $period->company_id;
            $company   = \App\Models\Company::find($companyId) ?? $period->company ?? \App\Models\Company::first();

            $dontIncludeZero = $request->boolean('dont_include_zero') || $request->input('include_zero') == '0';
            $departmentId    = $request->input('department_id');
            if ($departmentId === 'all') {
                $departmentId = null;
            }

            $exportFormat = strtolower($request->input('export_format', 'pdf'));

            // Resolve deduction types selection
            $selectedDeductions = $request->input('selected_deduction_types') ?: $request->input('deduction_types') ?: [
                'sss_contribution', 'philhealth_contribution', 'pagibig_contribution',
                'withholding_tax', 'sss_loan', 'pagibig_loan', 'vale_deduction', 'goods_deduction'
            ];

            if (!is_array($selectedDeductions)) {
                $selectedDeductions = [$selectedDeductions];
            }

            // Labels map
            $deductionLabels = [
                'sss_contribution'        => 'SSS',
                'philhealth_contribution' => 'PhilHealth',
                'pagibig_contribution'    => 'Pag-IBIG',
                'withholding_tax'         => 'W/Tax',
                'sss_loan'                => 'SSS Loan',
                'pagibig_loan'            => 'Pag-IBIG Loan',
                'vale_deduction'          => 'Vale',
                'goods_deduction'         => 'Goods',
            ];

            // Resolve selected deduction types. If empty (unselected), default to all deduction types
            $rawSelectedDeductions = $request->input('selected_deduction_types') ?: $request->input('deduction_types');
            if (empty($rawSelectedDeductions)) {
                $selectedDeductions = [
                    'sss_contribution', 'philhealth_contribution', 'pagibig_contribution', 'withholding_tax',
                    'sss_loan', 'pagibig_loan', 'vale_deduction', 'goods_deduction', 'absences_deduction'
                ];
                $customTypeIds = \App\Models\DeductionType::forCompany($companyId)->where('is_active', true)->pluck('id')->toArray();
                foreach ($customTypeIds as $cid) {
                    $selectedDeductions[] = (string) $cid;
                }
            } else {
                $selectedDeductions = is_array($rawSelectedDeductions) ? $rawSelectedDeductions : [$rawSelectedDeductions];
            }

            // Labels map
            $deductionLabels = [
                'sss_contribution'        => 'SSS',
                'philhealth_contribution' => 'PhilHealth',
                'pagibig_contribution'    => 'Pag-IBIG',
                'withholding_tax'         => 'W/Tax',
                'sss_loan'                => 'SSS Loan',
                'pagibig_loan'            => 'Pag-IBIG Loan',
                'vale_deduction'          => 'Vale',
                'goods_deduction'         => 'Goods',
                'absences_deduction'      => 'Absences',
            ];

            // Add custom deduction type names if numeric IDs are passed
            $customTypes = \App\Models\DeductionType::whereIn('id', array_filter($selectedDeductions, 'is_numeric'))->get()->keyBy('id');
            foreach ($customTypes as $id => $dt) {
                $deductionLabels[$id] = $dt->name;
            }

            // Query approved/posted/paid pay headers for the single target period
            $query = \App\Models\PayHeader::with(['employee.dept', 'payOuts.deductionType'])
                ->where('payroll_period_id', $period->id)
                ->whereIn('status', \App\Models\PayHeader::FINAL_REPORT_STATUSES);

            if ($companyId !== null) {
                $query->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                });
            }

            if ($departmentId) {
                $query->whereHas('employee', function ($q) use ($departmentId) {
                    $q->where('dept_id', $departmentId);
                });
            }

            $payHeaders = $query->get();

            if ($payHeaders->isEmpty()) {
                return back()->with('error', 'No payroll transactions found for the selected period.');
            }

            $reportData = [];
            $columnTotals = array_fill_keys($selectedDeductions, 0.0);
            $grandTotalDeductions = 0.0;

            foreach ($payHeaders as $ph) {
                $emp = $ph->employee;
                if (!$emp) continue;

                $rowDeductions = \App\Support\Payroll\DeductionReportResolver::resolveRowDeductions($ph, $selectedDeductions);
                $rowSum = array_sum($rowDeductions);

                // Filter out zero total amount rows if requested
                if ($dontIncludeZero && $rowSum <= 0) {
                    continue;
                }

                foreach ($selectedDeductions as $key) {
                    $columnTotals[$key] += ($rowDeductions[$key] ?? 0.0);
                }
                $grandTotalDeductions += $rowSum;

                $reportData[] = [
                    'payroll'           => $ph,
                    'employee'          => $emp,
                    'employee_code'     => $emp->employee_code ?? 'EMP' . $emp->id,
                    'name'              => $emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . substr($emp->middle_name, 0, 1) . '.' : ''),
                    'department'        => $emp->dept->name ?? '—',
                    'deductions'        => $rowDeductions,
                    'total_deductions'  => $rowSum,
                ];
            }

            if (empty($reportData)) {
                return back()->with('error', 'No deduction records found matching the selected criteria.');
            }

            $filenameBase = "Deduction_History_{$period->period_name}";
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\SingleDeductionHistoryExport($reportData, $selectedDeductions, $deductionLabels, $period, $company),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\SingleDeductionHistoryExport($reportData, $selectedDeductions, $deductionLabels, $period, $company),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.deduction-history', compact(
                    'company', 'period', 'reportData', 'selectedDeductions', 'deductionLabels', 'columnTotals', 'grandTotalDeductions'
                ))
                ->setPaper('legal', 'landscape');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Single-Period Deduction History failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate single-period deduction history: ' . $e->getMessage());
        }
    }

    /**
     * Report #10: Single-Period Income History Report
     * Itemizes income categories for a single specific payroll period.
     */
}
