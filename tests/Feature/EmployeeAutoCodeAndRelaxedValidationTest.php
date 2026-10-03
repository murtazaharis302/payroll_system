<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAutoCodeAndRelaxedValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id' => $this->user->id,
            'name' => 'Auto Code Test Company',
            'email' => 'autocode@example.com',
            'company_code' => 'COMP-8888',
            'status' => 1,
        ]);
    }

    public function test_creates_employee_with_only_required_names_and_auto_generates_code(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('payroll-employees.store'), [
                'company_id' => $this->company->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                // employee_code, date_hired, position left blank
            ]);

        $response->assertRedirect(route('payroll-employees.index'));

        $this->assertDatabaseHas('employees_master', [
            'company_id' => $this->company->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_hired' => null,
            'position' => null,
        ]);

        $createdEmployee = EmployeeMaster::where('first_name', 'John')->where('last_name', 'Doe')->first();
        $this->assertNotNull($createdEmployee);
        $this->assertNotEmpty($createdEmployee->employee_code);
        $this->assertStringStartsWith('EMP-', $createdEmployee->employee_code);
    }

    public function test_preserves_custom_employee_code_when_supplied(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('payroll-employees.store'), [
                'company_id' => $this->company->id,
                'employee_code' => 'EMP-CUSTOM-99',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
            ]);

        $response->assertRedirect(route('payroll-employees.index'));

        $this->assertDatabaseHas('employees_master', [
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-CUSTOM-99',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }
}
