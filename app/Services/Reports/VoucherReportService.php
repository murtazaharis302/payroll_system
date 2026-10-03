<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Support\Payroll\PayrollTotalsCalculator;

class VoucherReportService
{
    protected PayrollTotalsCalculator $calculator;

    public function __construct(?PayrollTotalsCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new PayrollTotalsCalculator();
    }

    /**
     * Build voucher report data.
     *
     * @param PayrollPeriod $period
     * @param Company|null $company
     * @param int|null $companyId
     * @param array $options
     * @return array
     */
    public function build(PayrollPeriod $period, ?Company $company = null, ?int $companyId = null, array $options = []): array
    {
        $query = PayHeader::with(['employee.dept', 'payIns.incomeType', 'payrollPeriod'])
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

        $payrolls = $query->orderBy('employee_id')->get();
        $selectedIncomeTypeIds = array_map('intval', (array) ($options['income_types'] ?? []));
        $includeZero = (bool) ($options['include_zero'] ?? false);

        $selectedIncomeTypes = !empty($selectedIncomeTypeIds)
            ? IncomeType::withoutGlobalScopes()->whereIn('id', $selectedIncomeTypeIds)->get()->keyBy('id')
            : IncomeType::withoutGlobalScopes()->where('is_active', true)->get()->keyBy('id');

        $voucherRows = [];
        $totalVoucherAmount = 0.00;

        foreach ($payrolls as $ph) {
            $empParticulars = [];
            $empGross = 0.00;
            $includedTypeIds = [];

            foreach ($ph->payIns as $pi) {
                $typeId = (int) $pi->income_type_id;
                $val = (float) $pi->rate * (float) $pi->number_input;

                if (!empty($selectedIncomeTypeIds) && !in_array($typeId, $selectedIncomeTypeIds, true)) {
                    continue;
                }

                $includedTypeIds[] = $typeId;

                if (!$includeZero && abs($val) < 0.00001) {
                    continue;
                }

                $typeName = $pi->incomeType->name ?? 'Earnings';
                $empParticulars[] = [
                    'name' => $typeName,
                    'amount' => $val,
                ];
                $empGross += $val;
            }

            if ($includeZero && $selectedIncomeTypes->isNotEmpty()) {
                foreach ($selectedIncomeTypes as $typeId => $incomeType) {
                    if (in_array((int) $typeId, $includedTypeIds, true)) {
                        continue;
                    }

                    $empParticulars[] = [
                        'name' => $incomeType->name,
                        'amount' => 0.00,
                    ];
                }
            }

            if ($ph->payIns->count() === 0) {
                $calc = $this->calculator->calculate($ph);
                $empGross = $calc['total_gross_pay'];
            }

            $ph->voucher_particulars = $empParticulars;
            $ph->voucher_total_amount = $empGross;
            $ph->voucher_amount_words = \App\Services\NumberToWordsHelper::formatPesos($empGross);

            $totalVoucherAmount += $empGross;
            $voucherRows[] = [
                'payroll' => $ph,
                'employee' => $ph->employee,
                'particulars' => $empParticulars,
                'total' => $empGross,
            ];
        }

        return [
            'period' => $period,
            'company' => $company,
            'payrolls' => $payrolls,
            'voucherRows' => $voucherRows,
            'totalVoucherAmount' => $totalVoucherAmount,
        ];
    }
}
