<?php

namespace App\Observers;

use App\Models\EmpIncome;
use App\Services\EmployeeService;

class EmpIncomeObserver
{
    protected EmployeeService $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    /**
     * Handle the EmpIncome "saved" event (covers created and updated).
     */
    public function saved(EmpIncome $empIncome): void
    {
        $this->employeeService->syncBaseRatesFromIncome($empIncome);
    }

    /**
     * Handle the EmpIncome "deleted" event.
     */
    public function deleted(EmpIncome $empIncome): void
    {
        $this->employeeService->handleIncomeDeletion($empIncome);
    }
}
