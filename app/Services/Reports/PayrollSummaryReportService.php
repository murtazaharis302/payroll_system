<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Support\Payroll\PayrollTotalsCalculator;

class PayrollSummaryReportService
{
    protected PayrollTotalsCalculator $calculator;

    public function __construct(?PayrollTotalsCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new PayrollTotalsCalculator();
    }

    /**
     * Build unified dataset for Payroll Summary 1, Summary 2, and Excel exports.
     *
     * @param PayrollPeriod $period
     * @param Company|null $company
     * @param int|null $companyId
     * @param int|string|null $departmentId
     * @param string $paperSize 'legal' or 'letter'
     * @return array
     */
    public function build(
        PayrollPeriod $period,
        ?Company $company = null,
        ?int $companyId = null,
        $departmentId = null,
        string $paperSize = 'legal'
    ): array {
        $departmentFilter = ($departmentId === 'all' || empty($departmentId)) ? null : $departmentId;

        $query = PayHeader::withoutGlobalScopes()->with([
            'employee' => fn ($q) => $q->withoutGlobalScopes()->with('dept'),
            'payIns' => fn ($q) => $q->withoutGlobalScopes()->with(['incomeType' => fn ($iq) => $iq->withoutGlobalScopes()]),
            'payOuts' => fn ($q) => $q->withoutGlobalScopes()->with(['deductionType' => fn ($dq) => $dq->withoutGlobalScopes()]),
            'payrollPeriod',
        ])
        ->where('payroll_period_id', $period->id);

        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->withoutGlobalScopes()->where('company_id', $companyId);
            });
        }

        if ($departmentFilter !== null) {
            $query->whereHas('employee', function ($q) use ($departmentFilter) {
                $q->withoutGlobalScopes()->where('dept_id', $departmentFilter);
            });
        }

        $payrolls = $query->orderBy('employee_id')->get();

        if ($payrolls->isEmpty()) {
            return [
                'period' => $period,
                'company' => $company,
                'payrolls' => collect(),
                'displayedIncomes' => collect(),
                'hiddenIncomes' => collect(),
                'displayedDeductions' => collect(),
                'hiddenDeductions' => collect(),
                'payrollRows' => collect(),
                'totals' => [],
            ];
        }

        // 1. Identify distinct active IncomeTypes in scoped PayIn rows (excluding BASIC and OVERTIME)
        $basicPayTypeIds = IncomeType::withoutGlobalScopes()
            ->where('system_key', 'basic')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
        $otPayTypeIds = IncomeType::withoutGlobalScopes()
            ->where('system_key', 'overtime')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $excludedTypeIds = array_merge($basicPayTypeIds, $otPayTypeIds);
        $payHeaderIds = $payrolls->pluck('id')->toArray();

        $additionalIncomeTypeIds = \App\Models\PayIn::withoutGlobalScopes()
            ->whereIn('pay_id', $payHeaderIds)
            ->whereNotIn('income_type_id', $excludedTypeIds)
            ->pluck('income_type_id')
            ->unique()
            ->filter()
            ->toArray();

        $additionalIncomeTypes = IncomeType::withoutGlobalScopes()
            ->whereIn('id', $additionalIncomeTypeIds)
            ->orderBy('name')
            ->get();

        // 2. Identify distinct active DeductionTypes in scoped PayOut rows
        $activeDeductionTypeIds = \App\Models\PayOut::withoutGlobalScopes()
            ->whereIn('pay_id', $payHeaderIds)
            ->pluck('deduction_type_id')
            ->unique()
            ->filter()
            ->toArray();

        $activeDeductionTypes = DeductionType::withoutGlobalScopes()
            ->whereIn('id', $activeDeductionTypeIds)
            ->orderBy('name')
            ->get();

        // 3. Dynamic Paper Width Column Partitioning
        $paperWidthPoints = (strtolower($paperSize) === 'letter') ? 792 : 1008;
        $horizontalMargins = 16 * 2.834; // ~45.3pt
        $printableWidth = $paperWidthPoints - $horizontalMargins;

        // Base fixed width components: No (20) + Name (75) + Net (45) + Signature (60) + Reg Rate (45) + Basic (80) + OT (80) + Total Gross (45) + Total Ded (45) = 495pt
        $baseFixedWidth = 495;
        $remainingWidth = max(0, $printableWidth - $baseFixedWidth);

        $incomeColWidth = 80; // 2 sub-cols: Qty + Amount
        $deductionColWidth = 45;

        $incomeCount = $additionalIncomeTypes->count();
        $deductionCount = $activeDeductionTypes->count();

        $displayedIncomes = collect();
        $hiddenIncomes = collect();

        $displayedDeductions = collect();
        $hiddenDeductions = collect();

        $allIncomesWidth = $incomeCount * $incomeColWidth;
        $allDeductionsWidth = $deductionCount * $deductionColWidth;

        if ($allIncomesWidth + $allDeductionsWidth <= $remainingWidth) {
            $displayedIncomes = $additionalIncomeTypes;
            $displayedDeductions = $activeDeductionTypes;
        } else {
            $maxIncomesToDisplay = 0;
            for ($i = 0; $i <= $incomeCount; $i++) {
                $incNeedOther = ($i < $incomeCount) ? 45 : 0;
                $wInc = ($i * $incomeColWidth) + $incNeedOther;
                $wForDed = $remainingWidth - $wInc;
                if ($wForDed >= 0) {
                    $maxIncomesToDisplay = $i;
                }
            }

            if ($maxIncomesToDisplay >= $incomeCount) {
                $displayedIncomes = $additionalIncomeTypes;
            } else {
                $displayedIncomes = $additionalIncomeTypes->take($maxIncomesToDisplay);
                $hiddenIncomes = $additionalIncomeTypes->slice($maxIncomesToDisplay);
            }

            $usedIncWidth = ($displayedIncomes->count() * $incomeColWidth) + ($hiddenIncomes->isNotEmpty() ? 45 : 0);
            $wForDed = max(0, $remainingWidth - $usedIncWidth);

            $maxDedsToDisplay = (int) floor($wForDed / $deductionColWidth);
            if ($maxDedsToDisplay >= $deductionCount) {
                $displayedDeductions = $activeDeductionTypes;
            } else {
                $dedLimit = max(0, $maxDedsToDisplay - 1);
                $displayedDeductions = $activeDeductionTypes->take($dedLimit);
                $hiddenDeductions = $activeDeductionTypes->slice($dedLimit);
            }
        }

        // 4. Map each payroll record into structured row data
        $payrollRows = collect();

        $totals = [
            'regular_rate' => 0.00,
            'basic_qty' => 0.00,
            'basic_amount' => 0.00,
            'ot_qty' => 0.00,
            'ot_amount' => 0.00,
            'displayed_incomes' => [],
            'other_income_amount' => 0.00,
            'total_gross_pay' => 0.00,
            'displayed_deductions' => [],
            'others_deductions_amount' => 0.00,
            'total_deductions' => 0.00,
            'net_pay' => 0.00,
        ];

        foreach ($displayedIncomes as $incType) {
            $totals['displayed_incomes'][$incType->id] = ['qty' => 0.00, 'amount' => 0.00];
        }
        foreach ($displayedDeductions as $dedType) {
            $totals['displayed_deductions'][$dedType->id] = 0.00;
        }

        foreach ($payrolls as $ph) {
            $emp = $ph->employee;
            $regularRate = (float) ($emp->monthly_rate > 0 ? $emp->monthly_rate : ($emp->daily_rate > 0 ? $emp->daily_rate : 0.00));

            $basicQty = 0.00;
            $basicAmount = 0.00;
            $otQty = 0.00;
            $otAmount = 0.00;

            $dispIncomes = [];
            foreach ($displayedIncomes as $incType) {
                $dispIncomes[$incType->id] = ['qty' => 0.00, 'amount' => 0.00];
            }

            $otherIncomeAmount = 0.00;

            foreach ($ph->payIns as $pi) {
                $typeId = (int) $pi->income_type_id;
                $qty = (float) ($pi->number_input ?? 0.00);
                $amt = (float) ($pi->rate * $qty);

                if (in_array($typeId, $basicPayTypeIds, true)) {
                    $basicQty += $qty;
                    $basicAmount += $amt;
                } elseif (in_array($typeId, $otPayTypeIds, true)) {
                    $otQty += $qty;
                    $otAmount += $amt;
                } elseif (isset($dispIncomes[$typeId])) {
                    $dispIncomes[$typeId]['qty'] += $qty;
                    $dispIncomes[$typeId]['amount'] += $amt;
                } else {
                    $otherIncomeAmount += $amt;
                }
            }

            if ($basicQty <= 0 && (float)$ph->days_worked > 0) {
                $basicQty = (float) $ph->days_worked;
            }
            if ($otQty <= 0 && (float)$ph->overtime_hours > 0) {
                $otQty = (float) $ph->overtime_hours;
            }

            $totalGrossPay = (float) $ph->total_gross_pay;

            $dispDeductions = [];
            foreach ($displayedDeductions as $dedType) {
                $dispDeductions[$dedType->id] = 0.00;
            }

            $othersDeductionsAmount = 0.00;

            foreach ($ph->payOuts as $po) {
                $dedTypeId = (int) $po->deduction_type_id;
                $amt = (float) ($po->rate * ($po->number_input ?? 1.00));

                if (isset($dispDeductions[$dedTypeId])) {
                    $dispDeductions[$dedTypeId] += $amt;
                } elseif ($hiddenDeductions->isNotEmpty()) {
                    $othersDeductionsAmount += $amt;
                }
            }

            $totalDeductions = (float) $ph->total_deductions;
            $netPay = (float) $ph->net_pay;

            $rowItem = [
                'pay_header' => $ph,
                'employee' => $emp,
                'regular_rate' => $regularRate,
                'basic_qty' => $basicQty,
                'basic_amount' => $basicAmount,
                'ot_qty' => $otQty,
                'ot_amount' => $otAmount,
                'displayed_incomes' => $dispIncomes,
                'other_income_amount' => $otherIncomeAmount,
                'total_gross_pay' => $totalGrossPay,
                'displayed_deductions' => $dispDeductions,
                'others_deductions_amount' => $othersDeductionsAmount,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
            ];

            $payrollRows->push($rowItem);

            $totals['regular_rate'] += $regularRate;
            $totals['basic_qty'] += $basicQty;
            $totals['basic_amount'] += $basicAmount;
            $totals['ot_qty'] += $otQty;
            $totals['ot_amount'] += $otAmount;
            foreach ($dispIncomes as $tId => $data) {
                $totals['displayed_incomes'][$tId]['qty'] += $data['qty'];
                $totals['displayed_incomes'][$tId]['amount'] += $data['amount'];
            }
            $totals['other_income_amount'] += $otherIncomeAmount;
            $totals['total_gross_pay'] += $totalGrossPay;

            foreach ($dispDeductions as $dId => $amt) {
                $totals['displayed_deductions'][$dId] += $amt;
            }
            $totals['others_deductions_amount'] += $othersDeductionsAmount;
            $totals['total_deductions'] += $totalDeductions;
            $totals['net_pay'] += $netPay;
        }

        return [
            'period' => $period,
            'company' => $company,
            'payrolls' => $payrolls,
            'payrollRows' => $payrollRows,
            'displayedIncomes' => $displayedIncomes,
            'hiddenIncomes' => $hiddenIncomes,
            'displayedDeductions' => $displayedDeductions,
            'hiddenDeductions' => $hiddenDeductions,
            'totals' => $totals,
        ];
    }
}
