<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ThirteenthMonthService
{
    /**
     * Calculate Year-To-Date (YTD) basic pay earned by an employee.
     *
     * @param EmployeeMaster $employee
     * @param int $year
     * @return float
     */
    public function calculateYtdBasicPay(EmployeeMaster $employee, int $year): float
    {
        // Find basic pay income type IDs
        $basicPayTypeIds = IncomeType::where('system_key', 'basic')
            ->pluck('id')
            ->toArray();

        if (empty($basicPayTypeIds)) {
            return 0.00;
        }

        // Sum basic pay rate * number_input from approved pay headers in the target year
        $sum = DB::table('pay_ins')
            ->join('pay_headers', 'pay_ins.pay_id', '=', 'pay_headers.id')
            ->join('payroll_periods', 'pay_headers.payroll_period_id', '=', 'payroll_periods.id')
            ->where('pay_headers.employee_id', $employee->id)
            ->where('pay_headers.status', 'approved')
            ->whereYear('payroll_periods.period_start', $year)
            ->whereIn('pay_ins.income_type_id', $basicPayTypeIds)
            ->sum(DB::raw('pay_ins.rate * pay_ins.number_input'));

        return (float) $sum;
    }

    /**
     * Compute 13th-month pay details for a specific employee.
     *
     * @param EmployeeMaster $employee
     * @param int $year
     * @return array
     */
    public function computeThirteenthMonthPay(EmployeeMaster $employee, int $year): array
    {
        $totalYtdBasic = $this->calculateYtdBasicPay($employee, $year);
        $monthsEligible = 12.0;

        if ($employee->date_hired) {
            $hireDate = Carbon::parse($employee->date_hired);
            if ($hireDate->year === $year) {
                // Calculate eligible months in target year
                $monthsEligible = 12.0 - $hireDate->month + 1.0;
                if ($hireDate->day > 15) {
                    $monthsEligible -= 0.5;
                }
                $monthsEligible = max(0.0, $monthsEligible);
            } elseif ($hireDate->year > $year) {
                $monthsEligible = 0.0;
            }
        }

        $thirteenthMonthPay = $monthsEligible > 0.0 ? round($totalYtdBasic / 12.0, 2) : 0.00;

        return [
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->full_name,
            'year' => $year,
            'total_ytd_basic_pay' => $totalYtdBasic,
            'months_eligible' => $monthsEligible,
            'thirteenth_month_pay' => $thirteenthMonthPay,
        ];
    }

    /**
     * Compute 13th-month calculations for all active employees in the current tenant company.
     *
     * @param int $year
     * @return array
     */
    public function computeCompanyThirteenthMonth(int $year): array
    {
        $companyId = session('company_id') ?? auth()->user()?->company_id;

        $employeesQuery = EmployeeMaster::where('status', 'active');
        if ($companyId) {
            $employeesQuery->where('company_id', $companyId);
        }

        $employees = $employeesQuery->get();
        $results = [];

        foreach ($employees as $employee) {
            $results[] = $this->computeThirteenthMonthPay($employee, $year);
        }

        return $results;
    }

    /**
     * Compute 13th-month calculations for a bounded period range and optional department.
     *
     * @param int $fromPeriodId
     * @param int $toPeriodId
     * @param string|null $departmentId
     * @return array
     */
    public function computeRangeThirteenthMonth(int $fromPeriodId, int $toPeriodId, ?string $departmentId = null): array
    {
        $fromPeriod = \App\Models\PayrollPeriod::findOrFail($fromPeriodId);
        $toPeriod   = \App\Models\PayrollPeriod::findOrFail($toPeriodId);

        $companyId = session('company_id') ?? auth()->user()?->company_id ?? $fromPeriod->company_id;

        $periods = \App\Models\PayrollPeriod::where('period_start', '>=', $fromPeriod->period_start)
            ->where('period_start', '<=', $toPeriod->period_start)
            ->where('company_id', $companyId)
            ->orderBy('period_start')
            ->get();

        $periodIds = $periods->pluck('id')->toArray();

        $employeesQuery = EmployeeMaster::where('status', 'active')
            ->where('company_id', $companyId);
        if ($departmentId && $departmentId !== 'all') {
            $employeesQuery->where('dept_id', $departmentId);
        }
        $employees = $employeesQuery->orderBy('last_name')->orderBy('first_name')->get();

        $transactions = PayHeader::whereIn('payroll_period_id', $periodIds)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get();

        $txByEmployee = $transactions->groupBy('employee_id');

        $results = [];
        foreach ($employees as $employee) {
            $empTx = $txByEmployee->get($employee->id, collect());
            $totalBasicPay = (float) $empTx->sum('basic_pay');
            if ($totalBasicPay <= 0) {
                continue;
            }

            $thirteenthMonth = round($totalBasicPay / 12.0, 2);

            $results[] = [
                'employee'          => $employee,
                'employee_id'       => $employee->id,
                'employee_code'     => $employee->employee_code,
                'employee_name'     => $employee->full_name,
                'department_name'   => $employee->dept->name ?? $employee->department->name ?? 'N/A',
                'periods_worked'    => $empTx->count(),
                'total_basic_pay'   => $totalBasicPay,
                'total_overtime'    => (float) $empTx->sum('overtime_pay'),
                'total_gross'       => (float) $empTx->sum('gross_income'),
                'thirteenth_month'  => $thirteenthMonth,
            ];
        }

        return [
            'fromPeriod' => $fromPeriod,
            'toPeriod' => $toPeriod,
            'periods' => $periods,
            'results' => $results,
        ];
    }
}
