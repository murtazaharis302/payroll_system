<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\DeductionType;
use App\Models\EmpDeduction;
use App\Models\EmpIncome;
use App\Models\EmpLoan;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

trait PayrollBatchChangePreflightActions
{
    /**
     * Resolve and validate type definitions for batch changes.
     */
    protected function resolveAndValidateTypes(
        string $mode,
        array $typeIds,
        int $companyId,
        array $typeConfigs,
        bool $hasRateVisibility,
        array &$blockingErrors
    ): Collection {
        $modelClass = $mode === 'income' ? IncomeType::class : DeductionType::class;

        $types = $modelClass::withoutGlobalScopes()
            ->whereIn('id', $typeIds)
            ->where('is_active', true)
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                      ->orWhereNull('company_id');
            })
            ->get();



        $selectedSet = array_map('intval', $typeIds);
        if ($types->count() !== count($selectedSet)) {
            $foundIds = $types->pluck('id')->map(fn ($id) => (int) $id)->all();
            $missing = array_diff($selectedSet, $foundIds);
            $blockingErrors[] = 'One or more selected ' . ucfirst($mode) . ' types are invalid, inactive, or not accessible for this company: [' . implode(', ', $missing) . '].';
        }

        foreach (array_keys($typeConfigs) as $cfgTypeId) {
            if (!in_array((int) $cfgTypeId, $selectedSet, true)) {
                $blockingErrors[] = "Invalid configuration: type config contains unselected or invalid type ID: {$cfgTypeId}.";
            }
        }

        if (!$hasRateVisibility && $mode === 'income') {
            foreach ($typeConfigs as $cfg) {
                if (is_array($cfg) && isset($cfg['rate']) && is_numeric($cfg['rate'])) {
                    $blockingErrors[] = 'User is restricted from submitting manual monetary income rates.';
                    break;
                }
            }
        }

        if ($mode === 'deduction') {
            foreach ($types as $type) {
                if ($this->isLoanDeductionType($type)) {
                    $cfg = $typeConfigs[$type->id] ?? [];
                    if (is_array($cfg) && isset($cfg['rate']) && $cfg['rate'] !== '' && $cfg['rate'] !== null) {
                        $blockingErrors[] = "Invalid configuration: manual rate/amount is not allowed for loan-backed deduction '{$type->name}'.";
                    }
                }
            }
        }

        return $types;
    }

    /**
     * Validate income line additions for a specific draft.
     */
    protected function validateDraftIncomeAdditions(
        PayHeader $draft,
        Collection $types,
        array $typeConfigs,
        string $empIdentity,
        array &$blockingErrors
    ): void {
        $existingIncomeTypeIds = $draft->payIns->pluck('income_type_id')->map(fn ($id) => (int) $id)->all();

        foreach ($types as $type) {
            $label = $type->code ?: $type->name;

            if (in_array((int) $type->id, $existingIncomeTypeIds, true)) {
                $blockingErrors[] = "{$empIdentity} — {$label}: already exists on payroll draft";
                continue;
            }

            $cfg = $typeConfigs[$type->id] ?? [];
            $hasManualRate = isset($cfg['rate']) && is_numeric($cfg['rate']) && (float) $cfg['rate'] > 0;

            if (!$hasManualRate && $type->system_key !== 'overtime') {
                $empInc = EmpIncome::withoutGlobalScopes()
                    ->where('employee_id', $draft->employee_id)
                    ->where('income_type_id', $type->id)
                    ->where('is_active', true)
                    ->first();

                if (!$empInc || (float) ($empInc->rate ?? $empInc->amount ?? 0) <= 0) {
                    $blockingErrors[] = "{$empIdentity} — {$label}: no active Recurring Income rate configured";
                }
            }
        }
    }

    /**
     * Validate deduction line additions for a specific draft.
     */
    protected function validateDraftDeductionAdditions(
        PayHeader $draft,
        PayrollPeriod $period,
        Collection $types,
        array $typeConfigs,
        string $empIdentity,
        array &$blockingErrors,
        array &$skippedLoanEmployees
    ): void {
        $existingOrdinaryDeductionTypeIds = $draft->payOuts->whereNull('loan_id')->pluck('deduction_type_id')->map(fn ($id) => (int) $id)->all();
        $existingLoanIds = $draft->payOuts->whereNotNull('loan_id')->pluck('loan_id')->map(fn ($id) => (int) $id)->all();

        // Check active loan mappings integrity across this employee
        $allActiveEmpLoans = EmpLoan::withoutGlobalScopes()
            ->with(['loanType' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('employee_id', $draft->employee_id)
            ->where('current_balance', '>', 0)
            ->get();

        foreach ($allActiveEmpLoans as $loan) {
            if (!$loan->loanType || empty($loan->loanType->deduction_type_id)) {
                $typeName = $loan->loanType?->name ?? 'Unknown Loan Type';
                $blockingErrors[] = "{$empIdentity} — Loan '{$typeName}': not mapped to a Deduction Type";
            }
        }

        foreach ($types as $type) {
            $label = $type->code ?: $type->name;

            if ($this->isLoanDeductionType($type)) {
                $activeLoans = $this->getEligibleActiveLoans($draft->employee_id, $type->id);

                if ($activeLoans->isEmpty()) {
                    $skippedLoanEmployees[$label][] = $empIdentity;
                } else {
                    foreach ($activeLoans as $loan) {
                        if (in_array((int) $loan->id, $existingLoanIds, true)) {
                            $loanTypeName = $loan->loanType?->name ?? $label;
                            $blockingErrors[] = "{$empIdentity} — Loan '{$loanTypeName}': already exists on payroll draft";
                        }
                    }
                }
            } else {
                if (in_array((int) $type->id, $existingOrdinaryDeductionTypeIds, true)) {
                    $blockingErrors[] = "{$empIdentity} — {$label}: already exists on payroll draft";
                    continue;
                }

                if (in_array($type->system_key, ['sss', 'philhealth', 'pagibig', 'tax'], true)) {
                    continue;
                }

                $cfg = $typeConfigs[$type->id] ?? [];
                $hasFixedRate = isset($cfg['rate']) && is_numeric($cfg['rate']) && (float) $cfg['rate'] > 0;

                if (!$hasFixedRate) {
                    $empDed = EmpDeduction::withoutGlobalScopes()
                        ->where('employee_id', $draft->employee_id)
                        ->where('deduction_type_id', $type->id)
                        ->where('is_active', true)
                        ->where(function ($query) use ($period) {
                            $query->whereNull('start_date')
                                  ->orWhere('start_date', '<=', $period->period_end);
                        })
                        ->where(function ($query) use ($period) {
                            $query->whereNull('end_date')
                                  ->orWhere('end_date', '>=', $period->period_start);
                        })
                        ->first();

                    if (!$empDed || (float) ($empDed->rate ?? 0) <= 0) {
                        $blockingErrors[] = "{$empIdentity} — {$label}: no active Recurring Deduction assignment/rate";
                    }
                }
            }
        }
    }

    /**
     * Check if a deduction type is backed by loans across the system.
     */
    protected function isLoanDeductionType(DeductionType $type): bool
    {
        return $type->isLoanBacked();
    }

    /**
     * Get all active eligible loans for an employee and deduction type.
     */
    protected function getEligibleActiveLoans(int $employeeId, int $deductionTypeId): Collection
    {
        return EmpLoan::withoutGlobalScopes()
            ->with(['loanType.deductionType'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['ACTIVE', 'active'])
            ->where('current_balance', '>', 0)
            ->whereHas('loanType', fn ($query) => $query->where('deduction_type_id', $deductionTypeId)->where('is_active', true))
            ->orderBy('id', 'asc')
            ->get();
    }
}
