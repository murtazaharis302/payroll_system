<?php

namespace App\Services;

use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayrollPolicy;
use Illuminate\Support\Facades\Log;

class EmployeeService
{
    public const DEFAULT_WORKING_DAYS = 26;
    public const DEFAULT_HOURS_PER_DAY = 8;

    /**
     * Unidirectional Synchronization:
     * Derive internal/read-only base rates (monthly_rate, daily_rate, hourly_rate) on EmployeeMaster
     * strictly from the authoritative Basic Salary EmpIncome record.
     */
    public function syncBaseRatesFromIncome(EmpIncome $empIncome): bool
    {
        $employee = $empIncome->employee ?? EmployeeMaster::find($empIncome->employee_id);
        if (!$employee) {
            return false;
        }

        $basicType = IncomeType::findBySystemKey('basic', $employee->company_id);
        if (!$basicType || (int) $empIncome->income_type_id !== (int) $basicType->id) {
            return false;
        }

        if (!$empIncome->is_active) {
            return $this->handleIncomeDeletion($empIncome);
        }

        $rate = (float) ($empIncome->rate ?? $empIncome->amount ?? 0);
        $frequency = strtolower((string) ($empIncome->frequency ?? 'monthly'));

        $policy = PayrollPolicy::forCompany($employee->company_id);
        $workdays = (float) ($policy->workdays_per_month > 0 ? $policy->workdays_per_month : self::DEFAULT_WORKING_DAYS);
        $hours = (float) ($policy->hours_per_day > 0 ? $policy->hours_per_day : self::DEFAULT_HOURS_PER_DAY);

        if ($frequency === 'daily') {
            $dailyRate = $rate;
            $monthlyRate = round($rate * $workdays, 2);
            $hourlyRate = round($rate / $hours, 2);
        } elseif ($frequency === 'hourly') {
            $hourlyRate = $rate;
            $dailyRate = round($rate * $hours, 2);
            $monthlyRate = round($rate * $hours * $workdays, 2);
        } elseif ($frequency === 'semi-monthly') {
            $monthlyRate = round($rate * 2, 2);
            $dailyRate = round(($rate * 2) / $workdays, 2);
            $hourlyRate = round(($rate * 2) / $workdays / $hours, 2);
        } else {
            // Default monthly
            $monthlyRate = $rate;
            $dailyRate = round($rate / $workdays, 2);
            $hourlyRate = round($rate / $workdays / $hours, 2);
        }

        $employee->monthly_rate = $monthlyRate;
        $employee->daily_rate = $dailyRate;
        $employee->hourly_rate = $hourlyRate;
        $employee->saveQuietly();

        return true;
    }

    /**
     * Handle deletion or deactivation of a Basic Salary assigned income record.
     */
    public function handleIncomeDeletion(EmpIncome $empIncome): bool
    {
        $employee = $empIncome->employee ?? EmployeeMaster::find($empIncome->employee_id);
        if (!$employee) {
            return false;
        }

        $basicType = IncomeType::findBySystemKey('basic', $employee->company_id);
        if (!$basicType || (int) $empIncome->income_type_id !== (int) $basicType->id) {
            return false;
        }

        // Search for any remaining active Basic Salary line item for this employee
        $remainingIncome = EmpIncome::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('income_type_id', $basicType->id)
            ->where('id', '!=', $empIncome->id)
            ->where('is_active', true)
            ->first();

        if ($remainingIncome) {
            return $this->syncBaseRatesFromIncome($remainingIncome);
        }

        Log::warning("Basic Salary assigned income (ID {$empIncome->id}) removed for employee ID {$empIncome->employee_id}. Existing master base rates maintained.");

        return true;
    }
}
