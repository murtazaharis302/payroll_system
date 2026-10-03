<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Support\Payroll\PayrollTotalsCalculator;

class IncomeHistoryReportService
{
    protected PayrollTotalsCalculator $calculator;

    public function __construct(?PayrollTotalsCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new PayrollTotalsCalculator();
    }

    /**
     * Build income history data reading historical PayHeader and PayIn lines only.
     *
     * @param PayrollPeriod $period
     * @param Company|null $company
     * @param int|null $companyId
     * @param array $options
     * @return array
     */
    public function build(PayrollPeriod $period, ?Company $company = null, ?int $companyId = null, array $options = []): array
    {
        $query = PayHeader::with(['employee.dept', 'payIns.incomeType'])
            ->where('payroll_period_id', $period->id);

        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        if (!empty($options['department_id']) && $options['department_id'] !== 'all') {
            $deptId = $options['department_id'];
            $query->whereHas('employee', function ($q) use ($deptId) {
                $q->where('dept_id', $deptId);
            });
        }

        $payHeaders = $query->get();
        $grandTotalIncome = 0.00;
        $reportData = [];

        foreach ($payHeaders as $ph) {
            $calc = $this->calculator->calculate($ph);
            $totalIncome = $calc['total_gross_pay'];
            $grandTotalIncome += $totalIncome;

            $reportData[] = [
                'payroll' => $ph,
                'employee' => $ph->employee,
                'total_income' => $totalIncome,
            ];
        }

        return [
            'period' => $period,
            'company' => $company,
            'payHeaders' => $payHeaders,
            'reportData' => $reportData,
            'grandTotalIncome' => $grandTotalIncome,
        ];
    }
}
