<?php

use App\Models\AttendanceLog;
use App\Models\EmpIncome;
use App\Models\HRAdjustment;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPolicy;
use App\Services\PayrollLineCalculator;
use Carbon\Carbon;

                // 1. Process Attendance & HR Adjustments
                $totalHoursWorked = 0.00;
                $totalOvertimeHours = 0.00;
                $totalDaysWorked = 0.00;

                // Fetch attendance logs for the employee in the period
                $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
                    ->whereBetween('log_date', [$period->period_start, $period->period_end])
                    ->get()
                    ->groupBy(fn($log) => Carbon::parse($log->log_date)->toDateString());

                // Fetch approved attendance HR adjustments
                $attendanceAdjustments = HRAdjustment::where('employee_id', $employee->id)
                    ->where('adjustment_type', 'attendance')
                    ->where('status', 'approved')
                    ->whereBetween('effective_date', [$period->period_start, $period->period_end])
                    ->get()
                    ->keyBy(fn($adj) => Carbon::parse($adj->effective_date)->toDateString());

                // Iterate through each date in the period
                $currentDate = Carbon::parse($period->period_start);
                while ($currentDate->lessThanOrEqualTo($period->period_end)) {
                    $dateStr = $currentDate->toDateString();

                    if ($attendanceAdjustments->has($dateStr)) {
                        // Use approved adjustment
                        $adj = $attendanceAdjustments->get($dateStr);
                        $hours = (float) ($adj->hours_worked ?? 0.00);
                        $otHours = (float) ($adj->overtime_hours ?? 0.00);

                        $totalHoursWorked += $hours;
                        $totalOvertimeHours += $otHours;
                        if ($hours > 0) {
                            $totalDaysWorked += 1.00;
                        }
                    } elseif ($attendanceLogs->has($dateStr)) {
                        // Calculate from logs with overnight shift support and incomplete punch safety
                        $dayLogs = $attendanceLogs->get($dateStr);
                        $inLogs = $dayLogs->filter(fn($log) => strtolower((string)$log->log_type) === 'in');
                        $outLogs = $dayLogs->filter(fn($log) => strtolower((string)$log->log_type) === 'out');

                        if ($inLogs->isNotEmpty() && $outLogs->isNotEmpty()) {
                            $earliestIn = Carbon::parse($dateStr . ' ' . $inLogs->min('log_time'));
                            $latestOutTime = $outLogs->max('log_time');
                            $latestOut = Carbon::parse($dateStr . ' ' . $latestOutTime);

                            if ($latestOut->lessThanOrEqualTo($earliestIn)) {
                                $latestOut->addDay();
                            }

                            $hours = $earliestIn->diffInMinutes($latestOut) / 60.00;

                            // Deduct 1 hour lunch break if worked hours > 5 hours
                            if ($hours > 5.00) {
                                $hours = max(0.00, $hours - 1.00);
                            }

                            $totalHoursWorked += $hours;
                            if ($hours > 0) {
                                $totalDaysWorked += 1.00;
                            }
                        }
                    }
                    $currentDate->addDay();
                }

                $companyId = $period->company_id ?? $employee->company_id;
                $policy = PayrollPolicy::forCompany($companyId, $period->period_start ? (string) $period->period_start : null);
                $lineCalculator = new PayrollLineCalculator($policy);

                // Source-of-truth: Resolve explicit submitted Days Worked (Attendance & Time Entry)
                $submittedDaysWorked = null;
                if (isset($options['payroll_inputs'][$employee->id]['days_worked']) && is_numeric($options['payroll_inputs'][$employee->id]['days_worked'])) {
                    $submittedDaysWorked = (float) $options['payroll_inputs'][$employee->id]['days_worked'];
                } elseif (isset($options['employee_inputs'][$employee->id]['days_worked']) && is_numeric($options['employee_inputs'][$employee->id]['days_worked'])) {
                    $submittedDaysWorked = (float) $options['employee_inputs'][$employee->id]['days_worked'];
                } elseif (isset($options['days_worked'][$employee->id]) && is_numeric($options['days_worked'][$employee->id])) {
                    $submittedDaysWorked = (float) $options['days_worked'][$employee->id];
                } elseif (isset($options['days_worked']) && is_numeric($options['days_worked'])) {
                    $submittedDaysWorked = (float) $options['days_worked'];
                }

                $resolvedDaysWorked = $submittedDaysWorked !== null ? $submittedDaysWorked : $totalDaysWorked;
                if ($submittedDaysWorked === null && $totalDaysWorked <= 0 && $policy->no_attendance_policy === 'fixed_cutoff_salary') {
                    $resolvedDaysWorked = round((float) $policy->workdays_per_month / (float) $statutoryPeriodFactor, 2);
                }

                // Source-of-truth: Resolve explicit submitted Days Absent (Attendance & Time Entry)
                $submittedDaysAbsent = null;
                if (isset($options['payroll_inputs'][$employee->id]['days_absent']) && is_numeric($options['payroll_inputs'][$employee->id]['days_absent'])) {
                    $submittedDaysAbsent = (float) $options['payroll_inputs'][$employee->id]['days_absent'];
                } elseif (isset($options['employee_inputs'][$employee->id]['days_absent']) && is_numeric($options['employee_inputs'][$employee->id]['days_absent'])) {
                    $submittedDaysAbsent = (float) $options['employee_inputs'][$employee->id]['days_absent'];
                } elseif (isset($options['days_absent'][$employee->id]) && is_numeric($options['days_absent'][$employee->id])) {
                    $submittedDaysAbsent = (float) $options['days_absent'][$employee->id];
                } elseif (isset($options['days_absent']) && is_numeric($options['days_absent'])) {
                    $submittedDaysAbsent = (float) $options['days_absent'];
                }
                $resolvedDaysAbsent = $submittedDaysAbsent !== null ? max(0.00, $submittedDaysAbsent) : 0.00;

                $requiresReview = false;
                $reviewReason = null;
                $reviewReasons = [];
                $skippedLoanDeductionNames = [];

                $isDailyOrHourlyWorker = ((float)$employee->monthly_rate <= 0) && ((float)$employee->daily_rate > 0 || (float)$employee->hourly_rate > 0);
                if ($isDailyOrHourlyWorker && $totalDaysWorked <= 0 && $totalHoursWorked <= 0 && $policy->no_attendance_policy === 'zero_pay_requires_review') {
                    $requiresReview = true;
                    $reviewReason = 'No attendance logs recorded for employee during this period.';
                    $reviewReasons[] = $reviewReason;
                }

                // 2. Create Pay Header
                $payHeader = PayHeader::create([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'days_worked' => $resolvedDaysWorked,
                    'days_absent' => $resolvedDaysAbsent,
                    'status' => 'draft',
                    'requires_review' => $requiresReview,
                    'review_reason' => $reviewReason,
                    'policy_version' => $policyVersion,
                ]);

                $payIns = [];
                $payOuts = [];
                $generatedIncomeTypeIds = [];

                // 3. Overtime Pay Calculation
                if ($overtimePayType) {
                    $otCfg = $getIncomeOption($overtimePayType->system_key, $overtimePayType->code, $overtimePayType->name, $overtimePayType->id);
                    $includeOt = (bool) ($otCfg['display'] ?? true);
                    $effectiveOtHours = $totalOvertimeHours;

                    if ($otCfg['has_config'] && $otCfg['number_input'] !== null) {
                        $effectiveOtHours = (float) $otCfg['number_input'];
                    } elseif ($useFixedIncome && $otCfg['has_config'] && !empty($otCfg['is_legacy_default']) && $otCfg['amount'] !== null) {
                        $effectiveOtHours = (float) $otCfg['amount'];
                    }

                    if (isset($options['payroll_inputs'][$employee->id]['overtime_hours']) && is_numeric($options['payroll_inputs'][$employee->id]['overtime_hours'])) {
                        $effectiveOtHours = (float) $options['payroll_inputs'][$employee->id]['overtime_hours'];
                    } elseif (isset($options['employee_inputs'][$employee->id]['overtime_hours']) && is_numeric($options['employee_inputs'][$employee->id]['overtime_hours'])) {
                        $effectiveOtHours = (float) $options['employee_inputs'][$employee->id]['overtime_hours'];
                    } elseif (isset($options['overtime_hours'][$employee->id]) && is_numeric($options['overtime_hours'][$employee->id])) {
                        $effectiveOtHours = (float) $options['overtime_hours'][$employee->id];
                    } elseif (isset($options['overtime_hours']) && is_numeric($options['overtime_hours'])) {
                        $effectiveOtHours = (float) $options['overtime_hours'];
                    }

                    // Check for active EmpIncome assignment for OVERTIME under Recurring Income Entries
                    $otEmpIncome = EmpIncome::withoutGlobalScopes()
                        ->where('employee_id', $employee->id)
                        ->where('income_type_id', $overtimePayType->id)
                        ->where('is_active', true)
                        ->orderByDesc('rate')
                        ->first();

                    $otRateAuthoritative = ($otEmpIncome !== null && (float) ($otEmpIncome->rate ?? $otEmpIncome->amount ?? 0) > 0)
                        ? (float) ($otEmpIncome->rate ?? $otEmpIncome->amount ?? 0)
                        : 0.00;

                    if ($effectiveOtHours > 0 && $otRateAuthoritative <= 0) {
                        $requiresReview = true;
                        $otMissingReason = "Overtime hours recorded ({$effectiveOtHours} hrs) but employee has no active Overtime rate configured in Recurring Income.";
                        $reviewReasons[] = $otMissingReason;
                        if (empty($reviewReason)) {
                            $reviewReason = $otMissingReason;
                        }
                    }

                    $otLine = $lineCalculator->calculateOvertimePay($employee, $effectiveOtHours, null, 'regular', $otRateAuthoritative);

                    $shouldGenerateOt = $includeOt;

                    if ($shouldGenerateOt) {
                        $payIns[] = PayIn::create([
                            'pay_id' => $payHeader->id,
                            'income_type_id' => $overtimePayType->id,
                            'number_input' => $effectiveOtHours ?? 0.00,
                            'rate' => $otLine['rate'] ?? $otRateAuthoritative ?? 0.00,
                        ]);
                        $generatedIncomeTypeIds[] = $overtimePayType->id;
                    }
                }

                // 4. Primary Earnings & Recurring Income Generation
                if ($useFixedIncome && !empty($rawIncomeOptions)) {
                    // Manual / Fixed Input Mode: Process selected income types
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

                        if (!$matchedType || $matchedType->system_key === 'overtime' || in_array($matchedType->id, $generatedIncomeTypeIds, true)) {
                            continue;
                        }

                        $incCfg = $getIncomeOption($matchedType->system_key, $matchedType->code, $matchedType->name, $matchedType->id);

                        // Check if employee has an active assigned EmpIncome for this IncomeType
                        $empInc = EmpIncome::withoutGlobalScopes()
                            ->where('employee_id', $employee->id)
                            ->where('income_type_id', $matchedType->id)
                            ->where('is_active', true)
                            ->first();

                        $hasManualAmount = isset($incItem['amount']) && is_numeric($incItem['amount']) && (float) $incItem['amount'] > 0;
                        $hasConfigAmount = $incCfg['has_config'] && isset($incCfg['amount']) && is_numeric($incCfg['amount']) && (float) $incCfg['amount'] > 0;

                        if ($hasManualAmount) {
                            $rate = (float) $incItem['amount'];
                            $frequency = $empInc?->frequency ?? 'custom';
                        } elseif ($hasConfigAmount) {
                            $rate = (float) $incCfg['amount'];
                            $frequency = $empInc?->frequency ?? 'custom';
                        } elseif ($empInc && (float) ($empInc->rate ?? $empInc->amount ?? 0) > 0) {
                            $rate = (float) ($empInc->rate ?? $empInc->amount ?? 0);
                            $frequency = $empInc->frequency ?? 'monthly';
                        } else {
                            continue; // No manual fixed amount and no active recurring assignment
                        }

                        if ($incCfg['has_config'] && $incCfg['number_input'] !== null) {
                            $input = (float) $incCfg['number_input'];
                        } elseif ($hasManualAmount || $hasConfigAmount) {
                            $input = 0.00;
                        } elseif ($frequency === 'daily') {
                            $input = $resolvedDaysWorked;
                        } elseif ($frequency === 'hourly') {
                            $input = $totalHoursWorked;
                        } elseif ($frequency === 'monthly') {
                            $input = 0.00;
                        } else {
                            $input = 0.00;
                        }

                        $payIns[] = PayIn::create([
                            'pay_id' => $payHeader->id,
                            'income_type_id' => $matchedType->id,
                            'number_input' => $input,
                            'rate' => $rate,
                        ]);

                        $generatedIncomeTypeIds[] = $matchedType->id;
                    }
                } else {
                    // Automated / Assigned Mode: Driven strictly by Employee Master EmpIncome assignments
                    $recurringIncomes = EmpIncome::withoutGlobalScopes()
                        ->with(['incomeType' => fn($q) => $q->withoutGlobalScopes()])
                        ->where('employee_id', $employee->id)
                        ->where('is_active', true)
                        ->orderByDesc('rate')
                        ->get()
                        ->unique('income_type_id');

                    foreach ($recurringIncomes as $income) {
                        if (!$income->income_type_id || in_array($income->income_type_id, $generatedIncomeTypeIds, true)) {
                            continue;
                        }
                        if ($income->incomeType?->system_key === 'overtime') {
                            continue; // Overtime is handled in the dedicated overtime step
                        }

                        // Honor selective exclusion if option was configured as display = false
                        $incCfg = $getIncomeOption($income->incomeType?->system_key, $income->incomeType?->code, $income->incomeType?->name, $income->income_type_id);
                        if ($incCfg['has_config'] && !$incCfg['display']) {
                            continue;
                        }

                        $rate = (float) ($income->rate ?? $income->amount ?? 0);
                        if ($rate <= 0) {
                            continue;
                        }

                        if ($incCfg['has_config'] && $incCfg['number_input'] !== null) {
                            $input = (float) $incCfg['number_input'];
                        } elseif ($income->frequency === 'daily') {
                            $input = $resolvedDaysWorked;
                        } elseif ($income->frequency === 'hourly') {
                            $input = $totalHoursWorked;
                        } elseif ($income->frequency === 'monthly') {
                            $input = 0.00;
                        } else {
                            $input = 0.00;
                        }

                        $payIns[] = PayIn::create([
                            'pay_id' => $payHeader->id,
                            'income_type_id' => $income->income_type_id,
                            'number_input' => $input,
                            'rate' => $rate,
                        ]);

                        $generatedIncomeTypeIds[] = $income->income_type_id;
                    }
                }

                // 6. HR Adjustments (one-offs)
                $oneOffAdjustments = HRAdjustment::where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereIn('adjustment_type', ['income', 'deduction'])
                    ->whereBetween('effective_date', [$period->period_start, $period->period_end])
                    ->get();

                foreach ($oneOffAdjustments as $adj) {
                    $amount = (float) $adj->amount;
                    if ($adj->adjustment_type === 'income' && $adj->income_type_id) {
                        $payIns[] = PayIn::create([
                            'pay_id' => $payHeader->id,
                            'income_type_id' => $adj->income_type_id,
                            'number_input' => 1.00,
                            'rate' => $amount,
                        ]);
                    } elseif ($adj->adjustment_type === 'deduction' && $adj->deduction_type_id) {
                        $payOuts[] = PayOut::create([
                            'pay_id' => $payHeader->id,
                            'deduction_type_id' => $adj->deduction_type_id,
                            'number_input' => 1.00,
                            'rate' => $amount,
                        ]);
                    }
                }

                $processedLoanDeductionTypeIds = [];
