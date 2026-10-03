<?php

use App\Exceptions\PayrollGenerationSetupException;
use App\Models\DeductionType;
use App\Models\EmpDeduction;
use App\Models\EmpIncome;
use App\Models\EmpLoan;
use App\Models\IncomeType;
use App\Models\LoanType;

$blockingErrors = [];

// 1. Department validity check across all targeted employees
foreach ($employees as $employee) {
    $empCode = $employee->employee_code ?: 'EMP-' . $employee->id;
    $empName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: ($employee->full_name ?? 'Employee #' . $employee->id);
    $empIdentity = "{$empCode} {$empName}";

    $deptValid = $employee->dept_id !== null &&
                 $employee->dept !== null &&
                 (int) $employee->dept->company_id === (int) $companyId;

    if (!$deptValid) {
        $blockingErrors[] = "{$empIdentity} — Department: no valid assigned department for this company";
    }
}

// 2. Active loan mappings check across all targeted employees
foreach ($employees as $employee) {
    $empCode = $employee->employee_code ?: 'EMP-' . $employee->id;
    $empName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: ($employee->full_name ?? 'Employee #' . $employee->id);
    $empIdentity = "{$empCode} {$empName}";

    $activeLoans = EmpLoan::withoutGlobalScopes()
        ->with(['loanType' => function ($q) { $q->withoutGlobalScopes(); }])
        ->where('employee_id', $employee->id)
        ->where('current_balance', '>', 0)
        ->orderBy('approved_at', 'asc')
        ->orderBy('id', 'asc')
        ->get();

    foreach ($activeLoans as $loan) {
        $loanType = $loan->loanType;
        if (!$loanType || empty($loanType->deduction_type_id)) {
            $typeName = $loanType?->name ?? 'Unknown Loan Type';
            $blockingErrors[] = "{$empIdentity} — Loan '{$typeName}': not mapped to a Deduction Type";
            continue;
        }

        $deductionType = DeductionType::withoutGlobalScopes()->find($loanType->deduction_type_id);
        if (!$deductionType) {
            $typeName = $loanType->name;
            $blockingErrors[] = "{$empIdentity} — Loan '{$typeName}': references non-existent Deduction Type ID {$loanType->deduction_type_id}";
        }
    }
}

// 3. Income types validation across company scope and preflight checks
if (!empty($rawIncomeOptions)) {
    $selectedIncomeItems = [];
    foreach ($rawIncomeOptions as $k => $incItem) {
        if (!is_array($incItem)) {
            continue;
        }
        $incTypeId = $incItem['income_type_id'] ?? $incItem['income_type'] ?? (is_numeric($k) ? $k : null);
        if ($incTypeId === null || $incTypeId === '') {
            continue;
        }

        $isDisplayed = isset($incItem['display']) ? (bool) $incItem['display'] : true;
        if (!$isDisplayed) {
            continue;
        }

        $matchedType = IncomeType::withoutGlobalScopes()
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where('is_active', true)
            ->where(function ($q) use ($incTypeId) {
                if (is_numeric($incTypeId)) {
                    $q->where('id', (int) $incTypeId);
                } else {
                    $q->where('code', (string) $incTypeId)
                      ->orWhere('code', strtoupper((string) $incTypeId));
                }
            })
            ->orderByRaw('company_id IS NOT NULL DESC')
            ->first();

        if (!$matchedType) {
            $blockingErrors[] = "Income Type [{$incTypeId}]: invalid, inactive, or not authorized for this company";
            continue;
        }

        if ($matchedType->system_key === 'overtime') {
            continue;
        }

        $opt = isset($getIncomeOption) ? $getIncomeOption($matchedType->system_key, $matchedType->code, $matchedType->name, $matchedType->id) : [];
        $isItemFixed = (isset($incItem['mode']) && in_array($incItem['mode'], ['fixed', 'manual'], true)) || $useFixedIncome;
        $hasFixedAmount = (isset($opt['amount']) && is_numeric($opt['amount']) && (float) $opt['amount'] > 0)
            || (isset($incItem['amount']) && is_numeric($incItem['amount']) && (float) $incItem['amount'] > 0);

        if ($isItemFixed) {
            $selectedIncomeItems[] = [
                'type' => $matchedType,
                'label' => $matchedType->code ?: ($matchedType->name ?: (string) $incTypeId),
                'has_fixed_amount' => $hasFixedAmount,
            ];
        }
    }

    if (!empty($selectedIncomeItems)) {
        foreach ($employees as $employee) {
            $empCode = $employee->employee_code ?: 'EMP-' . $employee->id;
            $empName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: ($employee->full_name ?? 'Employee #' . $employee->id);
            $empIdentity = "{$empCode} {$empName}";

            $empIncomes = EmpIncome::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->get();

            foreach ($selectedIncomeItems as $selItem) {
                $matchedType = $selItem['type'];
                $incomeLabel = $selItem['label'];
                $hasFixedAmount = $selItem['has_fixed_amount'];

                if ($hasFixedAmount) {
                    continue; // Manual fixed amount provided, no EmpIncome required
                }

                $empInc = $empIncomes->first(function ($ei) use ($matchedType) {
                    return (int) $ei->income_type_id === (int) $matchedType->id;
                });

                if (!$empInc || (float) ($empInc->rate ?? $empInc->amount ?? 0) <= 0) {
                    $blockingErrors[] = "{$empIdentity} — {$incomeLabel}: no active Recurring Income rate and no manual amount provided";
                }
            }
        }
    }
}

// 4. Automated / Assigned mode: selected deductions preflight validation across all targeted employees
if (!$useFixedAmount && !empty($rawDeductionOptions)) {
    $selectedDeductionItems = [];
    foreach ($rawDeductionOptions as $decItem) {
        if (!is_array($decItem) || empty($decItem['deduction_type'])) {
            continue;
        }
        $isDisplayed = isset($decItem['display']) ? (bool)$decItem['display'] : true;
        if (!$isDisplayed) {
            continue;
        }

        $decTypeStr = (string)$decItem['deduction_type'];
        $matchedType = DeductionType::withoutGlobalScopes()
            ->where(function ($q) use ($companyId, $decTypeStr) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where(function ($q) use ($decTypeStr) {
                $q->where('code', $decTypeStr)
                  ->orWhere('id', is_numeric($decTypeStr) ? (int)$decTypeStr : 0);
            })
            ->orderByRaw('company_id IS NOT NULL DESC')
            ->first();

        if (!$matchedType) {
            $matchedType = DeductionType::withoutGlobalScopes()
                ->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)->orWhereNull('company_id');
                })
                ->where('code', strtoupper($decTypeStr))
                ->orderByRaw('company_id IS NOT NULL DESC')
                ->first();
        }

        if (!$matchedType) {
            continue;
        }

        // Skip statutory deductions (calculated by statutory rules)
        if (in_array($matchedType->system_key, ['sss', 'philhealth', 'pagibig', 'tax'], true)) {
            continue;
        }

        $isLoanType = $matchedType->isLoanBacked();

        // Loan-backed deductions are applied dynamically to employees with active loans; missing loans are simply skipped
        if ($isLoanType) {
            continue;
        }

        $selectedDeductionItems[] = [
            'type' => $matchedType,
            'label' => $matchedType->code ?: ($matchedType->name ?: $decTypeStr),
        ];
    }

    if (!empty($selectedDeductionItems)) {
        foreach ($employees as $employee) {
            $empCode = $employee->employee_code ?: 'EMP-' . $employee->id;
            $empName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: ($employee->full_name ?? 'Employee #' . $employee->id);
            $empIdentity = "{$empCode} {$empName}";

            $empDeductions = EmpDeduction::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->get();

            foreach ($selectedDeductionItems as $selItem) {
                $matchedType = $selItem['type'];
                $deductionLabel = $selItem['label'];

                $empDed = $empDeductions->first(function ($ed) use ($matchedType) {
                    return (int)$ed->deduction_type_id === (int)$matchedType->id;
                });

                if (!$empDed || (float)($empDed->rate ?? 0) <= 0) {
                    $blockingErrors[] = "{$empIdentity} — {$deductionLabel}: no active Recurring Deduction assignment/rate";
                }
            }
        }
    }
}

if (!empty($blockingErrors)) {
    throw new PayrollGenerationSetupException($blockingErrors);
}
