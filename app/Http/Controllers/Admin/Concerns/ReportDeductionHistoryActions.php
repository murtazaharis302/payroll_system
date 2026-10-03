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

trait ReportDeductionHistoryActions
{
    public function deductionPayrollHistory(Request $request)
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

            // Get all periods in the selected range
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

            // Deduction columns selected by user
            $selectedDeductions = $request->input('selected_deduction_types', $request->input('deduction_types', []));
            if (empty($selectedDeductions)) {
                $selectedDeductions = [
                    'sss_contribution', 'philhealth_contribution', 'pagibig_contribution',
                    'withholding_tax', 'sss_loan', 'pagibig_loan', 'vale_deduction', 'goods_deduction',
                    'absences_deduction', 'other_deductions', 'total_deductions',
                ];
            }

            // Employees strictly scoped to company & department
            $employeesQuery = \App\Models\EmployeeMaster::where('status', 'active')
                ->where('company_id', $companyId);
            if ($departmentId) {
                $employeesQuery->where('dept_id', $departmentId);
            }
            $employees = $employeesQuery->orderBy('last_name')->orderBy('first_name')->get();

            // Fetch approved/posted/paid transactions with payOuts
            $transactions = \App\Models\PayHeader::with(['employee.dept', 'payOuts.deductionType'])
                ->whereIn('payroll_period_id', $periodIds)
                ->whereIn('employee_id', $employees->pluck('id'))
                ->whereIn('status', \App\Models\PayHeader::FINAL_REPORT_STATUSES)
                ->get();

            $transactionMap = [];
            foreach ($transactions as $tx) {
                $transactionMap[$tx->employee_id][$tx->payroll_period_id] = $tx;
            }

            $deductionLabels = [
                'sss_contribution'       => 'SSS',
                'philhealth_contribution'=> 'PhilHealth',
                'pagibig_contribution'   => 'Pag-IBIG',
                'withholding_tax'        => 'W/Tax',
                'sss_loan'               => 'SSS Loan',
                'pagibig_loan'           => 'Pag-IBIG Loan',
                'vale_deduction'         => 'Vale',
                'goods_deduction'        => 'Goods',
                'late_deduction'         => 'Late Ded.',
                'undertime_deduction'    => 'Undertime Ded.',
                'absences_deduction'     => 'Absences',
                'other_deductions'       => 'Other Ded.',
                'total_deductions'       => 'Total Ded.',
            ];

            foreach ($selectedDeductions as $col) {
                if (is_numeric($col) && !isset($deductionLabels[$col])) {
                    $dedTypeModel = \App\Models\DeductionType::find($col);
                    $deductionLabels[$col] = $dedTypeModel ? $dedTypeModel->name : ('Deduction #' . $col);
                }
            }

            // Build report rows
            $reportData  = [];
            $grandTotals = [];
            foreach ($selectedDeductions as $col) {
                $grandTotals[$col] = 0;
            }
            $grandTotals['row_total'] = 0;

            foreach ($employees as $employee) {
                $row        = ['employee' => $employee, 'periods' => [], 'row_total' => 0];
                $hasAnyData = false;

                foreach ($periods as $period) {
                    $tx           = $transactionMap[$employee->id][$period->id] ?? null;
                    $periodValues = [];

                    foreach ($selectedDeductions as $col) {
                        $val = 0.0;
                        if ($tx) {
                            if ($displayOption === 'rate_only') {
                                if (is_numeric($col)) {
                                    $po = $tx->payOuts->where('deduction_type_id', (int)$col)->first();
                                    $val = $po ? (float) $po->rate : 0.0;
                                } else {
                                    $po = $tx->payOuts->first(fn($p) => \App\Support\Payroll\DeductionReportResolver::matchesPayOut($col, $p));
                                    $val = $po ? (float) $po->rate : (float) ($tx->$col ?? 0);
                                }
                            } elseif ($displayOption === 'number_only') {
                                if (is_numeric($col)) {
                                    $po = $tx->payOuts->where('deduction_type_id', (int)$col)->first();
                                    $val = $po ? (float) $po->number_input : 0.0;
                                } else {
                                    $po = $tx->payOuts->first(fn($p) => \App\Support\Payroll\DeductionReportResolver::matchesPayOut($col, $p));
                                    $val = $po ? (float) ($po->number_input ?? 1.0) : ($col === 'late_deduction' ? (float)($tx->late_minutes ?? 0) : ($col === 'undertime_deduction' ? (float)($tx->undertime_minutes ?? 0) : ($col === 'absences_deduction' ? 0.0 : 1.0)));
                                }
                            } else {
                                $val = \App\Support\Payroll\DeductionReportResolver::resolveAmountForHeader($tx, $col);
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
                return back()->with('error', 'No deduction data found for the selected criteria.');
            }

            $filenameBase = 'Deduction_Payroll_History_' . $fromPeriod->period_name . '_to_' . $toPeriod->period_name;
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\DeductionPayrollHistoryExport($reportData, $periods, $selectedDeductions, $deductionLabels, $displayOption),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\DeductionPayrollHistoryExport($reportData, $periods, $selectedDeductions, $deductionLabels, $displayOption),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.deduction-payroll-history', compact(
                    'company', 'periods', 'fromPeriod', 'toPeriod',
                    'reportData', 'grandTotals', 'selectedDeductions',
                    'deductionLabels', 'displayOption', 'includeZero'
                ))
                ->setPaper('legal', 'landscape');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Deduction Payroll History failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * Report #7: 13th Month Computation
     * Philippine Standard Formula: Total Basic Pay ÷ 12
     */
    public function thirteenthMonthComputation(Request $request)
    {
        try {
            $fromPeriodId = $request->input('from_period_id', $request->input('payroll_period_id'));
            $toPeriodId   = $request->input('to_period_id', $fromPeriodId);

            if (!$fromPeriodId) {
                return back()->with('error', 'Please select both Start Period and End Period.');
            }

            $fromPeriod = \App\Models\PayrollPeriod::findOrFail($fromPeriodId);
            $toPeriod   = \App\Models\PayrollPeriod::findOrFail($toPeriodId ?? $fromPeriodId);

            // Ensure chronological ordering
            if ($fromPeriod->period_start > $toPeriod->period_start) {
                $temp = $fromPeriod;
                $fromPeriod = $toPeriod;
                $toPeriod = $temp;
            }

            $companyId  = $this->resolveCompanyId($request) ?? $fromPeriod->company_id;
            $company    = \App\Models\Company::find($companyId) ?? $fromPeriod->company ?? \App\Models\Company::first();

            // Get all periods in range
            $periods = \App\Models\PayrollPeriod::where('period_start', '>=', $fromPeriod->period_start)
                ->where('period_start', '<=', $toPeriod->period_start)
                ->where('company_id', $companyId)
                ->orderBy('period_start')
                ->get();
            $periodIds = $periods->pluck('id')->toArray();
            $periodCount = $periods->count();

            if ($periods->isEmpty()) {
                return back()->with('error', 'No payroll periods found in the selected range.');
            }

            // Department filter
            $departmentId = $request->input('department_id');
            if ($departmentId === 'all') {
                $departmentId = null;
            }

            // Export format: pdf | xlsx | csv
            $exportFormat = strtolower($request->input('export_format', 'pdf'));

            // Employees strictly scoped to company & department
            $employeesQuery = \App\Models\EmployeeMaster::where('status', 'active')
                ->where('company_id', $companyId);
            if ($departmentId) {
                $employeesQuery->where('dept_id', $departmentId);
            }
            $employees = $employeesQuery->with('dept')
                ->orderBy('last_name')->orderBy('first_name')->get();

            // Get transactions
            $allTransactions = \App\Models\PayHeader::whereIn('payroll_period_id', $periodIds)
                ->whereIn('employee_id', $employees->pluck('id'))
                ->get();

            $txByEmployee = $allTransactions->groupBy('employee_id');

            // Build computation rows
            $reportData  = [];
            $grandTotal  = [
                'total_basic_pay'    => 0,
                'total_overtime'     => 0,
                'total_gross'        => 0,
                'thirteenth_month'   => 0,
                'periods_worked'     => 0,
            ];

            foreach ($employees as $employee) {
                $empTx = $txByEmployee->get($employee->id, collect());

                $totalBasicPay  = (float) $empTx->sum('basic_pay');
                $totalOvertime  = (float) $empTx->sum('overtime_pay');
                $totalGross     = (float) $empTx->sum('gross_income');
                $periodsWorked  = $empTx->count();

                if ($totalBasicPay <= 0) continue; // Skip employees with no basic pay

                // Statutory formula: Total Basic Pay ÷ 12
                $thirteenthMonth = round($totalBasicPay / 12.0, 2);

                $row = [
                    'employee'          => $employee,
                    'total_basic_pay'   => $totalBasicPay,
                    'total_overtime'    => $totalOvertime,
                    'total_gross'       => $totalGross,
                    'periods_worked'    => $periodsWorked,
                    'thirteenth_month'  => $thirteenthMonth,
                ];

                $reportData[] = $row;

                $grandTotal['total_basic_pay']  += $totalBasicPay;
                $grandTotal['total_overtime']   += $totalOvertime;
                $grandTotal['total_gross']      += $totalGross;
                $grandTotal['thirteenth_month'] += $thirteenthMonth;
                $grandTotal['periods_worked']   += $periodsWorked;
            }

            if (empty($reportData)) {
                return back()->with('error', 'No payroll data found for the selected criteria.');
            }

            $filenameBase = "13th_Month_Pay_{$fromPeriod->period_name}_to_{$toPeriod->period_name}";
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\ThirteenthMonthExport($reportData, $fromPeriod, $toPeriod, $periods),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\ThirteenthMonthExport($reportData, $fromPeriod, $toPeriod, $periods),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.13th-month-computation', compact(
                    'company', 'fromPeriod', 'toPeriod', 'periods',
                    'periodCount', 'reportData', 'grandTotal'
                ))
                ->setPaper('legal', 'landscape');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('13th Month Computation failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * Report #8: Bank Report / Advice (Transmittal Letter Format)
     * Professional bank transmittal letter requesting transfer of net pay to employee accounts.
     */
}
