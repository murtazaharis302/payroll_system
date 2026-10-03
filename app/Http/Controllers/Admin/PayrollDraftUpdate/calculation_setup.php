<?php

use App\Models\DeductionType;
use App\Models\EmpIncome;
use App\Models\IncomeType;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPolicy;
use App\Services\PayrollLineCalculator;
use Carbon\Carbon;

            $initialPayOutRates = \App\Models\PayOut::withoutGlobalScopes()
                ->where('pay_id', $draft->id)
                ->pluck('rate', 'id')
                ->toArray();

            $emp = $draft->employee;
            $companyId = $emp ? $emp->company_id : null;
            $periodStartDate = $draft->payrollPeriod ? (string) $draft->payrollPeriod->period_start : null;
            $policy = PayrollPolicy::forCompany($companyId, $periodStartDate);
            $lineCalculator = new PayrollLineCalculator($policy);

            $daysWorked = (float) ($validated['days_worked'] ?? $draft->days_worked ?? 1.00);
            $otHours = (float) ($validated['overtime_hours'] ?? $draft->overtime_hours ?? 0.00);

            // Basic Pay resolution using PayrollLineCalculator & PayrollPolicy contract
            $inputBasicPay = (array_key_exists('basic_pay', $validated) && $validated['basic_pay'] !== null)
                ? (float) $validated['basic_pay']
                : null;

            $basicQty = $daysWorked;
            if ($request->has('pay_ins') && is_array($request->input('pay_ins'))) {
                foreach ($request->input('pay_ins') as $incData) {
                    if (isset($incData['id'])) {
                        $pIn = \App\Models\PayIn::withoutGlobalScopes()->with('incomeType')->find($incData['id']);
                        if ($pIn && $pIn->incomeType?->system_key === 'basic' && isset($incData['number_input'])) {
                            $basicQty = (float) $incData['number_input'];
                            break;
                        }
                    }
                }
            } else {
                $basicPayIn = \App\Models\PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', function($q){ $q->where('system_key', 'basic'); })->first();
                if ($basicPayIn && (float) $basicPayIn->number_input > 0) {
                    $basicQty = (float) $basicPayIn->number_input;
                }
            }

            $basicLine = $lineCalculator->calculateBasicPay($emp, $basicQty, $inputBasicPay, 2.0, null);
            $basicPay = $basicLine['amount'];

            // Overtime Pay resolution using PayrollLineCalculator & PayrollPolicy contract
            $inputOtPay = (array_key_exists('overtime_pay', $validated) && $validated['overtime_pay'] !== null)
                ? (float) $validated['overtime_pay']
                : null;

            $otEmpIncome = EmpIncome::withoutGlobalScopes()
                ->where('employee_id', $emp->id)
                ->whereHas('incomeType', fn($q) => $q->where('system_key', 'overtime'))
                ->where('is_active', true)
                ->first();
            $otAuthoritativeRate = ($otEmpIncome !== null && (float) ($otEmpIncome->rate ?? $otEmpIncome->amount ?? 0) > 0)
                ? (float) ($otEmpIncome->rate ?? $otEmpIncome->amount ?? 0)
                : 0.00;

            $otMissingText = 'no active Overtime rate configured in Recurring Income';
            $otMissingReason = "Overtime hours recorded ({$otHours} hrs) but employee has no active Overtime rate configured in Recurring Income.";

            if ($otHours > 0 && $otAuthoritativeRate <= 0 && empty($validated['override_reason'])) {
                $currentReasons = !empty($draft->review_reason) ? array_map('trim', explode('|', $draft->review_reason)) : [];
                $currentReasons = array_filter($currentReasons, fn($r) => !str_contains($r, $otMissingText));
                $currentReasons[] = $otMissingReason;
                $currentReasons = array_values(array_filter($currentReasons));

                $draft->requires_review = true;
                $draft->review_reason = implode(' | ', array_unique($currentReasons));
            } elseif ($otAuthoritativeRate > 0 || $otHours <= 0) {
                if (!empty($draft->review_reason) && str_contains($draft->review_reason, $otMissingText)) {
                    $currentReasons = array_map('trim', explode('|', $draft->review_reason));
                    $currentReasons = array_filter($currentReasons, fn($r) => !str_contains($r, $otMissingText));
                    $currentReasons = array_values(array_filter($currentReasons));

                    if (empty($currentReasons)) {
                        $draft->requires_review = false;
                        $draft->review_reason = null;
                    } else {
                        $draft->review_reason = implode(' | ', $currentReasons);
                    }
                }
            }

            $otLine = $lineCalculator->calculateOvertimePay($emp, $otHours, $inputOtPay, 'regular', $otAuthoritativeRate);
            $otPay = $otLine['amount'];

            $totalAllowances = (array_key_exists('total_allowances', $validated) && $validated['total_allowances'] !== null) ? (float) $validated['total_allowances'] : (float) ($draft->total_allowances ?? 0.00);
            $totalBonuses = (array_key_exists('total_bonuses', $validated) && $validated['total_bonuses'] !== null) ? (float) $validated['total_bonuses'] : (float) ($draft->total_bonuses ?? 0.00);
            $otherIncome = (array_key_exists('other_income', $validated) && $validated['other_income'] !== null) ? (float) $validated['other_income'] : (float) ($draft->other_income ?? 0.00);

            $updateExistingPayIn = function ($typeId, array $attributes) use ($draft) {
                if (!$typeId) return;
                $existing = \App\Models\PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $typeId)->first();
                if ($existing) {
                    \App\Models\PayIn::withoutGlobalScopes()->where('id', $existing->id)->update($attributes);
                }
            };

            $updateExistingPayOut = function ($typeId, array $attributes) use ($draft) {
                if (!$typeId) return;
                $existing = \App\Models\PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('deduction_type_id', $typeId)->first();
                if ($existing) {
                    \App\Models\PayOut::withoutGlobalScopes()->where('id', $existing->id)->update($attributes);
                }
            };

            // Update Incomes (only if pay_ins array is not explicitly provided in Master Quick-Entry mode)
            if (!$request->has('pay_ins')) {
                if (array_key_exists('basic_pay', $validated) && $validated['basic_pay'] !== null) {
                    $basicType = IncomeType::findBySystemKey('basic', (int) $companyId);
                    if ($basicType) {
                        $updateExistingPayIn($basicType->id, ['number_input' => $basicLine['number_input'], 'rate' => $basicLine['rate']]);
                    }
                }

                $otType = IncomeType::findBySystemKey('overtime', (int) $companyId);
                if ($otType) {
                    $existingOt = \App\Models\PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $otType->id)->first();
                    if ($existingOt) {
                        $existingOt->update(['number_input' => $otLine['number_input'], 'rate' => $otLine['rate']]);
                    } elseif ($otLine['rate'] > 0 || $otLine['number_input'] > 0 || $otLine['amount'] > 0) {
                        \App\Models\PayIn::create([
                            'pay_id' => $draft->id,
                            'income_type_id' => $otType->id,
                            'number_input' => $otLine['number_input'] ?? 0.00,
                            'rate' => $otLine['rate'],
                        ]);
                    }
                }

                if (array_key_exists('total_allowances', $validated) && $validated['total_allowances'] !== null) {
                    $allowType = IncomeType::withoutGlobalScopes()
                        ->where(function ($q) use ($companyId) {
                            $q->where('company_id', $companyId)->orWhereNull('company_id');
                        })
                        ->where('code', 'ALLOWANCE')
                        ->first();
                    if ($allowType) {
                        $updateExistingPayIn($allowType->id, ['number_input' => 1.00, 'rate' => $totalAllowances]);
                    }
                }

                if (array_key_exists('total_bonuses', $validated) && $validated['total_bonuses'] !== null) {
                    $bonusType = IncomeType::withoutGlobalScopes()
                        ->where(function ($q) use ($companyId) {
                            $q->where('company_id', $companyId)->orWhereNull('company_id');
                        })
                        ->where('code', 'BONUS')
                        ->first();
                    if ($bonusType) {
                        $updateExistingPayIn($bonusType->id, ['number_input' => 1.00, 'rate' => $totalBonuses]);
                    }
                }

                if (array_key_exists('other_income', $validated) && $validated['other_income'] !== null) {
                    $otherType = IncomeType::withoutGlobalScopes()
                        ->where(function ($q) use ($companyId) {
                            $q->where('company_id', $companyId)->orWhereNull('company_id');
                        })
                        ->where('code', 'OTHER')
                        ->first();
                    if ($otherType) {
                        $updateExistingPayIn($otherType->id, ['number_input' => 1.00, 'rate' => $otherIncome]);
                    }
                }
            }

            // Update Statutory Deductions if provided
            if (array_key_exists('sss_contribution', $validated) && $validated['sss_contribution'] !== null) {
                $sssType = DeductionType::findBySystemKey('sss', (int) $companyId);
                if ($sssType) {
                    $updateExistingPayOut($sssType->id, ['number_input' => 1.00, 'rate' => (float) $validated['sss_contribution']]);
                }
            }

            if (array_key_exists('philhealth_contribution', $validated) && $validated['philhealth_contribution'] !== null) {
                $phType = DeductionType::findBySystemKey('philhealth', (int) $companyId);
                if ($phType) {
                    $updateExistingPayOut($phType->id, ['number_input' => 1.00, 'rate' => (float) $validated['philhealth_contribution']]);
                }
            }

            if (array_key_exists('pagibig_contribution', $validated) && $validated['pagibig_contribution'] !== null) {
                $piType = DeductionType::findBySystemKey('pagibig', (int) $companyId);
                if ($piType) {
                    $updateExistingPayOut($piType->id, ['number_input' => 1.00, 'rate' => (float) $validated['pagibig_contribution']]);
                }
            }

            if (array_key_exists('withholding_tax', $validated) && $validated['withholding_tax'] !== null) {
                $taxType = DeductionType::findBySystemKey('tax', (int) $companyId);
                if ($taxType) {
                    $updateExistingPayOut($taxType->id, ['number_input' => 1.00, 'rate' => (float) $validated['withholding_tax']]);
                }
            }

            $getCapLoanDeductionRate = function ($deductionType, float $targetRate) use ($draft) {
                if ($targetRate <= 0 || !$deductionType) {
                    return 0.00;
                }

                $matchingActiveLoan = EmpLoan::where('employee_id', $draft->employee_id)
                    ->where('current_balance', '>', 0)
                    ->whereHas('loanType', function ($lq) use ($deductionType) {
                        $lq->where('deduction_type_id', $deductionType->id);
                    })
                    ->first();

                if (!$matchingActiveLoan) {
                    return 0.00;
                }

                return min($targetRate, (float) $matchingActiveLoan->current_balance);
            };

            if (array_key_exists('sss_loan_deduction', $validated) && $validated['sss_loan_deduction'] !== null) {
                $sssLoanType = DeductionType::where('code', 'SSS_LOAN')->first();
                if ($sssLoanType) {
                    $sssLoanDedVal = $getCapLoanDeductionRate($sssLoanType, (float) $validated['sss_loan_deduction']);
                    $updateExistingPayOut($sssLoanType->id, ['number_input' => 1.00, 'rate' => $sssLoanDedVal]);
                }
            }

            if (array_key_exists('pagibig_loan_deduction', $validated) && $validated['pagibig_loan_deduction'] !== null) {
                $piLoanType = DeductionType::where('code', 'PAGIBIG_LOAN')->first();
                if ($piLoanType) {
                    $piLoanDedVal = $getCapLoanDeductionRate($piLoanType, (float) $validated['pagibig_loan_deduction']);
                    $updateExistingPayOut($piLoanType->id, ['number_input' => 1.00, 'rate' => $piLoanDedVal]);
                }
            }

            if (array_key_exists('vale_loan_deduction', $validated) && $validated['vale_loan_deduction'] !== null) {
                $valeLoanType = DeductionType::where('code', 'VALE')->first();
                if ($valeLoanType) {
                    $valeLoanDedVal = $getCapLoanDeductionRate($valeLoanType, (float) $validated['vale_loan_deduction']);
                    $updateExistingPayOut($valeLoanType->id, ['number_input' => 1.00, 'rate' => $valeLoanDedVal]);
                }
            }

            if (array_key_exists('goods_purchase_deduction', $validated) && $validated['goods_purchase_deduction'] !== null) {
                $goodsType = DeductionType::where('code', 'GOODS')->first();
                if ($goodsType) {
                    $goodsDedVal = $getCapLoanDeductionRate($goodsType, (float) $validated['goods_purchase_deduction']);
                    $updateExistingPayOut($goodsType->id, ['number_input' => 1.00, 'rate' => $goodsDedVal]);
                }
            }

            if (array_key_exists('late_deduction', $validated) && $validated['late_deduction'] !== null) {
                $lateType = DeductionType::findBySystemKey('tardiness', (int) $companyId);
                if ($lateType) {
                    $lateHours = (float) ($validated['late_hours'] ?? $draft->late_hours ?? 0.00);
                    $lateDed = (float) $validated['late_deduction'];
                    $updateExistingPayOut($lateType->id, ['number_input' => $lateHours, 'rate' => $lateHours > 0 ? $lateDed / $lateHours : $lateDed]);
                }
            }

            if (array_key_exists('undertime_deduction', $validated) && $validated['undertime_deduction'] !== null) {
                $utType = DeductionType::findBySystemKey('undertime', (int) $companyId);
                if ($utType) {
                    $utHours = (float) ($validated['undertime_hours'] ?? 0.00);
                    $utDed = (float) $validated['undertime_deduction'];
                    $updateExistingPayOut($utType->id, ['number_input' => $utHours, 'rate' => $utHours > 0 ? $utDed / $utHours : $utDed]);
                }
            }

            $customIncomeSum = 0.00;
            if ($request->has('custom_incomes') && is_array($request->input('custom_incomes'))) {
                foreach ($request->input('custom_incomes') as $typeId => $val) {
                    $valFloat = max(0, (float) ($val ?? 0));
                    $customIncomeSum += $valFloat;
                    if ($typeId) {
                        $updateExistingPayIn($typeId, ['number_input' => 1.00, 'rate' => $valFloat]);
                    }
                }
            }

            if (array_key_exists('other_deductions', $validated) && $validated['other_deductions'] !== null) {
                $otherDType = DeductionType::where('code', 'OTHER')->first();
                if ($otherDType) {
                    $otherDeductionVal = (float) $validated['other_deductions'];
                    $updateExistingPayOut($otherDType->id, ['number_input' => 1.00, 'rate' => $otherDeductionVal]);
                }
            }

            $customDeductionsSum = 0.00;
            if ($request->has('custom_deductions') && is_array($request->input('custom_deductions'))) {
                foreach ($request->input('custom_deductions') as $typeId => $val) {
                    $valFloat = max(0, (float) ($val ?? 0));
                    if ($typeId) {
                        $customDt = DeductionType::find($typeId);
                        $finalVal = $customDt ? $getCapLoanDeductionRate($customDt, $valFloat) : $valFloat;
                        $customDeductionsSum += $finalVal;
                        $updateExistingPayOut($typeId, ['number_input' => 1.00, 'rate' => $finalVal]);
                    }
                }
            }
