<?php

use App\Models\GovernmentContribution;
use App\Models\PayHeader;
use Illuminate\Support\Facades\DB;

$companyId = $period->company_id;

require __DIR__ . '/setup_options.php';
require __DIR__ . '/setup_lookups.php';
require __DIR__ . '/preflight_validation.php';

        DB::transaction(function () use (
            $companyId,
            $period,
            $employees,
            $overtimePayType,
            $sssDeductionType,
            $philhealthDeductionType,
            $pagibigDeductionType,
            $taxDeductionType,
            $statutoryPeriodFactor,
            $annualPeriods,
            $sssRule,
            $philhealthRule,
            $pagibigRule,
            $taxRule,
            $policyVersion,
            $getDeductionOption,
            $getIncomeOption,
            $useFixedAmount,
            $useFixedIncome,
            $rawIncomeOptions,
            $rawDeductionOptions,
            $deductionConfigMap,
            $options,
            &$generatedHeaders
        ) {
            foreach ($employees as $employee) {
                // Delete existing draft pay header for this period and employee if editable
                $existingHeader = PayHeader::where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->first();

                if ($existingHeader) {
                    if (!empty($options['create_only'])) {
                        continue;
                    }

                    if (!$existingHeader->canEdit()) {
                        // Approved or locked drafts cannot be regenerated
                        continue;
                    }
                    // Clean up related pay_ins, pay_outs, and contributions
                    $existingHeader->payIns()->delete();
                    $existingHeader->payOuts()->delete();
                    GovernmentContribution::where('payroll_draft_id', $existingHeader->id)->delete();
                    $existingHeader->delete();
                }

                require __DIR__ . '/steps/attendance_and_income.php';
                require __DIR__ . '/steps/loans_and_deductions.php';
                require __DIR__ . '/steps/statutory_and_totals.php';
            }
        });

        return $generatedHeaders;
