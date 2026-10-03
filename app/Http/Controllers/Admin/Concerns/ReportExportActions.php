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

trait ReportExportActions
{
    public function singleDeductionMatrix(Request $request)
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

            // Periods in range chronologically
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

            $payHeaders = $query->get();

            if ($payHeaders->isEmpty()) {
                return back()->with('error', 'No payroll transactions found for the selected criteria.');
            }

            $resolveAmount = function($ph) use ($deductionTypeId) {
                return \App\Support\Payroll\DeductionReportResolver::resolveAmountForHeader($ph, $deductionTypeId);
            };

            // Group by employee and pivot matrix: Y-axis = Employees, X-axis = Period End-Dates
            $empGroups = $payHeaders->groupBy('employee_id');
            $reportData = [];
            $columnTotals = array_fill_keys($periodIds, 0.0);
            $grandTotalMatrix = 0.0;

            foreach ($empGroups as $empId => $phList) {
                $firstPh = $phList->first();
                $emp = $firstPh->employee;
                if (!$emp) continue;

                $amountsByPeriod = array_fill_keys($periodIds, 0.0);
                $rowSum = 0.0;

                foreach ($phList as $ph) {
                    $pid = $ph->payroll_period_id;
                    $val = $resolveAmount($ph);
                    $amountsByPeriod[$pid] += $val;
                    $rowSum += $val;
                }

                // Omit / Prune employee rows if they have 0.00 total deductions across the entire date range
                if ($rowSum <= 0) {
                    continue;
                }

                foreach ($periodIds as $pid) {
                    $columnTotals[$pid] += $amountsByPeriod[$pid];
                }
                $grandTotalMatrix += $rowSum;

                $reportData[] = [
                    'employee'       => $emp,
                    'employee_code'  => $emp->employee_code ?? 'EMP' . $emp->id,
                    'name'           => $emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . substr($emp->middle_name, 0, 1) . '.' : ''),
                    'department'     => $emp->dept->name ?? '—',
                    'amounts'        => $amountsByPeriod,
                    'row_total'      => $rowSum,
                ];
            }

            if (empty($reportData)) {
                return back()->with('error', 'No deduction matrix records found matching the selected criteria.');
            }

            $filenameBase = "Single_Deduction_Matrix_{$deductionLabel}_{$fromPeriod->period_name}";
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\SingleDeductionMatrixExport($reportData, $periods, $deductionLabel, $fromPeriod, $toPeriod, $company),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\SingleDeductionMatrixExport($reportData, $periods, $deductionLabel, $fromPeriod, $toPeriod, $company),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.single-deduction-matrix', compact(
                    'company', 'fromPeriod', 'toPeriod', 'periods', 'deductionLabel', 'deductionTypeId', 'reportData', 'columnTotals', 'grandTotalMatrix'
                ))
                ->setPaper('legal', 'landscape');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Single Deduction Matrix failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate single-deduction matrix: ' . $e->getMessage());
        }
    }

    /**
     * Save dynamic bank settings via AJAX from inline modal.
     */
    public function saveBankSettingsAjax(Request $request)
    {
        try {
            $request->validate([
                'bank_name'      => 'required|string|max:150',
                'account_number' => 'required|string|max:50',
                'branch_name'    => 'nullable|string|max:150',
                'manager_name'   => 'nullable|string|max:150',
                'corporate_signee_1' => 'nullable|string|max:150',
                'corporate_signee_1_title' => 'nullable|string|max:150',
                'corporate_signee_2' => 'nullable|string|max:150',
                'corporate_signee_2_title' => 'nullable|string|max:150',
                'company_id'     => 'nullable|integer',
            ]);

            $resolvedCompanyId = $this->resolveCompanyId($request);
            $companyId = $resolvedCompanyId ?? $request->company_id ?? auth()->user()?->company_id;

            if (!$companyId) {
                $company = \App\Models\Company::first();
                $companyId = $company?->id;
            }

            $setting = \App\Models\CompanyBankSetting::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'bank_name'  => $request->bank_name,
                ],
                [
                    'branch_name'              => $request->branch_name ?? 'Main Branch',
                    'manager_name'             => $request->manager_name,
                    'account_number'           => $request->account_number,
                    'corporate_signee_1'       => $request->corporate_signee_1,
                    'corporate_signee_1_title' => $request->corporate_signee_1_title,
                    'corporate_signee_2'       => $request->corporate_signee_2,
                    'corporate_signee_2_title' => $request->corporate_signee_2_title,
                    'is_active'                => true,
                    'is_default'               => true,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Bank settings updated successfully.',
                'setting' => $setting,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save bank settings: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Report #12: Export Payroll to File for Bank Upload
     * Generates a 5-column upload file (Last Name, First Name, Middle Name, Account Number, Amount)
     * supporting multi-period net pay aggregation.
     */
    public function exportPayrollForBank(Request $request)
    {
        try {
            $fromPeriodId = $request->input('from_period_id', $request->input('payroll_period_id'));
            $toPeriodId   = $request->input('to_period_id', $fromPeriodId);

            if (!$fromPeriodId) {
                return back()->with('error', 'Please select both Start Period and End Period.');
            }

            $companyId = $this->resolveCompanyId($request);

            $fromPeriodQuery = \App\Models\PayrollPeriod::where('id', $fromPeriodId);
            $toPeriodQuery   = \App\Models\PayrollPeriod::where('id', $toPeriodId ?? $fromPeriodId);
            if ($companyId !== null) {
                $fromPeriodQuery->where('company_id', $companyId);
                $toPeriodQuery->where('company_id', $companyId);
            }
            $fromPeriod = $fromPeriodQuery->firstOrFail();
            $toPeriod   = $toPeriodQuery->firstOrFail();

            $departmentId = $request->input('department_id');

            $filenameBase = "Bank_Upload_Payroll_{$fromPeriod->period_name}_to_{$toPeriod->period_name}";
            $safeFilename = $this->sanitizeFilename($filenameBase);

            $format = strtolower($request->input('export_format', 'csv'));
            $extension = $format === 'xlsx' ? '.xlsx' : '.csv';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\BankUploadExport($fromPeriod->id, $toPeriod->id, $companyId, $departmentId),
                $safeFilename . $extension,
                $format === 'xlsx' ? \Maatwebsite\Excel\Excel::XLSX : \Maatwebsite\Excel\Excel::CSV
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Payroll period not found.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Bank File Export failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to export bank upload file: ' . $e->getMessage());
        }
    }

    /**
     * Export Bank Advice to Excel
     */
    public function exportBankAdvice(int $payrollPeriodId)
    {
        $companyId = $this->resolveCompanyId(request());
        $periodQuery = PayrollPeriod::where('id', $payrollPeriodId);
        if ($companyId !== null) {
            $periodQuery->where('company_id', $companyId);
        }
        $period = $periodQuery->firstOrFail();

        $company = Company::find($companyId) ?? $period->company ?? Company::first();
        $service = app(\App\Services\Reports\BankAdviceReportService::class);
        $adviceData = $service->build($period, $period, $company, $companyId);

        $fileName = $this->sanitizeFilename('bank_advice_' . $period->period_name . '.xlsx');
        return Excel::download(
            new \App\Exports\BankAdviceExport(
                $adviceData['reportData'],
                $period,
                $period,
                $adviceData['bankSetting'],
                $company
            ),
            $fileName
        );
    }

    /**
     * Export Payroll Master Sheet Matrix to Excel
     */
    public function exportMasterSheet(Request $request, int $payrollPeriodId)
    {
        $companyId = $this->resolveCompanyId($request);
        $periodQuery = PayrollPeriod::where('id', $payrollPeriodId);
        if ($companyId !== null) {
            $periodQuery->where('company_id', $companyId);
        }
        $period = $periodQuery->firstOrFail();

        $departmentId = $request->query('department_id') ?? $request->input('department_id');
        $fileName = $this->sanitizeFilename('payroll_master_sheet_' . $period->period_name . '.xlsx');
        return Excel::download(new \App\Exports\PayrollMasterSheetExport($payrollPeriodId, $companyId, $departmentId), $fileName);
    }


    /**
     * Export Statutory Report (SSS, PhilHealth, Pag-IBIG, or Tax)
     */
    public function exportStatutory(string $type, int $year, int $month)
    {
        $companyId = $this->resolveCompanyId(request());
        $monthString = sprintf('%04d-%02d', $year, $month);
        $typeLower = strtolower($type);

        switch ($typeLower) {
            case 'sss':
                return Excel::download(new SSSReportExport($companyId, $monthString), $this->sanitizeFilename("sss_report_{$monthString}.xlsx"));
            case 'philhealth':
                return Excel::download(new PhilHealthReportExport($companyId, $monthString), $this->sanitizeFilename("philhealth_report_{$monthString}.xlsx"));
            case 'pagibig':
            case 'pag-ibig':
                return Excel::download(new PagIBIGReportExport($companyId, $monthString), $this->sanitizeFilename("pagibig_report_{$monthString}.xlsx"));
            case 'tax':
            case 'withholding-tax':
                return Excel::download(new WithholdingTaxReportExport($companyId, $monthString), $this->sanitizeFilename("withholding_tax_report_{$monthString}.xlsx"));
            default:
                abort(404, 'Invalid statutory report type.');
        }
    }

    /**
     * Export 13th-Month payout summaries
     */
    public function exportThirteenthMonth(int $year)
    {
        $companyId = $this->resolveCompanyId(request());
        return Excel::download(\App\Exports\ThirteenthMonthExport::forCompany($companyId, $year), $this->sanitizeFilename("13th_month_pay_{$year}.xlsx"));
    }

    /**
     * Sanitize filename by replacing forward slashes, backslashes, and invalid characters.
     */
    private function sanitizeFilename(string $rawFilename): string
    {
        // Replace slashes/backslashes with hyphens
        $safeFilename = str_replace(['/', '\\'], '-', $rawFilename);

        // Remove any invalid filename characters except alphanumeric, space, dot, hyphen, underscore
        $safeFilename = preg_replace('/[^\w\s\d\-_~.]/', '', $safeFilename);

        // Replace whitespace sequences with a single underscore
        return preg_replace('/\s+/', '_', trim($safeFilename));
    }
}
