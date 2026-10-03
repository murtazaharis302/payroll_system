<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TriDirectionalRateCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $companyAdmin;
    protected User $hrUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected IncomeType $basicIncomeType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);

        $adminSystemUser = User::create([
            'name' => 'System Owner',
            'email' => 'sysowner@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id' => $adminSystemUser->id,
            'name' => 'Tri-Directional Test Company',
            'email' => 'tridir@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'email' => 'compadmin_tri@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->hrUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'HR User',
            'email' => 'hr_tri@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-TRI-001',
            'first_name' => 'Arthur',
            'last_name' => 'Dent',
            'date_hired' => '2026-01-01',
            'position' => 'Traveler',
            'monthly_rate' => 26000.00,
            'daily_rate' => 1000.00,
            'hourly_rate' => 125.00,
            'status' => 'active',
        ]);

        $this->basicIncomeType = IncomeType::where('code', 'BASIC')->firstOrFail();
    }

    public function test_company_admin_can_update_base_rates_via_tri_directional_calculator(): void
    {
        // Target 1: Type 100 in Hourly Rate -> Daily Rate = 800.00, Monthly Basic Salary = 20,800.00
        $response = $this->actingAs($this->companyAdmin)->postJson(
            route('payroll-employees.update-record', $this->employee->id),
            [
                'employee_code' => 'EMP-TRI-001',
                'first_name' => 'Arthur',
                'last_name' => 'Dent',
                'status' => 'active',
                'hourly_rate' => 100.00,
                'daily_rate' => 800.00,
                'monthly_rate' => 20800.00,
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        $this->employee->refresh();
        $this->assertEquals(20800.00, (float) $this->employee->monthly_rate);
        $this->assertEquals(800.00, (float) $this->employee->daily_rate);
        $this->assertEquals(100.00, (float) $this->employee->hourly_rate);
    }

    public function test_hr_user_is_blocked_from_submitting_rate_attributes(): void
    {
        $response = $this->actingAs($this->hrUser)->postJson(
            route('payroll-employees.update-record', $this->employee->id),
            [
                'employee_code' => 'EMP-TRI-001',
                'first_name' => 'Arthur',
                'last_name' => 'Dent',
                'status' => 'active',
                'monthly_rate' => 99999.00,
                'daily_rate' => 9999.00,
                'hourly_rate' => 999.00,
            ]
        );

        $response->assertStatus(403);

        // Master rates must remain unchanged
        $this->employee->refresh();
        $this->assertEquals(26000.00, (float) $this->employee->monthly_rate);
        $this->assertEquals(1000.00, (float) $this->employee->daily_rate);
        $this->assertEquals(125.00, (float) $this->employee->hourly_rate);
    }

    public function test_hr_user_maintenance_view_has_zero_rate_dom_elements(): void
    {
        $response = $this->actingAs($this->hrUser)->get(route('payroll-employees.index'));

        $response->assertStatus(403);
    }

    public function test_company_admin_maintenance_view_renders_unlocked_rate_inputs(): void
    {
        $response = $this->actingAs($this->companyAdmin)->get(route('payroll-employees.index'));

        $response->assertStatus(200);
        $response->assertSee('Base Compensation Rates');
        $response->assertSee('id="monthly_rate"', false);
        $response->assertSee('id="daily_rate"', false);
        $response->assertSee('id="hourly_rate"', false);
    }
}
