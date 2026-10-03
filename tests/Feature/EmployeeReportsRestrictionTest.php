<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmployeeReportsRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $companyUser;
    protected User $employeeUser;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyUser = User::create([
            'name' => 'Company Admin',
            'email' => 'company@payroll.test',
            'password' => bcrypt('password'),
            'role' => 'company',
            'status' => '1',
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyUser->id,
            'company_code' => 'COMP-RESTRICT',
            'name' => 'Restriction Test Co',
            'email' => 'info@restrict.test',
            'phone' => '123456',
            'website' => 'https://restrict.test',
            'status' => '1',
        ]);

        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@payroll.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => '1',
        ]);

        $this->employeeUser = User::create([
            'name' => 'Regular Employee User',
            'email' => 'employee@payroll.test',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'company_id' => $this->company->id,
            'status' => '1',
        ]);
    }

    public function test_employee_role_user_cannot_access_reports_hub(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(panelRoute('reports.index'));
        $response->assertStatus(403);
    }

    public function test_employee_role_user_cannot_submit_report_generation_requests(): void
    {
        $response = $this->actingAs($this->employeeUser)->post(panelRoute('reports.payslip'), [
            'payroll_period_id' => 1,
            'employee_id' => 1,
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_and_company_users_can_access_reports_hub(): void
    {
        $adminResponse = $this->actingAs($this->adminUser)->get(panelRoute('reports.index'));
        $adminResponse->assertStatus(200);

        $companyResponse = $this->actingAs($this->companyUser)->get(panelRoute('reports.index'));
        $companyResponse->assertStatus(200);
    }
}
