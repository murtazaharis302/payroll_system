<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Support\Payroll\PayrollTotalsCalculator;
use Illuminate\Database\Eloquent\Collection;

class PayrollRegisterReportService
{
    protected PayrollTotalsCalculator $calculator;

    public function __construct(?PayrollTotalsCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new PayrollTotalsCalculator();
    }

    /**
     * Build report data for Payroll Register PDF/Excel/View.
     *
     * @param PayrollPeriod $period
     * @param Company|null $company
     * @param int|null $companyId
     * @return array
     */
    public function build(PayrollPeriod $period, ?Company $company = null, ?int $companyId = null): array
    {
        $query = PayHeader::with(['employee.dept', 'payIns.incomeType', 'payOuts.deductionType', 'payrollPeriod'])
            ->where('payroll_period_id', $period->id);

        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $payrolls = $query->orderBy('employee_id')->get();

        $rows = [];
        $totals = [
            'basic_pay' => 0.00,
            'overtime_pay' => 0.00,
            'allowances' => 0.00,
            'bonuses' => 0.00,
            'other_income' => 0.00,
            'gross_income' => 0.00,
            'sss_contribution' => 0.00,
            'philhealth_contribution' => 0.00,
            'pagibig_contribution' => 0.00,
            'withholding_tax' => 0.00,
            'sss_loan' => 0.00,
            'pagibig_loan' => 0.00,
            'vale_deduction' => 0.00,
            'goods_deduction' => 0.00,
            'late_deduction' => 0.00,
            'undertime_deduction' => 0.00,
            'other_deductions' => 0.00,
            'total_deductions' => 0.00,
            'net_pay' => 0.00,
        ];

        foreach ($payrolls as $ph) {
            $calculated = $this->calculator->calculate($ph);

            $rowBasic = (float) $ph->basic_pay;
            $rowOt = (float) $ph->overtime_pay;
            $rowAllowances = (float) $ph->total_allowances;
            $rowBonuses = (float) $ph->total_bonuses;
            $rowOther = (float) $ph->other_income;
            $rowGross = (float) $calculated['total_gross_pay'];
            $rowDeductions = (float) $calculated['total_deductions'];
            $rowNet = (float) $calculated['net_pay'];

            $totals['basic_pay'] += $rowBasic;
            $totals['overtime_pay'] += $rowOt;
            $totals['allowances'] += $rowAllowances;
            $totals['bonuses'] += $rowBonuses;
            $totals['other_income'] += $rowOther;
            $totals['gross_income'] += $rowGross;
            $totals['total_deductions'] += $rowDeductions;
            $totals['net_pay'] += $rowNet;

            $rows[] = [
                'payroll' => $ph,
                'employee' => $ph->employee,
                'gross_pay' => $rowGross,
                'total_deductions' => $rowDeductions,
                'net_pay' => $rowNet,
            ];
        }

        return [
            'period' => $period,
            'company' => $company,
            'payrolls' => $payrolls,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
