<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmpIncome;
use App\Models\EmpIn;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AssignedIncomeToBaseRateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected EmployeeMaster $employee;
    protected IncomeType $basicIncomeType;
    protected IncomeType $allowanceIncomeType;

    protected function setUp(): void
    {
        parent::setUp();

        $user = \App\Models\User::factory()->create();

        $this->company = Company::create([
            'user_id' => $user->id,
            'name' => 'Test Company',
            'email' => 'testcompany@example.com',
            'company_code' => 'COMP-0001',
            'status' => 1,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-101',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_hired' => '2026-01-01',
            'position' => 'Developer',
            'monthly_rate' => 0.00,
            'daily_rate' => 0.00,
            'hourly_rate' => 0.00,
        ]);

        $this->basicIncomeType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['name' => 'Basic Salary', 'is_active' => true]
        );

        $this->allowanceIncomeType = IncomeType::firstOrCreate(
            ['code' => 'ALLOWANCE'],
            ['name' => 'Transportation Allowance', 'is_active' => true]
        );
    }

    public function test_creating_basic_salary_income_syncs_master_base_rates(): void
    {
        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate' => 26000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $this->employee->refresh();

        $this->assertEquals(26000.00, (float) $this->employee->monthly_rate);
        $this->assertEquals(26000.00, (float) $this->employee->basic_salary);
        $this->assertEquals(1000.00, (float) $this->employee->daily_rate); // 26000 / 26
        $this->assertEquals(125.00, (float) $this->employee->hourly_rate); // 1000 / 8
    }

    public function test_creating_basic_salary_with_daily_frequency(): void
    {
        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate' => 800.00,
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $this->employee->refresh();

        $this->assertEquals(800.00, (float) $this->employee->daily_rate);
        $this->assertEquals(20800.00, (float) $this->employee->monthly_rate); // 800 * 26
        $this->assertEquals(100.00, (float) $this->employee->hourly_rate); // 800 / 8
    }

    public function test_updating_basic_salary_income_updates_rates_quietly(): void
    {
        $empIncome = EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate' => 26000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $empIncome->update(['rate' => 31200.00]);

        $this->employee->refresh();

        $this->assertEquals(31200.00, (float) $this->employee->monthly_rate);
        $this->assertEquals(1200.00, (float) $this->employee->daily_rate); // 31200 / 26
        $this->assertEquals(150.00, (float) $this->employee->hourly_rate); // 1200 / 8
    }

    public function test_non_basic_income_creation_does_not_alter_base_rates(): void
    {
        $this->employee->update([
            'monthly_rate' => 26000.00,
            'daily_rate' => 1000.00,
            'hourly_rate' => 125.00,
        ]);

        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->allowanceIncomeType->id,
            'rate' => 5000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $this->employee->refresh();

        $this->assertEquals(26000.00, (float) $this->employee->monthly_rate);
        $this->assertEquals(1000.00, (float) $this->employee->daily_rate);
        $this->assertEquals(125.00, (float) $this->employee->hourly_rate);
    }

    public function test_zero_rate_safeguard_preserves_existing_valid_rates(): void
    {
        Log::shouldReceive('warning')->once();

        // Establish initial valid rates
        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate' => 26000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $this->employee->refresh();
        $this->assertEquals(26000.00, (float) $this->employee->monthly_rate);

        // Attempt zero-rate update
        $income = EmpIncome::where('employee_id', $this->employee->id)->first();
        $income->update(['rate' => 0.00]);

        $this->employee->refresh();

        // Valid rates must be preserved
        $this->assertEquals(26000.00, (float) $this->employee->monthly_rate);
        $this->assertEquals(1000.00, (float) $this->employee->daily_rate);
        $this->assertEquals(125.00, (float) $this->employee->hourly_rate);
    }

    public function test_deletion_safeguard_maintains_last_valid_rate_and_logs_warning(): void
    {
        Log::shouldReceive('warning')->once();

        $income = EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate' => 26000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $this->employee->refresh();
        $this->assertEquals(26000.00, (float) $this->employee->monthly_rate);

        // Delete the basic salary income item
        $income->delete();

        $this->employee->refresh();

        // Existing master rates are preserved to prevent attendance penalty calculation breakage
        $this->assertEquals(26000.00, (float) $this->employee->monthly_rate);
        $this->assertEquals(1000.00, (float) $this->employee->daily_rate);
        $this->assertEquals(125.00, (float) $this->employee->hourly_rate);
    }

    public function test_empin_model_alias_functions_identically(): void
    {
        EmpIn::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate' => 52000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $this->employee->refresh();

        $this->assertEquals(52000.00, (float) $this->employee->monthly_rate);
        $this->assertEquals(2000.00, (float) $this->employee->daily_rate);
        $this->assertEquals(250.00, (float) $this->employee->hourly_rate);
    }
}
