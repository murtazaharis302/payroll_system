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

trait ReportPayrollHistoryActions
{
    public function voucher(Request $request)
    {
        $request->validate([
            'payroll_period_id' => 'required|exists:payroll_periods,id',
            'department_id' => 'nullable',
            'voucher_type' => 'nullable|string',
            'voucher_date' => 'nullable|date',
            'description' => 'nullable|string',
            'display_breakdown' => 'nullable',
            'include_zero' => 'nullable',
            'income_types' => 'nullable|array',
        ]);

        $period = PayrollPeriod::findOrFail($request->payroll_period_id);
        $companyId = $this->resolveCompanyId($request) ?? $period->company_id;
        $company = Company::find($companyId) ?? $period->company ?? Company::first();

        $voucherType = $request->input('voucher_type', 'Salaries & Wages');
        $voucherDate = $request->filled('voucher_date')
            ? \Carbon\Carbon::parse($request->voucher_date)->format('M d, Y')
            : \Carbon\Carbon::parse($period->pay_date ?? $period->period_end)->format('M d, Y');
        $description = $request->input('description', 'To payment for salaries and wages');

        $displayBreakdown = $request->boolean('display_breakdown', true);

        $includeZeroInput = $request->input('include_zero');
        $includeZero = $includeZeroInput !== null ? filter_var($includeZeroInput, FILTER_VALIDATE_BOOLEAN) : false;

        $selectedIncomeTypeIds = array_map('intval', (array) $request->input('income_types', []));

        $voucherService = app(\App\Services\Reports\VoucherReportService::class);
        $voucherData = $voucherService->build($period, $company, $companyId, [
            'department_id' => $request->department_id,
            'income_types' => $request->input('income_types', []),
            'include_zero' => $request->boolean('include_zero'),
        ]);
        $payrolls = $voucherData['payrolls'];

        if ($payrolls->isEmpty()) {
            return back()->with('error', 'No payroll data found for selected criteria.');
        }



        if ($request->input('export_format') === 'html') {
            return view('admin.reports.voucher', compact(
                'payrolls',
                'company',
                'period',
                'voucherType',
                'voucherDate',
                'description',
                'displayBreakdown',
                'includeZero'
            ));
        }

        $pdf = app('dompdf.wrapper')->loadView('admin.reports.voucher', compact(
            'payrolls',
            'company',
            'period',
            'voucherType',
            'voucherDate',
            'description',
            'displayBreakdown',
            'includeZero'
        ))->setPaper('letter', 'portrait');

        $filename = $this->sanitizeFilename('Cash_Vouchers_' . $period->period_name . '.pdf');

        return $pdf->stream($filename);
    }

    /**
     * Batch generate payslips for all employees in a period.
     * Generates a single PDF with 4 payslips per A4 landscape page.
     */
    public function batchPayslips(Request $request)
    {
        $request->validate([
            'payroll_period_id' => 'required|exists:payroll_periods,id',
        ]);

        $period    = PayrollPeriod::findOrFail($request->payroll_period_id);
        $companyId = $this->resolveCompanyId($request) ?? $period->company_id;
        $company   = Company::find($companyId) ?? $period->company ?? Company::first();

        // Build query with conditional company filter
        $query = PayHeader::with(['payIns.incomeType', 'payOuts.deductionType', 'employee', 'payrollPeriod'])
            ->where('payroll_period_id', $request->payroll_period_id)
            ->orderBy('employee_id');

        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $payrolls = $query->get();

        if ($payrolls->isEmpty()) {
            return back()->with('error', 'No payroll records found for the selected period.');
        }

        // Generate a single PDF — batch-payslips view places 4 payslips per landscape page
        $pdf = app('dompdf.wrapper')
            ->loadView('admin.reports.batch-payslips', compact('payrolls', 'company', 'period'))
            ->setPaper('a4', 'landscape');

        $filename = $this->sanitizeFilename('Payslips_' . $period->period_name . '.pdf');

        return $pdf->stream($filename);
    }

    /**
     * Report #5: Income Payroll History (Multi-Period)
     * Shows income across multiple payroll periods per employee
     */
    /**
     * Report #5: Income Payroll History (Multi-Period Matrix)
     * Tracks employee earnings across a customizable range of payroll periods
     */
    public function incomePayrollHistory(Request $request)
    {
        try {
            $fromPeriodId = $request->input('from_period_id', $request->input('payroll_period_id'));
            $toPeriodId   = $request->input('to_period_id', $fromPeriodId);

            if (!$fromPeriodId) {
                return back()->with('error', 'Please select both From Period and To Period.');
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

            // Get all periods between from and to (ordered by date)
            $periods = \App\Models\PayrollPeriod::where('period_start', '>=', $fromPeriod->period_start)
                ->where('period_start', '<=', $toPeriod->period_start)
                ->where('company_id', $companyId)
                ->orderBy('period_start')
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

            // Display Mode: rate_and_number | rate_only | number_only
            $displayOption = $request->input('display_mode', $request->input('display_option', 'rate_and_number'));
            if ($displayOption === 'amount') {
                $displayOption = 'rate_and_number';
            } elseif ($displayOption === 'rate') {
                $displayOption = 'rate_only';
            } elseif ($displayOption === 'number') {
                $displayOption = 'number_only';
            }

            // Include zero amounts toggle
            $includeZero = $request->has('include_zero_amount')
                ? filter_var($request->input('include_zero_amount'), FILTER_VALIDATE_BOOLEAN)
                : filter_var($request->input('include_zero', true), FILTER_VALIDATE_BOOLEAN);

            // Export format: pdf | xlsx | csv
            $exportFormat = strtolower($request->input('export_format', 'pdf'));

            // Income columns selected by user
            $selectedIncomes = $request->input('selected_income_types', $request->input('income_types', []));
            if (empty($selectedIncomes)) {
                $selectedIncomes = ['basic_pay', 'overtime_pay', 'allowances', 'bonuses', 'other_income', 'gross_income'];
            }

            // Get all employees strictly scoped to company & department
            $employeesQuery = \App\Models\EmployeeMaster::where('status', 'active')
                ->where('company_id', $companyId);
            if ($departmentId) {
                $employeesQuery->where('dept_id', $departmentId);
            }
            $employees = $employeesQuery->orderBy('last_name')->orderBy('first_name')->get();

            // Map: employee_id => [ period_id => PayHeader ]
            $transactions = \App\Models\PayHeader::with(['employee.dept', 'payIns.incomeType'])
                ->whereIn('payroll_period_id', $periodIds)
                ->whereIn('employee_id', $employees->pluck('id'))
                ->get();

            $transactionMap = [];
            foreach ($transactions as $tx) {
                $transactionMap[$tx->employee_id][$tx->payroll_period_id] = $tx;
            }

            $incomeLabels = [
                'basic_pay'    => 'Basic Pay',
                'overtime_pay' => 'Overtime',
                'allowances'   => 'Allowances',
                'bonuses'      => 'Bonuses',
                'other_income' => 'Other Income',
                'gross_income' => 'Gross Income',
            ];

            foreach ($selectedIncomes as $col) {
                if (is_numeric($col) && !isset($incomeLabels[$col])) {
                    $incTypeModel = \App\Models\IncomeType::find($col);
                    $incomeLabels[$col] = $incTypeModel ? $incTypeModel->name : ('Income #' . $col);
                }
            }

            // Build report data rows
            $reportData = [];
            $grandTotals = [];
            foreach ($selectedIncomes as $col) {
                $grandTotals[$col] = 0;
            }
            $grandTotals['row_total'] = 0;

            foreach ($employees as $employee) {
                $row = ['employee' => $employee, 'periods' => [], 'row_total' => 0];
                $hasAnyData = false;

                foreach ($periods as $period) {
                    $tx = $transactionMap[$employee->id][$period->id] ?? null;
                    $periodValues = [];

                    foreach ($selectedIncomes as $col) {
                        $val = 0.0;
                        if ($tx) {
                            if (is_numeric($col)) {
                                $pi = $tx->payIns->where('income_type_id', (int)$col)->first();
                                if ($pi) {
                                    if ($displayOption === 'rate_only') {
                                        $val = (float) $pi->rate;
                                    } elseif ($displayOption === 'number_only') {
                                        $val = (float) $pi->number_input;
                                    } else {
                                        $val = (float) $pi->rate * (float) $pi->number_input;
                                    }
                                }
                            } else {
                                if ($displayOption === 'rate_only') {
                                    $val = (float) ($tx->$col ?? 0);
                                } elseif ($displayOption === 'number_only') {
                                    $val = $col === 'overtime_pay' ? (float)($tx->overtime_hours ?? 0) : ($col === 'basic_pay' ? (float)($tx->days_worked ?? 0) : 1.0);
                                } else {
                                    $val = (float) ($tx->$col ?? 0);
                                }
                            }
                        }

                        $periodValues[$col] = $val;
                        $row['row_total'] += $val;
                        $grandTotals[$col] = ($grandTotals[$col] ?? 0) + $val;
                        if ($val > 0) {
                            $hasAnyData = true;
                        }
                    }

                    $row['periods'][$period->id] = $periodValues;
                }

                $grandTotals['row_total'] += $row['row_total'];

                if ($includeZero || $hasAnyData || $row['row_total'] > 0) {
                    $reportData[] = $row;
                }
            }

            if (empty($reportData)) {
                return back()->with('error', 'No income data found for the selected criteria.');
            }

            $filenameBase = 'Income_Payroll_History_' . $fromPeriod->period_name . '_to_' . $toPeriod->period_name;
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\IncomePayrollHistoryExport($reportData, $periods, $selectedIncomes, $incomeLabels, $displayOption),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\IncomePayrollHistoryExport($reportData, $periods, $selectedIncomes, $incomeLabels, $displayOption),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.income-payroll-history', compact(
                    'company', 'periods', 'fromPeriod', 'toPeriod',
                    'reportData', 'grandTotals', 'selectedIncomes',
                    'incomeLabels', 'displayOption', 'includeZero'
                ))
                ->setPaper('legal', 'landscape');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Income Payroll History failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * Report #6: Deduction Payroll History (Multi-Period Matrix)
     * Tracks employee deductions across a customizable range of payroll periods
     */

    /**
     * Report: Recurring Income Summary by Payroll Period (Issue #27)
     * Groups selected income types SUM per employee, per payroll period column.
     * Uses a single Payroll Date to identify the period range.
     */
    public function recurringIncomeSummary(Request $request)
    {
        try {
            // Accept single period_id OR a payroll_date to locate the period
            $periodId     = $request->input('payroll_period_id');
            $payrollDate  = $request->input('payroll_date');

            if (!$periodId && !$payrollDate) {
                return back()->with('error', 'Please select a Payroll Period or Payroll Date.');
            }

            $companyId = $this->resolveCompanyId($request);

            // Locate the "anchor" period
            if ($periodId) {
                $selectedPeriod = \App\Models\PayrollPeriod::findOrFail($periodId);
                $companyId      = $companyId ?? $selectedPeriod->company_id;
            } else {
                $selectedPeriod = \App\Models\PayrollPeriod::where('company_id', $companyId)
                    ->where(function ($q) use ($payrollDate) {
                        $q->whereDate('pay_date', $payrollDate)
                          ->orWhereDate('period_end', $payrollDate);
                    })
                    ->orderBy('period_start')
                    ->firstOrFail();
            }

            $company = \App\Models\Company::find($companyId)
                ?? $selectedPeriod->company
                ?? \App\Models\Company::first();

            // Fetch ALL periods for this company ordered by date
            // (show all active periods, each becomes one column)
            $periods = \App\Models\PayrollPeriod::where('company_id', $companyId)
                ->orderBy('period_start')
                ->get();

            if ($periods->isEmpty()) {
                return back()->with('error', 'No payroll periods found for this company.');
            }

            $periodIds = $periods->pluck('id')->toArray();

            // Department filter
            $departmentId = $request->input('department_id');
            if ($departmentId === 'all') {
                $departmentId = null;
            }

            // Include zero toggle
            $includeZero = $request->has('include_zero_amount')
                ? filter_var($request->input('include_zero_amount'), FILTER_VALIDATE_BOOLEAN)
                : filter_var($request->input('include_zero', true), FILTER_VALIDATE_BOOLEAN);

            // Export format
            $exportFormat = strtolower($request->input('export_format', 'pdf'));

            // Income columns selected by user
            $selectedIncomes = $request->input('selected_income_types', $request->input('income_types', []));
            if (empty($selectedIncomes)) {
                $selectedIncomes = ['basic_pay', 'overtime_pay', 'allowances', 'bonuses', 'other_income'];
            }

            // Income labels map
            $incomeLabels = [
                'basic_pay'    => 'Basic Pay',
                'overtime_pay' => 'Overtime',
                'allowances'   => 'Allowances',
                'bonuses'      => 'Bonuses',
                'other_income' => 'Other Income',
                'gross_income' => 'Gross Income',
            ];
            foreach ($selectedIncomes as $col) {
                if (is_numeric($col) && !isset($incomeLabels[$col])) {
                    $incTypeModel = \App\Models\IncomeType::find($col);
                    $incomeLabels[$col] = $incTypeModel ? $incTypeModel->name : ('Income #' . $col);
                }
            }

            // Get employees
            $employeesQuery = \App\Models\EmployeeMaster::where('status', 'active')
                ->where('company_id', $companyId);
            if ($departmentId) {
                $employeesQuery->where('dept_id', $departmentId);
            }
            $employees = $employeesQuery->orderBy('last_name')->orderBy('first_name')->get();

            // Load transactions
            $transactions = \App\Models\PayHeader::with(['employee.dept', 'payIns.incomeType'])
                ->whereIn('payroll_period_id', $periodIds)
                ->whereIn('employee_id', $employees->pluck('id'))
                ->get();

            $transactionMap = [];
            foreach ($transactions as $tx) {
                $transactionMap[$tx->employee_id][$tx->payroll_period_id] = $tx;
            }

            // Build report: one row per employee, columns = periods, cell = SUM of selected incomes
            $reportData   = [];
            $overallTotal = 0;

            foreach ($employees as $employee) {
                $row         = ['employee' => $employee, 'period_sums' => [], 'row_total' => 0];
                $hasAnyData  = false;

                foreach ($periods as $period) {
                    $tx  = $transactionMap[$employee->id][$period->id] ?? null;
                    $sum = 0.0;

                    if ($tx) {
                        foreach ($selectedIncomes as $col) {
                            if (is_numeric($col)) {
                                $pi = $tx->payIns->where('income_type_id', (int) $col)->first();
                                if ($pi) {
                                    $sum += (float) $pi->rate * (float) $pi->number_input;
                                }
                            } else {
                                $sum += (float) ($tx->$col ?? 0);
                            }
                        }
                    }

                    $row['period_sums'][$period->id] = $sum;
                    $row['row_total']               += $sum;
                    if ($sum > 0) {
                        $hasAnyData = true;
                    }
                }

                $overallTotal += $row['row_total'];

                if ($includeZero || $hasAnyData) {
                    $reportData[] = $row;
                }
            }

            if (empty($reportData)) {
                return back()->with('error', 'No income data found for the selected criteria.');
            }

            $filenameBase = 'Recurring_Income_Summary_' . ($selectedPeriod->period_name ?? 'Report');
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\RecurringIncomeSummaryExport($reportData, $periods, $selectedIncomes, $incomeLabels),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\RecurringIncomeSummaryExport($reportData, $periods, $selectedIncomes, $incomeLabels),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.recurring-income-summary', compact(
                    'company', 'periods', 'selectedPeriod',
                    'reportData', 'selectedIncomes', 'incomeLabels', 'includeZero'
                ))
                ->setPaper('legal', 'landscape');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Exception $e) {
            \Log::error('Recurring Income Summary failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }
}
