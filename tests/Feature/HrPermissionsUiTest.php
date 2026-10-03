<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HrPermissionsUiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company1;
    protected Company $company2;
    protected User $superAdmin;
    protected User $companyAdmin;
    protected User $hrUser;
    protected Menu $incomeInputMenu;
    protected Menu $incomeRateVisibilityMenu;
    protected Menu $deductionInputMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);
        $this->seed(MenusTableSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_hrp@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'compadmin_hrp@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company1 = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Alpha Corp',
            'company_code' => 'ALPHA',
            'email' => 'alpha@corp.com',
            'status' => '1',
        ]);

        $this->companyAdmin->update(['company_id' => $this->company1->id]);
        $this->companyAdmin->refresh();

        $this->company2 = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Beta Corp',
            'company_code' => 'BETA',
            'email' => 'beta@corp.com',
            'status' => '1',
        ]);

        $this->hrUser = User::create([
            'name' => 'HR Staff',
            'email' => 'hr_hrp@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company1->id,
        ]);

        $this->incomeInputMenu = Menu::where('module', 'payroll_income_inputs')->firstOrFail();
        $this->incomeRateVisibilityMenu = Menu::where('module', 'payroll_income_rate_visibility')->firstOrFail();
        $this->deductionInputMenu = Menu::where('module', 'payroll_deduction_inputs')->firstOrFail();
    }

    /**
     * Test 1: Company Admin accesses /company/hr-permissions directly for their company.
     */
    public function test_company_admin_views_and_toggles_hr_permissions(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);

        // GET page
        $response = $this->get(route('company.hr-permissions.index'));
        $response->assertStatus(200);
        $response->assertSee('Payroll Income Input');
        $response->assertSee('Payroll Income Rate Visibility');
        $response->assertSee('Payroll Deduction Input');
        $response->assertSee('DISABLED (Default)');

        // Toggle permission ON
        $postRes = $this->post(route('company.hr-permissions.update'), [
            'company_id' => $this->company1->id,
            'payroll_income_inputs' => '1',
            'payroll_income_rate_visibility' => '1',
            'payroll_deduction_inputs' => '1',
        ]);

        $postRes->assertRedirect(route('company.hr-permissions.index'));

        // Assert record created in DB
        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->company1->id,
            'role_id' => Role::HR,
            'menu_id' => $this->incomeInputMenu->id,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->company1->id,
            'role_id' => Role::HR,
            'menu_id' => $this->incomeRateVisibilityMenu->id,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->company1->id,
            'role_id' => Role::HR,
            'menu_id' => $this->deductionInputMenu->id,
            'is_enabled' => 1,
        ]);

        // Helper check
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_inputs'));
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_rate_visibility'));
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_deduction_inputs'));
    }

    /**
     * Test: Company Admin owner with NULL company_id column on user model can manage HR permissions.
     */
    public function test_company_admin_owner_without_explicit_company_id_manages_hr_permissions(): void
    {
        $ownerAdmin = User::create([
            'name' => 'Owner Admin',
            'email' => 'owner_admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => null,
        ]);

        $ownerCompany = Company::create([
            'user_id' => $ownerAdmin->id,
            'name' => 'Owner Corp',
            'company_code' => 'OWNER',
            'email' => 'owner@corp.com',
            'status' => '1',
        ]);

        $this->actingAs($ownerAdmin);

        $response = $this->get(route('company.hr-permissions.index'));
        $response->assertStatus(200);
        $response->assertSee('Owner Corp');

        $postRes = $this->post(route('company.hr-permissions.update'), [
            'company_id' => $ownerCompany->id,
            'payroll_income_inputs' => '1',
        ]);

        $postRes->assertRedirect(route('company.hr-permissions.index'));

        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $ownerCompany->id,
            'role_id' => Role::HR,
            'menu_id' => $this->incomeInputMenu->id,
            'is_enabled' => 1,
        ]);
    }

    /**
     * Test: Company Admin with role string company_admin can manage HR permissions.
     */
    public function test_company_admin_with_company_admin_role_string_manages_hr_permissions(): void
    {
        $caUser = User::create([
            'name' => 'Company Admin Role String',
            'email' => 'ca_string@test.com',
            'password' => Hash::make('password'),
            'role' => 'company_admin',
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => $this->company1->id,
        ]);

        $this->actingAs($caUser);

        $response = $this->get(route('company.hr-permissions.index'));
        $response->assertStatus(200);

        $postRes = $this->post(route('company.hr-permissions.update'), [
            'company_id' => $this->company1->id,
            'payroll_income_inputs' => '1',
        ]);

        $postRes->assertRedirect(route('company.hr-permissions.index'));
    }

    /**
     * Test: HR user is forbidden from managing HR permissions.
     */
    public function test_hr_user_is_forbidden_from_managing_hr_permissions(): void
    {
        $this->actingAs($this->hrUser);

        $response = $this->get(route('company.hr-permissions.index'));
        $response->assertStatus(403);
    }

    /**
     * Test 2: Super Admin accesses /admin/hr-permissions with company selector dropdown.
     */
    public function test_super_admin_manages_hr_permissions_with_company_selector(): void
    {
        $this->actingAs($this->superAdmin);

        // GET page with company2 selected
        $response = $this->get(route('admin.hr-permissions.index', ['company_id' => $this->company2->id]));
        $response->assertStatus(200);
        $response->assertSee('Beta Corp');

        // Toggle permission ON for Beta Corp
        $postRes = $this->post(route('admin.hr-permissions.update'), [
            'company_id' => $this->company2->id,
            'payroll_income_inputs' => '1',
            'payroll_income_rate_visibility' => '1',
            'payroll_deduction_inputs' => '1',
        ]);

        $postRes->assertRedirect(route('admin.hr-permissions.index', ['company_id' => $this->company2->id]));

        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->company2->id,
            'role_id' => Role::HR,
            'menu_id' => $this->incomeInputMenu->id,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->company2->id,
            'role_id' => Role::HR,
            'menu_id' => $this->incomeRateVisibilityMenu->id,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('menu_role_access', [
            'company_id' => $this->company2->id,
            'role_id' => Role::HR,
            'menu_id' => $this->deductionInputMenu->id,
            'is_enabled' => 1,
        ]);
    }

    /**
     * Test 3: Legacy /company/permissions-matrix redirects to /company/hr-permissions.
     */
    public function test_legacy_permissions_matrix_redirects_to_hr_permissions(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);

        $response = $this->get(route('company.permissions.matrix'));
        $response->assertRedirect(route('company.hr-permissions.index'));
    }

    /**
     * Test 4: Company Admin cannot manage HR permissions for another company.
     */
    public function test_company_admin_cannot_update_other_company_permissions(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);

        $response = $this->post(route('company.hr-permissions.update'), [
            'company_id' => $this->company2->id,
            'payroll_income_inputs' => '1',
        ]);

        $response->assertStatus(403);

        $foreignHr = User::create([
            'name' => 'Beta HR',
            'email' => 'beta_hr_hrp@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => Role::HR,
            'company_id' => $this->company2->id,
        ]);

        // A rejected cross-company request creates no grant for the other tenant.
        $this->assertFalse(MenuRoleAccess::hasModuleAccess($foreignHr, 'payroll_income_inputs'));
        $this->assertFalse(MenuRoleAccess::hasModuleAccess($foreignHr, 'payroll_income_rate_visibility'));
        $this->assertFalse(MenuRoleAccess::hasModuleAccess($foreignHr, 'payroll_deduction_inputs'));
    }

    public function test_super_admin_can_select_a_company_and_company_admins_see_both_default_actions(): void
    {
        $this->actingAs($this->superAdmin);
        $superResponse = $this->get(route('admin.payroll-drafts.create', ['company_id' => $this->company2->id]));
        $superResponse->assertStatus(200)
            ->assertSee('saveCompanyIncomeDefaults()', false)
            ->assertSee('saveCompanyDefaults()', false);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);
        $companyResponse = $this->get(route('company.payroll-drafts.create'));
        $companyResponse->assertStatus(200)
            ->assertSee('saveCompanyIncomeDefaults()', false)
            ->assertSee('saveCompanyDefaults()', false);
    }

    /**
     * Test 5: End-to-End: Company Admin enables permission -> HR accesses draft -> Income Grid is visible -> Number Input editable -> rate remains absent.
     */
    public function test_e2e_company_admin_enables_permission_hr_edits_draft_without_rate(): void
    {
        // 1. Company Admin logs in and enables HR Income Input permission
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);
        $postRes = $this->post(route('company.hr-permissions.update'), [
            'company_id' => $this->company1->id,
            'payroll_income_inputs' => '1',
        ]);
        $postRes->assertRedirect(route('company.hr-permissions.index'));

        // 2. Create employee & generate draft with a distinct rate
        $dept = Department::create([
            'company_id' => $this->company1->id,
            'code' => 'TESTDEPT',
            'name' => 'Test Department',
            'is_active' => true,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company1->id,
            'period_name' => 'August 2026 Cutoff B',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $distinctRate = 9876.54;
        $employee = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-E2E-1',
            'first_name' => 'E2E',
            'last_name' => 'Worker',
            'monthly_rate' => $distinctRate,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1, 'amount' => $distinctRate]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id], $options);
        $draft = $headers->first();

        // 3. HR logs in / accesses draft edit page
        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company1->id]);
        $viewRes = $this->get(route('admin.payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $period->id]));
        $viewRes->assertStatus(200);

        // Income Grid IS visible
        $viewRes->assertSee('Income / Earnings Grid');
        $viewRes->assertSee('pay_ins[');

        // Rate is ABSENT
        $viewRes->assertDontSee('data-rate');
        $viewRes->assertDontSee('9,876.54');
        $viewRes->assertDontSee('9876.54');
    }

    /**
     * Test 6: Legacy HR user with users.company_id = null resolves company via employee/owner relationship logic.
     */
    public function test_legacy_hr_user_with_null_company_id_resolves_company_via_employee_relationship(): void
    {
        // Create legacy HR user with null company_id
        $legacyHrUser = User::create([
            'name' => 'Legacy HR',
            'email' => 'legacyhr@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'company_id' => null, // Legacy null company_id
        ]);

        $dept = Department::create([
            'company_id' => $this->company1->id,
            'code' => 'LEGDEPT',
            'name' => 'Legacy HR Dept',
            'is_active' => true,
        ]);

        // Link legacy HR user to an Employee record (owner_user_id = Company Admin)
        Employee::create([
            'owner_user_id' => $this->companyAdmin->id,
            'e_user_id' => $legacyHrUser->id,
            'designation' => 'HR Specialist',
            'id_number' => 'EMP-LEG-HR-1',
            'status' => 'active',
        ]);

        // Also link legacy HR user to an EmployeeMaster record belonging to company1 via emp_id
        $empMaster = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-LEG-HR',
            'first_name' => 'Legacy',
            'last_name' => 'HRStaff',
            'status' => 'active',
        ]);

        $legacyHrUser->update(['emp_id' => $empMaster->id]);

        // Enable permission for company1
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company1->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );

        $this->actingAs($legacyHrUser)->withSession(['active_company_id' => $this->company1->id]);

        // hasModuleAccess must return true for legacy HR user
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($legacyHrUser, 'payroll_income_inputs'));
    }
}
