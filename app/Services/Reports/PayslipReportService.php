<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Support\Payroll\PayrollTotalsCalculator;

class PayslipReportService
{
    protected PayrollTotalsCalculator $calculator;

    public function __construct(?PayrollTotalsCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new PayrollTotalsCalculator();
    }

    /**
     * Build single payslip data.
     *
     * @param int $payrollPeriodId
     * @param int $employeeId
     * @param int|null $companyId
     * @return array|null
     */
    public function buildSingle(int $payrollPeriodId, int $employeeId, ?int $companyId = null): ?array
    {
        $query = PayHeader::with(['payIns.incomeType', 'payOuts.deductionType', 'employee', 'payrollPeriod'])
            ->where('payroll_period_id', $payrollPeriodId)
            ->where('employee_id', $employeeId);

        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $payHeader = $query->first();
        if (!$payHeader) {
            return null;
        }

        $company = Company::find($companyId) ?? $payHeader->payrollPeriod->company ?? Company::first();
        $calc = $this->calculator->calculate($payHeader);

        return [
            'payHeader' => $payHeader,
            'company' => $company,
            'totals' => $calc,
        ];
    }
}
