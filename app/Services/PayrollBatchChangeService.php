<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PayrollBatchChangeValidationException;
use App\Models\ActivityLog;
use App\Models\DeductionType;
use App\Models\IncomeType;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Concerns\PayrollBatchChangePreflightActions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollBatchChangeService
{
    use PayrollBatchChangePreflightActions;

    public function __construct(
        protected PayrollProcessingService $processingService,
        protected PayrollWorkflowService $workflowService
    ) {}

    /**
     * Retrieve eligible existing PayHeader records for batch changes in a payroll period.
     */
    public function getEligiblePeriodDrafts(PayrollPeriod $period, ?int $companyId, bool $canEditPending): Collection
    {
        $allowedStatuses = $canEditPending
            ? ['draft', 'rejected', 'pending_approval']
            : ['draft', 'rejected'];

        return PayHeader::withoutGlobalScopes()
            ->with(['employee.dept'])
            ->where('payroll_period_id', $period->id)
            ->when($companyId, function ($query, $cid) {
                $query->whereHas('employee', fn ($eq) => $eq->where('company_id', $cid));
            })
            ->whereIn('status', $allowedStatuses)
            ->get()
            ->sortBy(fn (PayHeader $draft) => $draft->employee?->last_name ?? '')
            ->values();
    }

    /**
     * Apply additive Income and/or Deduction batch changes inside a single database transaction.
     *
     * @param array<int> $incomeTypeIds
     * @param array<int, array{number_input?: float|numeric-string|null, rate?: float|numeric-string|null}> $incomeTypeConfigs
     * @param array<int> $deductionTypeIds
     * @param array<int, array{number_input?: float|numeric-string|null, rate?: float|numeric-string|null}> $deductionTypeConfigs
     * @param array<int> $draftIds
     * @return array{updated_count: int, skipped_loan_employees: array<string, array<int, string>>}
     */
    public function applyBatchChange(
        PayrollPeriod $period,
        array $incomeTypeIds,
        array $incomeTypeConfigs,
        array $deductionTypeIds,
        array $deductionTypeConfigs,
        array $draftIds,
        ?User $actor = null
    ): array {
        $actor = $actor ?? auth()->user();
        $isSuperAdmin = ($actor && ($actor->role === 'admin' || $actor->role_id === \App\Models\Role::SUPER_ADMIN));
        $targetCompanyId = (int) $period->company_id;

        if (!$isSuperAdmin && $actor && (int) $actor->company_id !== $targetCompanyId) {
            throw new AuthorizationException('Unauthorized access to cross-company payroll period.');
        }

        $canEditPending = $actor && MenuRoleAccess::hasModuleAccess($actor, 'payroll_locked_edit_override');
        $hasIncomeInputs = $actor && MenuRoleAccess::hasModuleAccess($actor, 'payroll_income_inputs');
        $hasDeductionInputs = $actor && MenuRoleAccess::hasModuleAccess($actor, 'payroll_deduction_inputs');
        $hasRateVisibility = $actor && MenuRoleAccess::hasModuleAccess($actor, 'payroll_income_rate_visibility');

        if (!empty($incomeTypeIds) && !$hasIncomeInputs) {
            throw new AuthorizationException('Unauthorized: user lacks permission for payroll income inputs.');
        }

        if (!empty($deductionTypeIds) && !$hasDeductionInputs) {
            throw new AuthorizationException('Unauthorized: user lacks permission for payroll deduction inputs.');
        }

        return DB::transaction(function () use (
            $period,
            $incomeTypeIds,
            $incomeTypeConfigs,
            $deductionTypeIds,
            $deductionTypeConfigs,
            $draftIds,
            $actor,
            $targetCompanyId,
            $canEditPending,
            $hasRateVisibility
        ) {
            // 1. Deterministic locking in ascending ID order
            $uniqueDraftIds = array_values(array_unique(array_map('intval', $draftIds)));
            sort($uniqueDraftIds);

            /** @var Collection<int, PayHeader> $drafts */
            $drafts = PayHeader::withoutGlobalScopes()
                ->with(['employee.dept', 'payrollPeriod', 'payIns', 'payOuts'])
                ->whereIn('id', $uniqueDraftIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            if ($drafts->count() !== count($uniqueDraftIds)) {
                throw new \InvalidArgumentException('One or more selected payroll drafts could not be found or locked.');
            }

            // 2. Authoritative in-transaction preflight
            $blockingErrors = [];
            $incomeTypes = collect();
            $deductionTypes = collect();

            if (!empty($incomeTypeIds)) {
                $incomeTypes = $this->resolveAndValidateTypes('income', $incomeTypeIds, $targetCompanyId, $incomeTypeConfigs, $hasRateVisibility, $blockingErrors);
            }

            if (!empty($deductionTypeIds)) {
                $deductionTypes = $this->resolveAndValidateTypes('deduction', $deductionTypeIds, $targetCompanyId, $deductionTypeConfigs, true, $blockingErrors);
            }

            $skippedLoanEmployees = []; // [type_name => [empIdentity]]

            foreach ($drafts as $draft) {
                $employee = $draft->employee;
                $empCode = $employee?->employee_code ?: 'EMP-' . ($draft->employee_id ?? $draft->id);
                $empName = trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? '')) ?: ($employee?->full_name ?? 'Employee');
                $empIdentity = "{$empCode} - {$empName}";

                // Verify company isolation
                $draftCompanyId = (int) ($employee?->company_id ?? 0);
                if ($draftCompanyId !== $targetCompanyId) {
                    $blockingErrors[] = "{$empIdentity} — Unauthorized: payroll draft belongs to another company";
                    continue;
                }

                // Verify department validity
                $dept = $employee?->dept;
                if (!$dept || (int) $dept->company_id !== $targetCompanyId) {
                    $blockingErrors[] = "{$empIdentity} — Department: no valid assigned department for this company";
                }

                // Verify status eligibility
                $status = (string) ($draft->getRawOriginal('status') ?? $draft->status);
                if (in_array($status, ['approved', 'posted', 'paid', 'voided'], true)) {
                    $blockingErrors[] = "{$empIdentity} — Status: record is finalized ('{$status}') and cannot be modified";
                    continue;
                }

                if ($status === 'pending_approval' && !$canEditPending) {
                    throw new AuthorizationException("Unauthorized: {$empIdentity} is pending approval and requires locked edit override permission.");
                }

                if ($incomeTypes->isNotEmpty()) {
                    $this->validateDraftIncomeAdditions($draft, $incomeTypes, $incomeTypeConfigs, $empIdentity, $blockingErrors);
                }

                if ($deductionTypes->isNotEmpty()) {
                    $this->validateDraftDeductionAdditions(
                        $draft,
                        $period,
                        $deductionTypes,
                        $deductionTypeConfigs,
                        $empIdentity,
                        $blockingErrors,
                        $skippedLoanEmployees
                    );
                }
            }

            if (!empty($blockingErrors)) {
                throw new PayrollBatchChangeValidationException($blockingErrors);
            }

            // 3. Apply line additions and single recalculation per draft
            foreach ($drafts as $draft) {
                $hasIncomeAddition = $incomeTypes->isNotEmpty();
                $hasStatutoryDeductionAddition = false;

                // Add Income Lines
                if ($hasIncomeAddition) {
                    foreach ($incomeTypes as $incomeType) {
                        $config = $incomeTypeConfigs[$incomeType->id] ?? [];
                        $qty = isset($config['number_input']) && is_numeric($config['number_input']) ? (float) $config['number_input'] : 1.00;
                        $rateOverride = ($hasRateVisibility && isset($config['rate']) && is_numeric($config['rate'])) ? (float) $config['rate'] : null;

                        $line = $this->processingService->resolveAddedIncomeLine($draft, $incomeType, $qty, $rateOverride);

                        PayIn::create([
                            'pay_id' => $draft->id,
                            'income_type_id' => $incomeType->id,
                            'number_input' => $line['number_input'],
                            'rate' => $line['rate'],
                        ]);
                    }
                }

                // Add Deduction Lines
                if ($deductionTypes->isNotEmpty()) {
                    foreach ($deductionTypes as $deductionType) {
                        $config = $deductionTypeConfigs[$deductionType->id] ?? [];
                        $qty = isset($config['number_input']) && is_numeric($config['number_input']) ? (float) $config['number_input'] : 1.00;
                        $rateOverride = (isset($config['rate']) && is_numeric($config['rate'])) ? (float) $config['rate'] : null;

                        if ($this->isLoanDeductionType($deductionType)) {
                            $activeLoans = $this->getEligibleActiveLoans($draft->employee_id, $deductionType->id);
                            if ($activeLoans->isNotEmpty()) {
                                foreach ($activeLoans as $loan) {
                                    $line = $this->processingService->resolveAddedDraftDeductionLine(
                                        $draft,
                                        $deductionType,
                                        $loan,
                                        1.00,
                                        $rateOverride
                                    );

                                    PayOut::create([
                                        'pay_id' => $draft->id,
                                        'deduction_type_id' => $deductionType->id,
                                        'loan_id' => $loan->id,
                                        'number_input' => 1.00,
                                        'rate' => $line['rate'],
                                    ]);
                                }
                            } else {
                                $this->appendDraftReviewReason($draft, "Skipped loan deduction '{$deductionType->name}': no active loan found.");
                            }
                        } else {
                            $codeUpper = strtoupper((string) ($deductionType->code ?? ''));
                            if (in_array($codeUpper, ['SSS', 'PHILHEALTH', 'PAGIBIG'], true)) {
                                $hasStatutoryDeductionAddition = true;
                            }

                            $line = $this->processingService->resolveAddedDraftDeductionLine(
                                $draft,
                                $deductionType,
                                null,
                                $qty,
                                $rateOverride
                            );

                            PayOut::create([
                                'pay_id' => $draft->id,
                                'deduction_type_id' => $deductionType->id,
                                'loan_id' => null,
                                'number_input' => $line['number_input'],
                                'rate' => $line['rate'],
                            ]);
                        }
                    }
                }

                // Recalculate statutory lines & totals once per draft
                if ($hasIncomeAddition) {
                    $this->processingService->recalculateDraftStatutoryDeductions($draft);
                    $draft->recalculateTotals();
                } elseif ($hasStatutoryDeductionAddition) {
                    $this->processingService->syncDraftGovernmentContributions($draft);
                    $draft->recalculateTotals();
                } else {
                    $draft->recalculateTotals();
                }

                // 4. Scoped workflow transition back to draft with single lock_version bump
                $this->workflowService->returnToDraftForBatchChange($draft, $actor);
            }

            // 5. Audit logging
            $allLabels = [];
            if ($incomeTypes->isNotEmpty()) {
                $allLabels[] = 'Income: [' . $incomeTypes->map(fn ($t) => $t->code ?: $t->name)->implode(', ') . ']';
            }
            if ($deductionTypes->isNotEmpty()) {
                $allLabels[] = 'Deductions: [' . $deductionTypes->map(fn ($t) => $t->code ?: $t->name)->implode(', ') . ']';
            }
            $typeSummary = implode('; ', $allLabels);

            ActivityLog::log(
                'batch_change',
                'payroll_draft',
                "Batch added {$typeSummary} to {$drafts->count()} payroll draft(s) for period {$period->period_name}",
                PayHeader::class,
                null,
                null,
                null,
                $targetCompanyId,
                $actor?->id
            );

            return [
                'updated_count' => $drafts->count(),
                'skipped_loan_employees' => $skippedLoanEmployees,
            ];
        });
    }

    /**
     * Append to draft review reason without overwriting existing reasons.
     */
    protected function appendDraftReviewReason(PayHeader $draft, string $message): void
    {
        $existing = array_filter(array_map('trim', explode(';', (string) $draft->review_reason)));
        if (!in_array($message, $existing, true)) {
            $existing[] = $message;
            $draft->review_reason = implode('; ', $existing);
        }
        $draft->requires_review = true;
        $draft->save();
    }
}
