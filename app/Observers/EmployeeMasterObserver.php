<?php

namespace App\Observers;

use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Services\EmployeeService;

class EmployeeMasterObserver
{
    protected EmployeeService $employeeService;

    public function __construct(?EmployeeService $employeeService = null)
    {
        $this->employeeService = $employeeService ?? app(EmployeeService::class);
    }

    /**
     * Handle the EmployeeMaster "created" event.
     * Populate available active income types for the employee's company scope with safe zero initial rates.
     * Income Types + EmpIncome are the single authoritative source of truth for recurring earnings.
     */
    public function created(EmployeeMaster $employee): void
    {
        if (empty($employee->company_id)) {
            return;
        }

        $activeIncomeTypes = IncomeType::withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($query) use ($employee) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $employee->company_id);
            })
            ->get();

        foreach ($activeIncomeTypes as $incomeType) {
            $exists = EmpIncome::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('income_type_id', $incomeType->id)
                ->exists();

            if (!$exists) {
                $frequency = ($incomeType->system_key === 'overtime') ? 'per_occurrence' : 'monthly';

                $empIncome = new EmpIncome([
                    'employee_id'    => $employee->id,
                    'income_type_id' => $incomeType->id,
                    'rate'           => 0.00,
                    'amount'         => 0.00,
                    'frequency'      => $frequency,
                    'is_active'      => true,
                ]);
                $empIncome->saveQuietly();
            }
        }
    }
}
