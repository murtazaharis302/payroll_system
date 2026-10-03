<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $superAdmin;
    protected User $companyAdminA;
    protected User $companyAdminB;
    protected User $hrUserA;
    protected User $employeeUserA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MenusTableSeeder::class);

        $this->superAdmin = User::create([
            'name'       => 'Super Admin',
            'email'      => 'superadmin@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::SUPER_ADMIN,
            'role'       => 'admin',
        ]);

        $this->companyA = Company::create([
            'name'         => 'Company A',
            'email'        => 'compa@test.com',
            'status'       => '1',
            'user_id'      => $this->superAdmin->id,
            'company_code' => 'COMPA',
        ]);

        $this->companyB = Company::create([
            'name'         => 'Company B',
            'email'        => 'compb@test.com',
            'status'       => '1',
            'user_id'      => $this->superAdmin->id,
            'company_code' => 'COMPB',
        ]);

        $this->superAdmin->update(['company_id' => $this->companyA->id]);

        $this->companyAdminA = User::create([
            'name'       => 'Admin A',
            'email'      => 'admina@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::COMPANY_ADMIN,
            'role'       => 'company',
            'company_id' => $this->companyA->id,
        ]);

        $this->companyAdminB = User::create([
            'name'       => 'Admin B',
            'email'      => 'adminb@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::COMPANY_ADMIN,
            'role'       => 'company',
            'company_id' => $this->companyB->id,
        ]);

        $this->hrUserA = User::create([
            'name'       => 'HR User A',
            'email'      => 'hra@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::HR,
            'role'       => 'hr',
            'company_id' => $this->companyA->id,
        ]);

        $this->employeeUserA = User::create([
            'name'       => 'Employee A',
            'email'      => 'employeea@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::EMPLOYEE,
            'role'       => 'employee',
            'company_id' => $this->companyA->id,
        ]);
    }

    public function test_menu_model_stores_route_name_in_fillable(): void
    {
        $menu = Menu::create([
            'name'       => 'Test Custom Module',
            'route'      => 'company/custom-module',
            'route_name' => 'custom-module.index',
            'icon'       => 'fe-box',
            'order'      => 99,
            'module'     => 'custom_module',
            'is_active'  => true,
        ]);

        $this->assertEquals('custom-module.index', $menu->route_name);
        $this->assertDatabaseHas('menus', [
            'module'     => 'custom_module',
            'route_name' => 'custom-module.index',
        ]);
    }

    public function test_company_isolation_company_admin_cannot_toggle_another_companys_matrix(): void
    {
        $valeLoansMenu = Menu::where('module', 'vale_loans')->firstOrFail();

        // Company Admin A attempts to explicitly pass Company B's ID to toggle Company B's matrix
        $response = $this->actingAs($this->companyAdminA)->postJson(route('company.permissions.toggle'), [
            'company_id' => $this->companyB->id,
            'menu_id'    => $valeLoansMenu->id,
            'role_id'    => Role::HR,
            'is_enabled' => 1,
        ]);

        $response->assertStatus(200);

        // Verify that the record was created for Company A (Company Admin A's company), not Company B
        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->companyA->id,
            'menu_id'    => $valeLoansMenu->id,
            'role_id'    => Role::HR,
            'is_enabled' => 1,
        ]);

        $this->assertDatabaseMissing('menu_role_access', [
            'company_id' => $this->companyB->id,
            'menu_id'    => $valeLoansMenu->id,
            'role_id'    => Role::HR,
            'is_enabled' => 1,
        ]);
    }

    public function test_hr_and_employee_allow_and_deny_behavior(): void
    {
        $valeLoansMenu = Menu::where('module', 'vale_loans')->firstOrFail();

        // 1. Initially disabled for HR in Company A -> returns 403
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $valeLoansMenu->id],
            ['is_enabled' => 0]
        );

        $denyResponse = $this->actingAs($this->hrUserA)->get(route('admin.loan-monitor.index'));
        $denyResponse->assertStatus(403);

        // 2. Enable for HR in Company A -> returns 200 OK
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $valeLoansMenu->id],
            ['is_enabled' => 1]
        );

        $allowResponse = $this->actingAs($this->hrUserA)->get(route('admin.loan-monitor.index'));
        $allowResponse->assertStatus(200);
    }

    public function test_super_admin_global_bypass(): void
    {
        $valeLoansMenu = Menu::where('module', 'vale_loans')->firstOrFail();

        // Disable module for HR and Company
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $valeLoansMenu->id],
            ['is_enabled' => 0]
        );

        $response = $this->actingAs($this->superAdmin)->get(route('admin.loan-monitor.index'));
        $response->assertStatus(200);
    }

    public function test_company_admin_retains_access_to_permissions_matrix(): void
    {
        $permissionsMenu = Menu::where('module', 'permissions')->firstOrFail();

        $response = $this->actingAs($this->companyAdminA)->get(route('company.permissions.matrix'));
        $response->assertStatus(200);
    }

    public function test_hr_permission_controller_redirects_to_unified_matrix(): void
    {
        $response = $this->actingAs($this->companyAdminA)->get(route('company.hr-permissions.index'));
        $response->assertRedirect(route('company.permissions.matrix'));
    }

    public function test_super_admin_can_access_and_toggle_company_permissions_matrix(): void
    {
        $valeLoansMenu = Menu::where('module', 'vale_loans')->firstOrFail();

        // 1. Super Admin opens company.permissions.matrix
        $response = $this->actingAs($this->superAdmin)->get(route('company.permissions.matrix', ['company_id' => $this->companyB->id]));
        $response->assertStatus(200);

        // 2. Super Admin toggles role delegation for Company B
        $toggleResponse = $this->actingAs($this->superAdmin)->postJson(route('company.permissions.toggle'), [
            'company_id' => $this->companyB->id,
            'menu_id'    => $valeLoansMenu->id,
            'role_id'    => Role::HR,
            'is_enabled' => 1,
        ]);
        $toggleResponse->assertStatus(200);

        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->companyB->id,
            'menu_id'    => $valeLoansMenu->id,
            'role_id'    => Role::HR,
            'is_enabled' => 1,
        ]);
    }

    public function test_company_admin_tier1_entitlement_restriction(): void
    {
        $reportsMenu = Menu::where('module', 'reports')->firstOrFail();

        // 1. Super Admin disables Tier 1 entitlement for Company A
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::COMPANY_ADMIN, 'menu_id' => $reportsMenu->id],
            ['is_enabled' => 0]
        );

        // 2. Company Admin A tries to access reports -> 403 Forbidden
        $response = $this->actingAs($this->companyAdminA)->get(route('reports.index'));
        $response->assertStatus(403);
    }

    public function test_bulk_save_permissions_matrix(): void
    {
        $reportsMenu = Menu::where('module', 'reports')->firstOrFail();
        $attendanceMenu = Menu::where('module', 'attendance')->firstOrFail();

        $response = $this->actingAs($this->companyAdminA)->post(route('company.permissions.save'), [
            'company_id' => $this->companyA->id,
            'permissions' => [
                $reportsMenu->id => [
                    Role::HR => 1,
                    Role::EMPLOYEE => 1,
                ],
                $attendanceMenu->id => [
                    Role::HR => 1,
                    Role::EMPLOYEE => 0,
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->companyA->id,
            'menu_id'    => $reportsMenu->id,
            'role_id'    => Role::EMPLOYEE,
            'is_enabled' => 1,
        ]);

        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->companyA->id,
            'menu_id'    => $attendanceMenu->id,
            'role_id'    => Role::EMPLOYEE,
            'is_enabled' => 0,
        ]);
    }

    public function test_hr_dashboard_route_is_protected_by_permissions_matrix(): void
    {
        $dashboardMenu = Menu::where('module', 'dashboard')->firstOrFail();

        // 1. Disable dashboard for HR in Company A
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $dashboardMenu->id],
            ['is_enabled' => 0]
        );

        // 2. HR User A attempts to access /hr/dashboard -> 403 Forbidden
        $response = $this->actingAs($this->hrUserA)->get(route('hr.dashboard'));
        $response->assertStatus(403);
    }

    public function test_hr_access_to_departments_module_controlled_by_permission_matrix(): void
    {
        $deptMenu = Menu::where('module', 'departments')->firstOrFail();

        // 1. Enable departments for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $deptMenu->id],
            ['is_enabled' => 1]
        );
        $this->actingAs($this->hrUserA)->get(route('departments.index'))->assertOk();

        // 2. Disable departments for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $deptMenu->id],
            ['is_enabled' => 0]
        );
        $this->actingAs($this->hrUserA)->get(route('departments.index'))->assertStatus(403);
    }

    public function test_hr_access_to_loan_types_module_controlled_by_permission_matrix(): void
    {
        $loanTypeMenu = Menu::where('module', 'loan_types')->firstOrFail();

        // 1. Enable loan_types for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $loanTypeMenu->id],
            ['is_enabled' => 1]
        );
        $this->actingAs($this->hrUserA)->get(route('loan-types.index'))->assertOk();

        // 2. Disable loan_types for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $loanTypeMenu->id],
            ['is_enabled' => 0]
        );
        $this->actingAs($this->hrUserA)->get(route('loan-types.index'))->assertStatus(403);
    }

    public function test_hr_access_to_payroll_approvals_module_controlled_by_permission_matrix(): void
    {
        $approvalMenu = Menu::where('module', 'payroll_approvals')->firstOrFail();

        // 1. Enable payroll approvals for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $approvalMenu->id],
            ['is_enabled' => 1]
        );
        $this->actingAs($this->hrUserA)->get(route('payroll-approvals.index'))->assertOk();

        // 2. Disable payroll approvals for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $approvalMenu->id],
            ['is_enabled' => 0]
        );
        $this->actingAs($this->hrUserA)->get(route('payroll-approvals.index'))->assertStatus(403);
    }

    public function test_hr_access_to_payroll_employees_module_controlled_by_permission_matrix(): void
    {
        $empMenu = Menu::where('module', 'payroll_employees')->firstOrFail();

        // 1. Enable payroll_employees for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $empMenu->id],
            ['is_enabled' => 1]
        );
        $this->actingAs($this->hrUserA)->get(route('company.payroll-employees.index'))->assertOk();

        // 2. Disable payroll_employees for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $empMenu->id],
            ['is_enabled' => 0]
        );
        $this->actingAs($this->hrUserA)->get(route('company.payroll-employees.index'))->assertStatus(403);
    }
}
