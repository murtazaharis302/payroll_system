<?php

use App\Models\DeductionType;
use App\Models\EmpDeduction;
use App\Models\EmpLoan;
use App\Models\LoanPayment;
use App\Models\PayOut;

                // 7. Active Loans Repayments (emp_loans)
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
                        $typeId = $loanType?->id ?? 'null';
                        throw new \InvalidArgumentException("Unmapped Loan Type: Loan Type '{$typeName}' (ID: {$typeId}) is not mapped to a Deduction Type. Please configure a mapped Deduction Type in Loan Setup before processing payroll.");
                    }

                    $deductionType = DeductionType::withoutGlobalScopes()->find($loanType->deduction_type_id);
                    if (!$deductionType) {
                        $typeName = $loanType->name;
                        throw new \InvalidArgumentException("Unmapped Loan Type: Loan Type '{$typeName}' (ID: {$loanType->id}) references non-existent Deduction Type ID {$loanType->deduction_type_id}.");
                    }

                    $loanOpt = $getDeductionOption($deductionType->system_key, $deductionType->code, $deductionType->name, $deductionType->id);
                    if ($loanOpt['display'] === false) {
                        // Skip excluded loan deduction
                        continue;
                    }

                    $initRate = ($useFixedAmount && isset($loanOpt['amount']) && is_numeric($loanOpt['amount']) && (float)$loanOpt['amount'] > 0)
                        ? min((float)$loanOpt['amount'], (float)$loan->current_balance)
                        : min((float)$loan->per_cutoff_amount, (float)$loan->current_balance);

                    $payOuts[] = PayOut::create([
                        'pay_id' => $payHeader->id,
                        'deduction_type_id' => $deductionType->id,
                        'loan_id' => $loan->id,
                        'number_input' => 1.00,
                        'rate' => $initRate,
                    ]);

                    $processedLoanDeductionTypeIds[] = $deductionType->id;
                }

                // 8. Recurring Deductions (emp_deductions)
                $recurringDeductions = EmpDeduction::where('employee_id', $employee->id)
                    ->where('is_active', true)
                    ->where(function($query) use ($period) {
                        $query->whereNull('start_date')
                              ->orWhere('start_date', '<=', $period->period_end);
                    })
                    ->where(function($query) use ($period) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>=', $period->period_start);
                    })
                    ->get();

                foreach ($recurringDeductions as $deduction) {
                    // Skip if this deduction type was already processed as a loan payment
                    if (in_array($deduction->deduction_type_id, $processedLoanDeductionTypeIds, true)) {
                        continue;
                    }

                    // Also skip statutory types (handled separately below)
                    if (in_array($deduction->deductionType?->system_key, ['sss', 'philhealth', 'pagibig', 'tax'], true)) {
                        continue;
                    }

                    $recOpt = $getDeductionOption($deduction->deductionType?->system_key, $deduction->deductionType?->code, $deduction->deductionType?->name, $deduction->deduction_type_id);
                    if ($recOpt['display'] === false) {
                        // Skip excluded recurring deduction
                        continue;
                    }

                    if ($useFixedAmount && $recOpt['has_config'] && (float)($recOpt['amount'] ?? 0) > 0) {
                        $rate = (float) $recOpt['amount'];
                    } else {
                        $rate = (float) $deduction->rate;
                        if ($deduction->frequency === 'monthly') {
                            $rate = $rate / $statutoryPeriodFactor;
                        }
                    }

                    $payOuts[] = PayOut::create([
                        'pay_id' => $payHeader->id,
                        'deduction_type_id' => $deduction->deduction_type_id,
                        'number_input' => 1.00,
                        'rate' => $rate,
                    ]);

                    $processedLoanDeductionTypeIds[] = $deduction->deduction_type_id;
                }

                // 8.1 Selected Non-Statutory Deductions (Deterministic Catch-All)
                if (!empty($rawDeductionOptions) && is_array($rawDeductionOptions)) {
                    foreach ($rawDeductionOptions as $decItem) {
                        if (!is_array($decItem)) continue;
                        $decDisplay = isset($decItem['display']) ? (bool)$decItem['display'] : true;
                        if (!$decDisplay) continue;

                        $decTypeStr = (string) ($decItem['deduction_type'] ?? '');
                        if ($decTypeStr === '') continue;

                        // Find matching DeductionType in DB (by code or ID) scoped to company/global
                        $dt = DeductionType::withoutGlobalScopes()
                            ->where(function ($q) use ($companyId) {
                                $q->where('company_id', $companyId)->orWhereNull('company_id');
                            })
                            ->where(function ($q) use ($decTypeStr) {
                                $q->where('code', $decTypeStr)
                                  ->orWhere('id', is_numeric($decTypeStr) ? (int)$decTypeStr : 0);
                            })
                            ->first();

                        if (!$dt) {
                            $dt = DeductionType::withoutGlobalScopes()
                                ->where(function ($q) use ($companyId) {
                                    $q->where('company_id', $companyId)->orWhereNull('company_id');
                                })
                                ->where('code', strtoupper($decTypeStr))
                                ->first();
                        }

                        if (!$dt) continue;

                        // Skip statutory deductions (handled separately in Step 9)
                        if (in_array($dt->system_key, ['sss', 'philhealth', 'pagibig', 'tax'], true)) {
                            continue;
                        }

                        // Skip if this deduction type was already created (e.g. via active loan or recurring deduction)
                        if (in_array($dt->id, $processedLoanDeductionTypeIds, true)) {
                            continue;
                        }

                        $opt = $getDeductionOption($dt->system_key, $dt->code, $dt->name, $dt->id);
                        $isLoanType = $dt->isLoanBacked();

                        if ($isLoanType) {
                            // Check if employee has active loan for this deduction type
                            $matchingActiveLoan = EmpLoan::withoutGlobalScopes()
                                ->where('employee_id', $employee->id)
                                ->where('current_balance', '>', 0)
                                ->whereHas('loanType', function ($lq) use ($dt) {
                                    $lq->withoutGlobalScopes();
                                    $lq->where('deduction_type_id', $dt->id);
                                })
                                ->first();

                            if (!$matchingActiveLoan) {
                                // Block/skip loan deduction without active loan and record explicit reason
                                $requiresReview = true;
                                $skippedLoanDeductionNames[] = $dt->name;
                                continue;
                            }

                            $cappedRate = ($useFixedAmount && isset($opt['amount']) && is_numeric($opt['amount']) && (float)$opt['amount'] > 0)
                                ? min((float)$opt['amount'], (float)$matchingActiveLoan->current_balance)
                                : min((float)$matchingActiveLoan->per_cutoff_amount, (float)$matchingActiveLoan->current_balance);

                            $payOuts[] = PayOut::create([
                                'pay_id' => $payHeader->id,
                                'deduction_type_id' => $dt->id,
                                'loan_id' => $matchingActiveLoan->id,
                                'number_input' => 1.00,
                                'rate' => $cappedRate,
                            ]);
                            $processedLoanDeductionTypeIds[] = $dt->id;
                        } else {
                            $initRate = ($useFixedAmount && isset($opt['amount']) && is_numeric($opt['amount']) && (float) $opt['amount'] > 0)
                                ? (float) $opt['amount']
                                : null;

                            if ($initRate === null) {
                                $empDed = EmpDeduction::withoutGlobalScopes()
                                    ->where('employee_id', $employee->id)
                                    ->where('deduction_type_id', $dt->id)
                                    ->where('is_active', true)
                                    ->first();

                                $initRate = (float) ($empDed?->rate ?? 0.00);
                                if ($empDed?->frequency === 'monthly') {
                                    $initRate /= $statutoryPeriodFactor;
                                }
                            }

                            $payOuts[] = PayOut::create([
                                'pay_id' => $payHeader->id,
                                'deduction_type_id' => $dt->id,
                                'number_input' => 1.00,
                                'rate' => $initRate,
                            ]);
                            $processedLoanDeductionTypeIds[] = $dt->id;
                        }
                    }
                }

                // 9. Statutory Government Contributions (PH Compliance)
