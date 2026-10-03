<?php

namespace App\Services\Concerns;

use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait PayrollStatutorySyncActions
{
    /**
     * Recalculate income-dependent statutory deductions (Withholding Tax, SSS, PhilHealth, Pag-IBIG)
     * for a draft PayHeader based on updated pay_ins / gross income.
     */
    public function recalculateDraftStatutoryDeductions(PayHeader $payHeader): void
    {
        $period = $payHeader->payrollPeriod;
        $employee = $payHeader->employee;
        if (!$period || !$employee) {
            return;
        }

        $grossPay = (float) PayIn::withoutGlobalScopes()
            ->where('pay_id', $payHeader->id)
            ->sum(DB::raw('rate * number_input'));

        $companyId = $period->company_id;
        $statutoryPeriodFactor = $this->statutoryPeriodFactor($period->frequency);
        $annualPeriods = $this->annualPeriods($period->frequency);

        $payDate = $period->pay_date
            ? Carbon::parse($period->pay_date)->toDateString()
            : Carbon::parse($period->period_end)->toDateString();

        $sssRule = $this->statutoryCalculationService->resolveRule('sss', $payDate);
        $philhealthRule = $this->statutoryCalculationService->resolveRule('philhealth', $payDate);
        $pagibigRule = $this->statutoryCalculationService->resolveRule('pagibig', $payDate);
        $taxRule = $this->statutoryCalculationService->resolveRule('tax', $payDate);

        $monthlySalaryBasis = $this->monthlySalaryBasis($grossPay, $statutoryPeriodFactor, $employee);

        $sssCalc = $this->statutoryCalculationService->calculateSSS($monthlySalaryBasis, $sssRule);
        $periodSssEE = round($sssCalc['monthly_ee'] / $statutoryPeriodFactor, 2);

        $phCalc = $this->statutoryCalculationService->calculatePhilHealth($monthlySalaryBasis, $philhealthRule);
        $periodPhEE = round($phCalc['monthly_ee'] / $statutoryPeriodFactor, 2);

        $pagibigCalc = $this->statutoryCalculationService->calculatePagIbig($monthlySalaryBasis, $pagibigRule);
        $periodPagibigEE = round($pagibigCalc['monthly_ee'] / $statutoryPeriodFactor, 2);

        // Update PayOut lines for SSS, PhilHealth, Pag-IBIG if present and not fixed-zero override
        $this->updateStatutoryPayOut($payHeader, 'sss', $periodSssEE, $companyId);
        $this->updateStatutoryPayOut($payHeader, 'philhealth', $periodPhEE, $companyId);
        $this->updateStatutoryPayOut($payHeader, 'pagibig', $periodPagibigEE, $companyId);

        $taxDeductionType = DeductionType::findBySystemKey('tax', $companyId);

        if ($taxDeductionType) {
            $taxPayOut = PayOut::withoutGlobalScopes()
                ->where('pay_id', $payHeader->id)
                ->where('deduction_type_id', $taxDeductionType->id)
                ->first();

            if ($taxPayOut) {
                $taxableIncome = max(0.00, $grossPay - ($periodSssEE + $periodPhEE + $periodPagibigEE));
                $periodTax = $this->statutoryCalculationService->calculateWithholdingTax($taxableIncome, $annualPeriods, $taxRule);

                PayOut::withoutGlobalScopes()->where('id', $taxPayOut->id)->update([
                    'rate' => $periodTax,
                    'number_input' => 1.00,
                ]);
            }
        }

        $this->syncDraftGovernmentContributions($payHeader);
    }

    protected function updateStatutoryPayOut(PayHeader $payHeader, string $systemKey, float $newRate, ?int $companyId): void
    {
        $dt = DeductionType::findBySystemKey($systemKey, $companyId);

        if ($dt) {
            $po = PayOut::withoutGlobalScopes()
                ->where('pay_id', $payHeader->id)
                ->where('deduction_type_id', $dt->id)
                ->first();

            if ($po) {
                $isFixedOverride = $this->isFixedStatutoryOverride($companyId, $systemKey);

                if (!$isFixedOverride) {
                    PayOut::withoutGlobalScopes()->where('id', $po->id)->update(['rate' => $newRate]);
                }
            }
        }
    }

    /**
     * Synchronize the linked GovernmentContribution records with updated draft salary basis / PayOut lines.
     */
    public function syncDraftGovernmentContributions(PayHeader $payHeader): void
    {
        $period = $payHeader->payrollPeriod;
        $employee = $payHeader->employee;
        if (!$period || !$employee) {
            return;
        }

        $companyId = $period->company_id ?? $employee->company_id;
        $factor = $this->statutoryPeriodFactor($period->frequency);
        $payDate = $period->pay_date
            ? Carbon::parse($period->pay_date)->toDateString()
            : Carbon::parse($period->period_end)->toDateString();

        $grossPay = (float) PayIn::withoutGlobalScopes()
            ->where('pay_id', $payHeader->id)
            ->sum(DB::raw('rate * number_input'));

        $monthlySalaryBasis = $this->monthlySalaryBasis($grossPay, $factor, $employee);

        $statTypes = [
            'sss' => ['calc' => fn($r) => $this->statutoryCalculationService->calculateSSS($monthlySalaryBasis, $r)],
            'philhealth' => ['calc' => fn($r) => $this->statutoryCalculationService->calculatePhilHealth($monthlySalaryBasis, $r)],
            'pagibig' => ['calc' => fn($r) => $this->statutoryCalculationService->calculatePagIbig($monthlySalaryBasis, $r)],
        ];

        foreach ($statTypes as $type => $info) {
            $dt = DeductionType::findBySystemKey($type, $companyId);

            if (!$dt) {
                continue;
            }

            $payOut = PayOut::withoutGlobalScopes()
                ->where('pay_id', $payHeader->id)
                ->where('deduction_type_id', $dt->id)
                ->first();

            if (!$payOut) {
                continue;
            }

            $rule = $this->statutoryCalculationService->resolveRule($type, $payDate);
            $calc = $info['calc']($rule);

            $calcER = round(($calc['monthly_er'] ?? 0) / $factor, 2);
            $calcEC = isset($calc['monthly_ec']) ? round($calc['monthly_ec'] / $factor, 2) : 0.00;

            $isFixedOverride = $this->isFixedStatutoryOverride($companyId, $type);

            $eeShare = (float) ($payOut->rate * ($payOut->number_input ?? 1.00));
            $erShare = $isFixedOverride ? 0.00 : ($calcER + $calcEC);

            $notes = $isFixedOverride
                ? 'Fixed statutory override - ' . ucfirst($type)
                : 'Synchronized with draft #' . $payHeader->id . ' - ' . ucfirst($type) . ' (' . $rule->version_code . ')';

            $existingContrib = GovernmentContribution::withoutGlobalScopes()
                ->where('payroll_draft_id', $payHeader->id)
                ->where('type', $type)
                ->first();

            $status = ($existingContrib && in_array($existingContrib->status, GovernmentContribution::VALID_STATUSES, true))
                ? $existingContrib->status
                : GovernmentContribution::STATUS_PENDING;

            GovernmentContribution::updateOrCreate(
                [
                    'payroll_draft_id' => $payHeader->id,
                    'type' => $type,
                ],
                [
                    'employee_id' => $employee->id,
                    'company_id' => $companyId,
                    'payroll_period_id' => $period->id,
                    'employee_share' => $eeShare,
                    'employer_share' => $erShare,
                    'total_contribution' => $eeShare + $erShare,
                    'period_start' => $period->period_start,
                    'period_end' => $period->period_end,
                    'status' => $status,
                    'notes' => $notes,
                ]
            );
        }
    }

    protected function isFixedStatutoryOverride(\App\Models\Company|int|null $company, string $code): bool
    {
        $companyModel = $company instanceof \App\Models\Company
            ? $company
            : ($company ? \App\Models\Company::find($company) : null);

        if (!$companyModel || empty($companyModel->deduction_defaults)) {
            return false;
        }

        $defaults = $companyModel->deduction_defaults;
        if (empty($defaults['use_fixed_amount'])) {
            return false;
        }

        $deductions = $defaults['deductions'] ?? [];
        if (!is_array($deductions)) {
            return false;
        }

        $codeUpper = strtoupper((string) $code);
        $validKeys = match ($codeUpper) {
            'SSS' => ['sss', 'ssscontribution'],
            'PHILHEALTH', 'PH' => ['philhealth', 'ph', 'philhealthcontribution'],
            'PAGIBIG', 'HDMF' => ['pagibig', 'hdmf', 'pagibigcontribution'],
            default => [strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $code))],
        };

        foreach ($deductions as $d) {
            if (!is_array($d)) continue;
            $typeStr = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', (string)($d['deduction_type'] ?? '')));
            if ($typeStr !== '' && in_array($typeStr, $validKeys, true)) {
                return true;
            }
        }

        return false;
    }

    protected function statutoryPeriodFactor(?string $frequency): float
    {
        return match ($frequency) {
            'monthly' => 1.0,
            'weekly' => 4.0,
            'bi_weekly' => 2.0,
            default => 2.0,
        };
    }

    protected function annualPeriods(?string $frequency): int
    {
        return match ($frequency) {
            'monthly' => 12,
            'weekly' => 52,
            'bi_weekly' => 26,
            default => 24,
        };
    }

    protected function monthlySalaryBasis(float $grossPay, float $factor, EmployeeMaster $employee): float
    {
        if ($grossPay > 0) {
            return $grossPay * $factor;
        }

        if ((float) $employee->monthly_rate > 0) {
            return (float) $employee->monthly_rate;
        }

        if ((float) $employee->daily_rate > 0) {
            return (float) $employee->daily_rate * 26;
        }

        if ((float) $employee->hourly_rate > 0) {
            return (float) $employee->hourly_rate * 8 * 26;
        }

        return 0.00;
    }

    protected function existingStatutoryRate(PayHeader $payHeader, string $systemKey, float $fallback): float
    {
        $line = PayOut::withoutGlobalScopes()
            ->where('pay_id', $payHeader->id)
            ->whereHas('deductionType', fn ($query) => $query->where('system_key', $systemKey))
            ->first();

        return $line ? (float) $line->rate * (float) $line->number_input : $fallback;
    }
}
