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

trait ReportLedgerActions
{
    public function deductionLedger(Request $request)
    {
        try {
            $fromPeriodId = $request->input('from_period_id') ?: $request->input('payroll_period_id');
            $toPeriodId   = $request->input('to_period_id') ?: $fromPeriodId;

            if (!$fromPeriodId) {
                $latest = \App\Models\PayrollPeriod::orderBy('period_end', 'desc')->first();
                $fromPeriodId = $latest?->id;
                $toPeriodId = $latest?->id;
            }

            if (!$fromPeriodId) {
                return back()->with('error', 'No payroll period available.');
            }

            $fromPeriod = \App\Models\PayrollPeriod::findOrFail($fromPeriodId);
            $toPeriod   = \App\Models\PayrollPeriod::findOrFail($toPeriodId ?? $fromPeriodId);

            // Ensure chronological ordering
            if ($fromPeriod->period_start > $toPeriod->period_start) {
                $temp = $fromPeriod;
                $fromPeriod = $toPeriod;
                $toPeriod = $temp;
            }

            $companyId = $this->resolveCompanyId($request) ?? $fromPeriod->company_id;
            $company   = \App\Models\Company::find($companyId) ?? $fromPeriod->company ?? \App\Models\Company::first();

            // Periods in date range chronologically
            $periods = \App\Models\PayrollPeriod::where('period_start', '>=', $fromPeriod->period_start)
                ->where('period_start', '<=', $toPeriod->period_start)
                ->where('company_id', $companyId)
                ->orderBy('period_start', 'asc')
                ->get();

            if ($periods->isEmpty()) {
                return back()->with('error', 'No payroll periods found in the selected range.');
            }

            $periodIds = $periods->pluck('id')->toArray();

            // Department filter
            $departmentId = $request->input('department_id');
            if ($departmentId === 'all') {
                $departmentId = null;
            }

            $exportFormat = strtolower($request->input('export_format', 'pdf'));

            // Single selected deduction type
            $deductionTypeId = $request->input('deduction_type_id', 'sss_contribution');

            $standardLabels = \App\Support\Payroll\DeductionReportResolver::STANDARD_LABELS;

            $deductionLabel = $standardLabels[$deductionTypeId] ?? null;
            if (!$deductionLabel && is_numeric($deductionTypeId)) {
                $dt = \App\Models\DeductionType::find($deductionTypeId);
                $deductionLabel = $dt?->name ?? ('Deduction Item #' . $deductionTypeId);
            }
            if (!$deductionLabel) {
                $deductionLabel = ucwords(str_replace('_', ' ', (string) $deductionTypeId));
            }

            // Query approved/posted/paid pay headers for target period range
            $query = \App\Models\PayHeader::with(['employee.dept', 'payrollPeriod', 'payOuts.deductionType'])
                ->whereIn('payroll_period_id', $periodIds)
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

            // Employee filter: normalize 'all'/empty to no filter; verify company boundary for numeric ID
            $employeeId = $request->input('employee_id');
            if ($employeeId && $employeeId !== 'all') {
                if (is_numeric($employeeId)) {
                    $empId = (int) $employeeId;
                    $empBelongsToCompany = \App\Models\EmployeeMaster::where('id', $empId)
                        ->when($companyId !== null, fn($q) => $q->where('company_id', $companyId))
                        ->exists();

                    if ($empBelongsToCompany) {
                        $query->where('employee_id', $empId);
                    } else {
                        // Strict tenant boundary: cross-company employee ID returns no records
                        $query->where('employee_id', -1);
                    }
                } else {
                    $query->where('employee_id', -1);
                }
            }

            $payHeaders = $query->get();

            if ($payHeaders->isEmpty()) {
                return back()->with('error', 'No payroll transactions found for the selected criteria.');
            }

            $resolveAmount = function($ph) use ($deductionTypeId) {
                return \App\Support\Payroll\DeductionReportResolver::resolveAmountForHeader($ph, $deductionTypeId);
            };

            // Group by employee and build vertical ledger rows chronologically
            $empGroups = $payHeaders->groupBy('employee_id');
            $reportData = [];
            $grandTotalLedger = 0.0;

            foreach ($empGroups as $empId => $phList) {
                $firstPh = $phList->first();
                $emp = $firstPh->employee;
                if (!$emp) continue;

                $empRows = [];
                $empTotal = 0.0;

                // Sort transactions chronologically by period_start
                $sortedPhs = $phList->sortBy(fn($p) => $p->payrollPeriod?->period_start ?? '');

                foreach ($sortedPhs as $ph) {
                    $p = $ph->payrollPeriod;
                    if (!$p) continue;

                    $val = $resolveAmount($ph);
                    $startFmt = \Carbon\Carbon::parse($p->period_start)->format('m/d/Y');
                    $endFmt   = \Carbon\Carbon::parse($p->period_end)->format('m/d/Y');
                    $rangeStr = "{$startFmt} to {$endFmt}";

                    $empRows[] = [
                        'period'       => $p,
                        'period_name'  => $p->period_name,
                        'period_range' => $rangeStr,
                        'amount'       => $val,
                    ];

                    $empTotal += $val;
                }

                $grandTotalLedger += $empTotal;

                $reportData[] = [
                    'employee'       => $emp,
                    'employee_code'  => $emp->employee_code ?? 'EMP' . $emp->id,
                    'name'           => $emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . substr($emp->middle_name, 0, 1) . '.' : ''),
                    'department'     => $emp->dept->name ?? '—',
                    'rows'           => $empRows,
                    'employee_total' => $empTotal,
                ];
            }

            if (empty($reportData)) {
                return back()->with('error', 'No deduction ledger records found matching the selected criteria.');
            }

            $filenameBase = "Deduction_Ledger_{$deductionLabel}_{$fromPeriod->period_name}";
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\DeductionLedgerExport($reportData, $deductionLabel, $fromPeriod, $toPeriod, $company),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\DeductionLedgerExport($reportData, $deductionLabel, $fromPeriod, $toPeriod, $company),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.deduction-ledger', compact(
                    'company', 'fromPeriod', 'toPeriod', 'periods', 'deductionLabel', 'deductionTypeId', 'reportData', 'grandTotalLedger'
                ))
                ->setPaper('legal', 'landscape');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Deduction Ledger failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate deduction ledger: ' . $e->getMessage());
        }
    }

    /**
     * Report #11.8: Single-Deduction Multi-Period Matrix Report
     * Tracks a single selected deduction type over a range of payroll periods (employees in rows, period end-dates in horizontal columns).
     */
}
