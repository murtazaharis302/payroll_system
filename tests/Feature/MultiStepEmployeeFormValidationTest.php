<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiStepEmployeeFormValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => Role::SUPER_ADMIN], [
            'name' => 'super-admin',
            'display_name' => 'Super Admin',
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $role->id,
            'role' => 'company',
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Multi-Step Test Company',
            'company_name' => 'Multi-Step Test Company',
            'company_code' => 'COMP-STEP',
            'email' => 'step_test@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);
        $this->dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'code' => 'DEPT_M',
            'name' => 'MultiStep Dept',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Step 1 validation failure returns employee_form_step = 1 and preserves input.
     */
    public function test_step_1_validation_failure_returns_employee_form_step_1_and_preserves_input(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('company.payroll-employees.store'), [
                'company_id' => $this->company->id,
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['first_name']);
        $response->assertSessionHas('employee_form_step', 1);
        $response->assertSessionHas('statusDanger', 'Please complete the highlighted required fields before saving the employee.');
        $response->assertSessionHasInput('last_name', 'Doe');
    }

    /**
     * Test 2: Step 2 validation failure returns employee_form_step = 2.
     */
    public function test_step_2_validation_failure_returns_employee_form_step_2(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('company.payroll-employees.store'), [
                'company_id' => $this->company->id,
                'dept_id' => $this->dept->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'monthly_rate' => -500.00, // Invalid Step 2 rate
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['monthly_rate']);
        $response->assertSessionHas('employee_form_step', 2);
        $response->assertSessionHas('statusDanger', 'Please complete the highlighted required fields before saving the employee.');
        $response->assertSessionHasInput('first_name', 'John');
    }

    /**
     * Test 3: Step 3 validation failure returns employee_form_step = 3.
     */
    public function test_step_3_validation_failure_returns_employee_form_step_3(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('company.payroll-employees.store'), [
                'company_id' => $this->company->id,
                'dept_id' => $this->dept->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'monthly_rate' => 20000.00,
                'sss_number' => str_repeat('X', 100), // Exceeds max 50 chars for Step 3
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['sss_number']);
        $response->assertSessionHas('employee_form_step', 3);
        $response->assertSessionHas('statusDanger', 'Please complete the highlighted required fields before saving the employee.');
        $response->assertSessionHasInput('first_name', 'John');
    }

    /**
     * Test 4: Store endpoint rejects missing last_name, returns to Step 1, and does not create employee record.
     */
    public function test_store_endpoint_rejects_missing_last_name_returns_to_step_1_and_does_not_create_employee(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('company.payroll-employees.store'), [
                'company_id' => $this->company->id,
                'first_name' => 'bob',
                'last_name' => '', // Missing required last_name
                'monthly_rate' => 20000.00,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('last_name');
        $response->assertSessionHas('employee_form_step', 1);
        $response->assertSessionHasInput('first_name', 'bob');

        $this->assertDatabaseMissing('employees_master', [
            'first_name' => 'bob',
        ]);
    }
}
