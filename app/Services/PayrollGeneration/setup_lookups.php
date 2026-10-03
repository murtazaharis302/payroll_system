<?php

use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\DeductionType;
use Carbon\Carbon;

        // Fetch active employees matching selection
        $employeesQuery = EmployeeMaster::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', 'active');

        if (!empty($employeeIds)) {
            $employeesQuery->whereIn('id', $employeeIds);
        } else {
            $employeesQuery->whereHas('dept', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $employees = $employeesQuery->get();
        $generatedHeaders = collect();

        // Income and Deduction types for lookup with company-specific precedence
        $overtimePayType = IncomeType::findBySystemKey('overtime', (int) $companyId);

        $sssDeductionType = DeductionType::findBySystemKey('sss', (int) $companyId)
            ?? throw new \RuntimeException("SSS deduction type not found for company [{$companyId}].");

        $philhealthDeductionType = DeductionType::findBySystemKey('philhealth', (int) $companyId)
            ?? throw new \RuntimeException("PhilHealth deduction type not found for company [{$companyId}].");

        $pagibigDeductionType = DeductionType::findBySystemKey('pagibig', (int) $companyId)
            ?? throw new \RuntimeException("Pag-IBIG deduction type not found for company [{$companyId}].");

        $taxDeductionType = DeductionType::findBySystemKey('tax', (int) $companyId)
            ?? throw new \RuntimeException("Withholding Tax deduction type not found for company [{$companyId}].");

        // Determine if period is semi-monthly or monthly
        $startDate = Carbon::parse($period->period_start);
        $endDate = Carbon::parse($period->period_end);
        $daysInPeriod = $startDate->diffInDays($endDate) + 1;
        $isSemiMonthly = $daysInPeriod <= 16;
        $statutoryPeriodFactor = $isSemiMonthly ? 2 : 1;
        $annualPeriods = $isSemiMonthly ? 24 : 12;

        // Resolve statutory rule versions for the pay date (no silent fallback)
        $payDate = $period->pay_date
            ? Carbon::parse($period->pay_date)->toDateString()
            : Carbon::parse($period->period_end)->toDateString();

        $sssRule = $this->statutoryCalculationService->resolveRule('sss', $payDate);
        $philhealthRule = $this->statutoryCalculationService->resolveRule('philhealth', $payDate);
        $pagibigRule = $this->statutoryCalculationService->resolveRule('pagibig', $payDate);
        $taxRule = $this->statutoryCalculationService->resolveRule('tax', $payDate);

        $policyVersion = json_encode([
            'sss' => $sssRule->version_code,
            'philhealth' => $philhealthRule->version_code,
            'pagibig' => $pagibigRule->version_code,
            'tax' => $taxRule->version_code,
        ]);
