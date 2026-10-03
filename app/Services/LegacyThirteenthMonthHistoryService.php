<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\IncomeType;
use App\Models\PayHeader;
use Illuminate\Support\Facades\DB;

class LegacyThirteenthMonthHistoryService
{
    /**
     * Generate Legacy 13th Month Payroll History Report data.
     *
     * @param int $companyId
     * @param int $year
     * @return array
     */
    public function generateReport(int $companyId, int $year): array
    {
        $company = Company::find($companyId);

        // Find BASIC pay income type IDs
        $basicPayTypeIds = IncomeType::withoutGlobalScopes()
            ->where('system_key', 'basic')
            ->pluck('id')
            ->toArray();

        // Query approved/posted/paid pay headers for target company and year
        $query = PayHeader::with(['employee.dept', 'payrollPeriod', 'payIns'])
            ->whereIn('status', ['approved', 'posted', 'paid'])
            ->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereHas('payrollPeriod', function ($q) use ($year) {
                $q->where(function ($pq) use ($year) {
                    $pq->whereYear('period_start', $year)
                       ->orWhereYear('pay_date', $year)
                       ->orWhereYear('period_end', $year);
                });
            });

        $payHeaders = $query->get();

        // Collect distinct payroll periods ordered by period_start / pay_date
        $periods = $payHeaders->pluck('payrollPeriod')
            ->filter()
            ->unique('id')
            ->sortBy(function ($p) {
                return $p->pay_date ?? $p->period_start ?? $p->period_end;
            })
            ->values();

        $cutoffs = [];
        foreach ($periods as $period) {
            $dateStr = $period->pay_date
                ? \Carbon\Carbon::parse($period->pay_date)->format('m/d/Y')
                : ($period->period_end ? \Carbon\Carbon::parse($period->period_end)->format('m/d/Y') : ('Cutoff ' . $period->id));

            $cutoffs[$period->id] = [
                'id'          => $period->id,
                'name'        => $period->period_name ?? ('Cutoff ' . $period->id),
                'pay_date'    => $dateStr,
                'short_label' => $dateStr,
            ];
        }

        $employeesMap = [];
        foreach ($payHeaders as $ph) {
            $emp = $ph->employee;
            if (!$emp) {
                continue;
            }

            if (!isset($employeesMap[$emp->id])) {
                $employeesMap[$emp->id] = [
                    'employee'             => $emp,
                    'employee_id'          => $emp->id,
                    'employee_code'        => $emp->employee_code,
                    'employee_name'        => $emp->full_name,
                    'department_name'      => $emp->department ?? ($emp->dept->name ?? 'N/A'),
                    'cutoff_values'        => [],
                    'total_basic'          => 0.00,
                    'thirteenth_month_pay' => 0.00,
                ];
                foreach ($cutoffs as $pId => $cData) {
                    $employeesMap[$emp->id]['cutoff_values'][$pId] = 0.00;
                }
            }

            // Calculate persisted BASIC PayIn sum strictly by basic pay type IDs
            $basicPay = (float) $ph->payIns
                ->whereIn('income_type_id', $basicPayTypeIds)
                ->sum(function ($pi) {
                    return (float) ($pi->rate ?? 0) * (float) ($pi->number_input ?? 1);
                });

            $periodId = $ph->payroll_period_id;
            if (isset($employeesMap[$emp->id]['cutoff_values'][$periodId])) {
                $employeesMap[$emp->id]['cutoff_values'][$periodId] += $basicPay;
            }
        }

        $rows = array_values($employeesMap);
        usort($rows, function ($a, $b) {
            return strcmp((string) $a['employee_name'], (string) $b['employee_name']);
        });

        $cutoffTotals = [];
        foreach ($cutoffs as $pId => $cData) {
            $cutoffTotals[$pId] = 0.00;
        }

        $grandTotalBasic = 0.00;
        $grandThirteenthMonthPay = 0.00;

        foreach ($rows as &$row) {
            $rowTotal = 0.00;
            foreach ($cutoffs as $pId => $cData) {
                $val = (float) ($row['cutoff_values'][$pId] ?? 0.00);
                $rowTotal += $val;
                $cutoffTotals[$pId] += $val;
            }
            $row['total_basic'] = round($rowTotal, 2);
            $row['thirteenth_month_pay'] = round($rowTotal / 11.5, 2);

            $grandTotalBasic += $row['total_basic'];
            $grandThirteenthMonthPay += $row['thirteenth_month_pay'];
        }
        unset($row);

        return [
            'company'                   => $company,
            'company_id'                => $companyId,
            'year'                      => $year,
            'cutoffs'                   => $cutoffs,
            'rows'                      => $rows,
            'cutoff_totals'             => $cutoffTotals,
            'grand_total_basic'         => round($grandTotalBasic, 2),
            'grand_thirteenth_month_pay'=> round($grandThirteenthMonthPay, 2),
        ];
    }
}
