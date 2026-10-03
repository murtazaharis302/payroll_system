<?php

use App\Models\GovernmentContribution;
use App\Models\PayOut;

                $tempGross = 0.00;
                foreach ($payIns as $pi) {
                    $tempGross += ((float) $pi->rate * (float) $pi->number_input);
                }

                if ($tempGross > 0) {
                    $monthlySalaryBasis = (float) ($tempGross * $statutoryPeriodFactor);
                } elseif ((float) $employee->monthly_rate > 0) {
                    $monthlySalaryBasis = (float) $employee->monthly_rate;
                } elseif ((float) $employee->daily_rate > 0) {
                    $monthlySalaryBasis = (float) $employee->daily_rate * 26;
                } elseif ((float) $employee->hourly_rate > 0) {
                    $monthlySalaryBasis = (float) $employee->hourly_rate * 8 * 26;
                } else {
                    $monthlySalaryBasis = 0.00;
                }

                // --- SSS (Effective-Dated Rule) ---
                $sssCalc = $this->statutoryCalculationService->calculateSSS($monthlySalaryBasis, $sssRule);
                $periodSssEE = round($sssCalc['monthly_ee'] / $statutoryPeriodFactor, 2);
                $periodSssER = round($sssCalc['monthly_er'] / $statutoryPeriodFactor, 2);
                $periodSssEC = round($sssCalc['monthly_ec'] / $statutoryPeriodFactor, 2);

                $sssOpt = $getDeductionOption($sssDeductionType->system_key, $sssDeductionType->code, $sssDeductionType->name, $sssDeductionType->id);
                if ($sssOpt['display'] !== false) {
                    if ($useFixedAmount && $sssOpt['has_config']) {
                        $periodSssEE = (float) $sssOpt['amount'];
                        $periodSssER = 0.00;
                        $periodSssEC = 0.00;
                    }

                    $payOuts[] = PayOut::create([
                        'pay_id' => $payHeader->id,
                        'deduction_type_id' => $sssDeductionType->id,
                        'number_input' => 1.00,
                        'rate' => $periodSssEE,
                    ]);

                    GovernmentContribution::create([
                        'employee_id' => $employee->id,
                        'company_id' => $companyId,
                        'payroll_period_id' => $period->id,
                        'payroll_draft_id' => $payHeader->id,
                        'type' => 'sss',
                        'employee_share' => $periodSssEE,
                        'employer_share' => $periodSssER + $periodSssEC,
                        'total_contribution' => $periodSssEE + $periodSssER + $periodSssEC,
                        'period_start' => $period->period_start,
                        'period_end' => $period->period_end,
                        'status' => 'pending',
                        'notes' => 'Generated automatically - SSS (' . $sssRule->version_code . ')',
                    ]);
                } else {
                    $periodSssEE = 0.00;
                }

                // --- PhilHealth (Effective-Dated Rule) ---
                $phCalc = $this->statutoryCalculationService->calculatePhilHealth($monthlySalaryBasis, $philhealthRule);
                $periodPhEE = round($phCalc['monthly_ee'] / $statutoryPeriodFactor, 2);
                $periodPhER = round($phCalc['monthly_er'] / $statutoryPeriodFactor, 2);

                $phOpt = $getDeductionOption($philhealthDeductionType->system_key, $philhealthDeductionType->code, $philhealthDeductionType->name, $philhealthDeductionType->id);
                if ($phOpt['display'] !== false) {
                    if ($useFixedAmount && $phOpt['has_config']) {
                        $periodPhEE = (float) $phOpt['amount'];
                        $periodPhER = 0.00;
                    }

                    $payOuts[] = PayOut::create([
                        'pay_id' => $payHeader->id,
                        'deduction_type_id' => $philhealthDeductionType->id,
                        'number_input' => 1.00,
                        'rate' => $periodPhEE,
                    ]);

                    GovernmentContribution::create([
                        'employee_id' => $employee->id,
                        'company_id' => $companyId,
                        'payroll_period_id' => $period->id,
                        'payroll_draft_id' => $payHeader->id,
                        'type' => 'philhealth',
                        'employee_share' => $periodPhEE,
                        'employer_share' => $periodPhER,
                        'total_contribution' => $periodPhEE + $periodPhER,
                        'period_start' => $period->period_start,
                        'period_end' => $period->period_end,
                        'status' => 'pending',
                        'notes' => 'Generated automatically - PhilHealth (' . $philhealthRule->version_code . ')',
                    ]);
                } else {
                    $periodPhEE = 0.00;
                }

                // --- Pag-IBIG (Effective-Dated Rule) ---
                $pagibigCalc = $this->statutoryCalculationService->calculatePagIbig($monthlySalaryBasis, $pagibigRule);
                $periodPagibigEE = round($pagibigCalc['monthly_ee'] / $statutoryPeriodFactor, 2);
                $periodPagibigER = round($pagibigCalc['monthly_er'] / $statutoryPeriodFactor, 2);

                $pagibigOpt = $getDeductionOption($pagibigDeductionType->system_key, $pagibigDeductionType->code, $pagibigDeductionType->name, $pagibigDeductionType->id);
                if ($pagibigOpt['display'] !== false) {
                    if ($useFixedAmount && $pagibigOpt['has_config']) {
                        $periodPagibigEE = (float) $pagibigOpt['amount'];
                        $periodPagibigER = 0.00;
                    }

                    $payOuts[] = PayOut::create([
                        'pay_id' => $payHeader->id,
                        'deduction_type_id' => $pagibigDeductionType->id,
                        'number_input' => 1.00,
                        'rate' => $periodPagibigEE,
                    ]);

                    GovernmentContribution::create([
                        'employee_id' => $employee->id,
                        'company_id' => $companyId,
                        'payroll_period_id' => $period->id,
                        'payroll_draft_id' => $payHeader->id,
                        'type' => 'pagibig',
                        'employee_share' => $periodPagibigEE,
                        'employer_share' => $periodPagibigER,
                        'total_contribution' => $periodPagibigEE + $periodPagibigER,
                        'period_start' => $period->period_start,
                        'period_end' => $period->period_end,
                        'status' => 'pending',
                        'notes' => 'Generated automatically - Pag-IBIG (' . $pagibigRule->version_code . ')',
                    ]);
                } else {
                    $periodPagibigEE = 0.00;
                }

                // --- Withholding Tax (Effective-Dated Rule) ---
                $grossPay = 0.00;
                foreach ($payIns as $payIn) {
                    $grossPay += ((float) $payIn->rate * (float) $payIn->number_input);
                }

                $taxableIncome = max(0.00, $grossPay - ($periodSssEE + $periodPhEE + $periodPagibigEE));
                $periodTax = $this->statutoryCalculationService->calculateWithholdingTax($taxableIncome, $annualPeriods, $taxRule);

                $taxOpt = $getDeductionOption($taxDeductionType->system_key, $taxDeductionType->code, $taxDeductionType->name, $taxDeductionType->id);
                if ($taxOpt['display'] !== false) {
                    if ($useFixedAmount && $taxOpt['has_config'] && isset($taxOpt['amount']) && (float)$taxOpt['amount'] > 0) {
                        $periodTax = (float) $taxOpt['amount'];
                    }

                    $payOuts[] = PayOut::create([
                        'pay_id' => $payHeader->id,
                        'deduction_type_id' => $taxDeductionType->id,
                        'number_input' => 1.00,
                        'rate' => $periodTax,
                    ]);
                }

                // 10. Totals Calculation & Header Update
                $totalGross = 0.00;
                foreach ($payIns as $pi) {
                    $totalGross += ((float) $pi->rate * (float) $pi->number_input);
                }

                $totalDeductions = 0.00;
                foreach ($payOuts as $po) {
                    $totalDeductions += ((float) $po->rate * (float) $po->number_input);
                }

                $netPay = $totalGross - $totalDeductions;

                if (!empty($skippedLoanDeductionNames)) {
                    $skippedLoanNamesStr = implode(', ', array_unique($skippedLoanDeductionNames));
                    $reviewReasons[] = "Selected loan deductions skipped due to missing active loans: {$skippedLoanNamesStr}.";
                }

                $reviewReasons = array_filter(array_unique($reviewReasons));
                $finalReviewReason = !empty($reviewReasons) ? implode(' | ', $reviewReasons) : null;
                $finalRequiresReview = $requiresReview || !empty($reviewReasons);

                $payHeader->update([
                    'total_gross_pay' => $totalGross,
                    'total_deductions' => $totalDeductions,
                    'net_pay' => $netPay,
                    'requires_review' => $finalRequiresReview,
                    'review_reason' => $finalReviewReason,
                    'policy_version' => $policyVersion,
                    'status' => 'draft',
                ]);

                $generatedHeaders->push($payHeader);
