<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\EmployeeMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HRDualPortalLoginTest extends TestCase
{
    use RefreshDatabase;

    protected User $companyUser;
    protected User $hrUser;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyUser = User::create([
            'name' => 'Company Owner',
            'email' => 'companyowner@test.com',
            'password' => bcrypt('password123'),
            'role' => 'company',
            'status' => '1',
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyUser->id,
            'company_code' => 'COMP-HRTEST',
            'name' => 'HR Dual Portal Test Co',
            'email' => 'info@hrtest.com',
            'phone' => '123456',
            'website' => 'https://hrtest.com',
            'status' => '1',
        ]);

        $this->hrUser = User::create([
            'name' => 'Sarah HR Manager',
            'email' => 'sarah.hr@test.com',
            'password' => bcrypt('password123'),
            'role' => 'hr',
            'company_id' => $this->company->id,
            'status' => '1',
        ]);

        // Link EmployeeMaster profile to HR user
        EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-HR01',
            'first_name' => 'Sarah',
            'last_name' => 'HR Manager',
            'email' => 'sarah.hr@test.com',
            'e_user_id' => $this->hrUser->id,
            'status' => 'active',
            'date_of_joining' => now()->subYears(2),
        ]);
    }

    public function test_hr_login_via_hr_portal_leads_to_hr_dashboard(): void
    {
        $response = $this->post(route('hrLogin'), [
            'email' => 'sarah.hr@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('hr/dashboard');
        $this->assertAuthenticatedAs($this->hrUser);
        $this->assertEquals('hr', session('login_portal'));
    }

    public function test_hr_login_via_employee_portal_leads_to_employee_dashboard(): void
    {
        $response = $this->post(route('employeeLogin'), [
            'email' => 'sarah.hr@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('employee/dashboard');
        $this->assertAuthenticatedAs($this->hrUser);
        $this->assertEquals('employee', session('login_portal'));

        // Verify HR user can view employee dashboard when logged in via employee portal
        $dashboardResponse = $this->actingAs($this->hrUser)->get(route('employee.dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Sarah');
    }
}
