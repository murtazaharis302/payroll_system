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

trait ReportIncomeActions
{
    public function incomeHistory(Request $request)
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

            // Resolve selected income types. If empty (unselected), default to all income types
            $rawSelectedIncomes = $request->input('selected_income_types') ?: $request->input('income_types');
            if (empty($rawSelectedIncomes)) {
                $selectedIncomes = [
                    'basic_salary', 'overtime_pay', 'night_diff_pay', 'holiday_pay',
                    'thirteenth_month_pay', 'allowances', 'incentives', 'other_income'
                ];
                $customTypeIds = \App\Models\IncomeType::forCompany($companyId)->where('is_active', true)->pluck('id')->toArray();
                foreach ($customTypeIds as $cid) {
                    $selectedIncomes[] = (string) $cid;
                }
            } else {
                $selectedIncomes = is_array($rawSelectedIncomes) ? $rawSelectedIncomes : [$rawSelectedIncomes];
            }

            // Labels map
            $incomeLabels = [
                'basic_salary'         => 'Basic Salary',
                'overtime_pay'         => 'Overtime',
                'night_diff_pay'       => 'Night Diff',
                'holiday_pay'          => 'Holiday Pay',
                'thirteenth_month_pay' => '13th Month Pay',
                'allowances'           => 'Allowance',
                'incentives'           => 'Incentive',
                'other_income'         => 'Other Income',
            ];

            // Add custom income type names if numeric IDs are passed
            $customTypes = \App\Models\IncomeType::whereIn('id', array_filter($selectedIncomes, 'is_numeric'))->get()->keyBy('id');
            foreach ($customTypes as $id => $it) {
                $incomeLabels[$id] = $it->name;
            }

            $historyService = app(\App\Services\Reports\IncomeHistoryReportService::class);
            $historyData = $historyService->build($period, $company, $companyId, [
                'department_id' => $departmentId,
            ]);
            $payHeaders = $historyData['payHeaders'];

            if ($payHeaders->isEmpty()) {
                return back()->with('error', 'No payroll transactions found for the selected period.');
            }

            $codeToKeyMap = [
                'BASIC'      => 'basic_salary',
                'OVERTIME'   => 'overtime_pay',
                'NIGHT_DIFF' => 'night_diff_pay',
                'HOLIDAY'    => 'holiday_pay',
                '13TH_MONTH' => 'thirteenth_month_pay',
                'ALLOWANCE'  => 'allowances',
                'BONUS'      => 'incentives',
                'INCENTIVE'  => 'incentives',
                'OTHER'      => 'other_income',
            ];

            $resolveIncomeKey = function($pi) use ($codeToKeyMap, $selectedIncomes) {
                if (!$pi) return null;
                $typeId = (string) $pi->income_type_id;
                if (in_array($typeId, $selectedIncomes, true) || in_array((int)$typeId, $selectedIncomes, true)) {
                    return $typeId;
                }

                $sysKey = $pi->incomeType?->system_key;
                if ($sysKey) {
                    $systemMap = [
                        'basic' => 'basic_salary',
                        'overtime' => 'overtime_pay',
                        'night_diff' => 'night_diff_pay',
                        'holiday' => 'holiday_pay',
                        '13th_month' => 'thirteenth_month_pay',
                    ];
                    if (isset($systemMap[$sysKey]) && in_array($systemMap[$sysKey], $selectedIncomes, true)) {
                        return $systemMap[$sysKey];
                    }
                }

                $code = strtoupper(trim($pi->incomeType?->code ?? ''));
                if (isset($codeToKeyMap[$code])) {
                    $key = $codeToKeyMap[$code];
                    if (in_array($key, $selectedIncomes, true)) {
                        return $key;
                    }
                }

                return null;
            };

            $reportData = [];
            $columnTotals = array_fill_keys($selectedIncomes, 0.0);
            $grandTotalIncome = 0.0;

            foreach ($payHeaders as $ph) {
                $emp = $ph->employee;
                if (!$emp) continue;

                $rowIncomes = array_fill_keys($selectedIncomes, 0.0);
                $rowSum = 0.0;

                if ($ph->payIns->count() > 0) {
                    foreach ($ph->payIns as $pi) {
                        $key = $resolveIncomeKey($pi);
                        if ($key && isset($rowIncomes[$key])) {
                            $val = (float) ($pi->rate * $pi->number_input);
                            if ($val <= 0 && isset($pi->amount)) {
                                $val = (float) $pi->amount;
                            }
                            $rowIncomes[$key] += $val;
                            $rowSum += $val;
                        }
                    }
                } else {
                    // Fallback to PayHeader accessors only if payIns is empty
                    $phIncomeMap = [
                        'basic_salary'         => (float) ($ph->basic_pay ?? $ph->basic_salary ?? 0),
                        'overtime_pay'         => (float) ($ph->overtime_pay ?? 0),
                        'night_diff_pay'       => (float) ($ph->night_diff_pay ?? $ph->night_diff ?? 0),
                        'holiday_pay'          => (float) ($ph->holiday_pay ?? 0),
                        'thirteenth_month_pay' => (float) ($ph->thirteenth_month_pay ?? $ph->thirteenth_month ?? 0),
                        'allowances'           => (float) ($ph->total_allowances ?? $ph->allowances ?? 0),
                        'incentives'           => (float) ($ph->total_bonuses ?? $ph->incentives ?? 0),
                        'other_income'         => (float) ($ph->other_income ?? 0),
                    ];

                    foreach ($selectedIncomes as $key) {
                        if ($rowIncomes[$key] == 0 && isset($phIncomeMap[$key]) && $phIncomeMap[$key] > 0) {
                            $val = $phIncomeMap[$key];
                            $rowIncomes[$key] = $val;
                            $rowSum += $val;
                        }
                    }
                }

                // Filter out zero total amount rows if requested
                if ($dontIncludeZero && $rowSum <= 0) {
                    continue;
                }

                foreach ($selectedIncomes as $key) {
                    $columnTotals[$key] += $rowIncomes[$key];
                }
                $grandTotalIncome += $rowSum;

                $reportData[] = [
                    'payroll'       => $ph,
                    'employee'      => $emp,
                    'employee_code' => $emp->employee_code ?? 'EMP' . $emp->id,
                    'name'          => $emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . substr($emp->middle_name, 0, 1) . '.' : ''),
                    'department'    => $emp->dept->name ?? '—',
                    'incomes'       => $rowIncomes,
                    'total_income'  => $rowSum,
                ];
            }

            if (empty($reportData)) {
                return back()->with('error', 'No income records found matching the selected criteria.');
            }

            $filenameBase = "Income_History_{$period->period_name}";
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\SingleIncomeHistoryExport($reportData, $selectedIncomes, $incomeLabels, $period, $company),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\SingleIncomeHistoryExport($reportData, $selectedIncomes, $incomeLabels, $period, $company),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            if ($exportFormat === 'html') {
                return view('admin.reports.income-history', compact(
                    'company', 'period', 'reportData', 'selectedIncomes', 'incomeLabels', 'columnTotals', 'grandTotalIncome'
                ));
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.income-history', compact(
                    'company', 'period', 'reportData', 'selectedIncomes', 'incomeLabels', 'columnTotals', 'grandTotalIncome'
                ))
                ->setPaper('legal', 'landscape');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Single-Period Income History failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate single-period income history: ' . $e->getMessage());
        }
    }

    /**
     * Report #11: Deduction Ledger (Vertical Period History per Deduction Item)
     * Displays historical deductions for a single selected deduction type with payroll periods rendered as vertical rows.
     */
}
