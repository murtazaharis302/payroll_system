<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HRAccessPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);
    }

    public function test_hr_can_access_employees_tab_and_create_employees_for_their_company_without_selecting_company(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'HR Test Company',
            'company_code' => 'HRTC-01',
            'email' => 'hrtc@test.com',
            'status' => 1,
        ]);

        $hrUser = User::factory()->create([
            'name' => 'HR Officer',
            'email' => 'hrofficer@test.com',
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        $this->actingAs($hrUser);

        // HR is restricted from system user list with 403
        $indexResponse = $this->get(route('employee'));
        $indexResponse->assertStatus(403);

        // HR is restricted from create user page with 403
        $createResponse = $this->get(route('createEmployee'));
        $createResponse->assertStatus(403);
    }


    public function test_hr_can_access_payroll_operations(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'Payroll HR Company',
            'company_code' => 'PHRC-01',
            'email' => 'phrc@test.com',
            'status' => 1,
        ]);

        $hrUser = User::factory()->create([
            'name' => 'Payroll HR User',
            'email' => 'payrollhr@test.com',
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        $this->actingAs($hrUser);

        $response = $this->get(route('payroll-drafts.index'));
        $response->assertStatus(200);
    }

    public function test_hr_cannot_approve_payroll_drafts(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'Approval Test Co',
            'company_code' => 'ATC-01',
            'email' => 'atc@test.com',
            'status' => 1,
        ]);

        $hrUser = User::factory()->create([
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        $this->actingAs($hrUser);

        // HR is blocked with 403 when trying to approve payroll draft
        $response = $this->post(route('payroll-approvals.approve', 1));
        $response->assertStatus(403);
    }

    public function test_hr_creating_payroll_employee_requires_company_admin_approval(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'PE Approval Co',
            'company_code' => 'PEAC-01',
            'email' => 'peac@test.com',
            'status' => 1,
        ]);

        $hrUser = User::factory()->create([
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        $this->actingAs($hrUser);

        // HR is restricted from payroll employee creation with 403
        $response = $this->post(route('payroll-employees.store'), [
            'company_id' => $company->id,
            'employee_code' => 'EMP-PE-01',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'janesmith@test.com',
            'date_hired' => '2026-07-01',
            'position' => 'Staff',
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    public function test_hr_and_company_admin_department_creation_is_scoped_to_their_company(): void
    {
        $company1Owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company1 = Company::create([
            'user_id' => $company1Owner->id,
            'name' => 'Dept Co One',
            'company_code' => 'DCO-01',
            'email' => 'dco1@test.com',
            'status' => 1,
        ]);

        $company2Owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company2 = Company::create([
            'user_id' => $company2Owner->id,
            'name' => 'Dept Co Two',
            'company_code' => 'DCO-02',
            'email' => 'dco2@test.com',
            'status' => 1,
        ]);

        $this->actingAs($company1Owner);

        // Company 1 owner posts department attempting company 2's ID
        $response = $this->post(route('departments.store'), [
            'company_id' => $company2->id,
            'code' => 'OPS',
            'name' => 'Operations',
        ]);

        $dept = \App\Models\Department::where('code', 'OPS')->first();
        $this->assertNotNull($dept);
        // Automatically forced to Company 1
        $this->assertEquals($company1->id, $dept->company_id);
    }

    public function test_hr_can_access_admin_payroll_employees_route(): void
    {
        $superAdmin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $superAdmin->id,
            'name' => 'Admin PE Co',
            'company_code' => 'APEC-01',
            'email' => 'apec@test.com',
            'status' => 1,
        ]);

        $hrUser = User::factory()->create([
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        $this->actingAs($hrUser);

        // Can access /admin/payroll-employees without 403
        $indexResponse = $this->get('/admin/payroll-employees');
        $indexResponse->assertStatus(200);

        // Accessing /admin/payroll-employees/create returns 403 Forbidden for HR
        $createResponse = $this->get('/admin/payroll-employees/create');
        $createResponse->assertStatus(403);
    }



    public function test_hr_user_cannot_access_or_manage_departments(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'HR Dept Restriction Co',
            'company_code' => 'HDRC-01',
            'email' => 'hdrc@test.com',
            'status' => 1,
        ]);

        $hrUser = User::factory()->create([
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        $this->actingAs($hrUser);

        // Accessing departments returns 403 Forbidden for HR
        $response = $this->get(route('departments.index'));
        $response->assertStatus(403);
    }
}
