<?php

use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Forward-sync existing EmployeeMaster compensation rates to canonical EmpIncome records.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employees_master') || !Schema::hasTable('emp_income') || !Schema::hasTable('income_types')) {
            return;
        }

        $employees = EmployeeMaster::withoutGlobalScopes()->get();

        foreach ($employees as $employee) {
            $monthlyRate = (float) ($employee->monthly_rate ?? 0);
            $dailyRate = (float) ($employee->daily_rate ?? 0);
            $hourlyRate = (float) ($employee->hourly_rate ?? 0);

            if ($monthlyRate <= 0 && $dailyRate <= 0 && $hourlyRate <= 0) {
                continue;
            }

            // Find canonical basic income type (company-specific first, then global)
            $basicType = IncomeType::findBySystemKey('basic', $employee->company_id);
            if (!$basicType) {
                continue;
            }

            $rate = $monthlyRate > 0 ? $monthlyRate : ($dailyRate > 0 ? $dailyRate : $hourlyRate);
            $frequency = $monthlyRate > 0 ? 'monthly' : ($dailyRate > 0 ? 'daily' : 'hourly');

            $existing = EmpIncome::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('income_type_id', $basicType->id)
                ->first();

            if (!$existing) {
                $empInc = new EmpIncome([
                    'employee_id'    => $employee->id,
                    'income_type_id' => $basicType->id,
                    'rate'           => $rate,
                    'amount'         => $rate,
                    'frequency'      => $frequency,
                    'is_active'      => true,
                ]);
                $empInc->saveQuietly();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward non-destructive migration; existing records preserved.
    }
};
