<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use Tests\TestCase;

class RolePortalLoginTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $companyUser;
    protected User $hrUser;
    protected User $employeeUser;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        // Create Company Owner
        $this->companyUser = User::factory()->create([
            'email' => 'company@portaltest.com',
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'role' => 'company',
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyUser->id,
            'name' => 'Portal Test Corp',
            'company_code' => 'TEST-0001',
            'email' => 'company@portaltest.com',
            'status' => 1,
        ]);
        $this->companyUser->update(['company_id' => $this->company->id]);

        // Create Admin
        $this->adminUser = User::factory()->create([
            'email' => 'admin@portaltest.com',
            'password' => Hash::make('password123'),
            'role_id' => 1,
            'role' => 'admin',
            'status' => 1,
        ]);

        // Create HR
        $this->hrUser = User::factory()->create([
            'email' => 'hr@portaltest.com',
            'password' => Hash::make('password123'),
            'role_id' => 3,
            'role' => 'hr',
            'status' => 1,
            'company_id' => $this->company->id,
        ]);

        // Create Employee
        $this->employeeUser = User::factory()->create([
            'email' => 'employee@portaltest.com',
            'password' => Hash::make('password123'),
            'role_id' => 4,
            'role' => 'employee',
            'status' => 1,
            'company_id' => $this->company->id,
        ]);
    }

    /** Valid Portal Logins */

    public function test_admin_can_login_on_admin_portal(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'admin@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($this->adminUser);
        $response->assertRedirect('admin/dashboard');
    }

    public function test_company_can_login_on_company_portal(): void
    {
        $response = $this->post(route('companyLogin'), [
            'company_code' => 'TEST-0001',
            'email' => 'company@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($this->companyUser);
        $response->assertRedirect('company/dashboard');
    }

    public function test_hr_can_login_on_hr_portal(): void
    {
        $response = $this->post(route('hrLogin'), [
            'email' => 'hr@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($this->hrUser);
        $response->assertRedirect('hr/dashboard');
    }

    public function test_employee_can_login_on_employee_portal(): void
    {
        $response = $this->post(route('employeeLogin'), [
            'email' => 'employee@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($this->employeeUser);
        $response->assertRedirect('employee/dashboard');
    }

    /** Invalid Cross-Portal Logins - Employee trying other portals */

    public function test_employee_cannot_login_on_hr_portal(): void
    {
        $response = $this->post(route('hrLogin'), [
            'email' => 'employee@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an Employee account', session('statusDanger'));
        $this->assertStringContainsString('Click here to go to Employee portal', session('statusDanger'));
        $this->assertStringContainsString(url('employee/login'), session('statusDanger'));
    }

    public function test_employee_cannot_login_on_admin_portal(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'employee@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an Employee account', session('statusDanger'));
    }

    public function test_employee_cannot_login_on_company_portal(): void
    {
        $response = $this->post(route('companyLogin'), [
            'company_code' => 'TEST-0001',
            'email' => 'employee@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an Employee account', session('statusDanger'));
    }

    /** Invalid Cross-Portal Logins - HR trying other portals */

    public function test_hr_cannot_login_on_employee_portal(): void
    {
        $response = $this->post(route('employeeLogin'), [
            'email' => 'hr@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an HR account', session('statusDanger'));
        $this->assertStringContainsString(url('hr/login'), session('statusDanger'));
    }

    public function test_hr_cannot_login_on_admin_portal(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'hr@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an HR account', session('statusDanger'));
    }

    public function test_hr_cannot_login_on_company_portal(): void
    {
        $response = $this->post(route('companyLogin'), [
            'company_code' => 'TEST-0001',
            'email' => 'hr@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an HR account', session('statusDanger'));
    }

    /** Invalid Cross-Portal Logins - Company trying other portals */

    public function test_company_cannot_login_on_admin_portal(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'company@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is a Company account', session('statusDanger'));
        $this->assertStringContainsString(url('company/login'), session('statusDanger'));
    }

    public function test_company_cannot_login_on_hr_portal(): void
    {
        $response = $this->post(route('hrLogin'), [
            'email' => 'company@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is a Company account', session('statusDanger'));
    }

    public function test_company_cannot_login_on_employee_portal(): void
    {
        $response = $this->post(route('employeeLogin'), [
            'email' => 'company@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is a Company account', session('statusDanger'));
    }

    /** Invalid Cross-Portal Logins - Admin trying other portals */

    public function test_admin_cannot_login_on_company_portal(): void
    {
        $response = $this->post(route('companyLogin'), [
            'company_code' => 'TEST-0001',
            'email' => 'admin@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an Admin account', session('statusDanger'));
        $this->assertStringContainsString(url('admin/login'), session('statusDanger'));
    }

    public function test_admin_cannot_login_on_hr_portal(): void
    {
        $response = $this->post(route('hrLogin'), [
            'email' => 'admin@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an Admin account', session('statusDanger'));
    }

    public function test_admin_cannot_login_on_employee_portal(): void
    {
        $response = $this->post(route('employeeLogin'), [
            'email' => 'admin@portaltest.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('This account is an Admin account', session('statusDanger'));
    }
}
