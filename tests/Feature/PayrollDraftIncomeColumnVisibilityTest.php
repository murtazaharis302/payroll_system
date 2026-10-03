<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MenusTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDraftIncomeColumnVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $superAdmin;
    protected User $companyAdmin;
    protected User $hrUser;
    protected EmployeeMaster $employee;
    protected PayrollPeriod $period;
    protected PayHeader $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenusTableSeeder::class);

        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id'  => Role::SUPER_ADMIN,
            'role'     => 'admin',
        ]);

        $this->company = Company::create([
            'name'         => 'Matrix Test Corp',
            'email'        => 'corp@testcorp.com',
            'status'       => '1',
            'user_id'      => $this->superAdmin->id,
            'company_code' => 'MATRIXTST',
        ]);

        $this->companyAdmin = User::create([
            'name'       => 'Company Admin',
            'email'      => 'admin@testcorp.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::COMPANY_ADMIN,
            'role'       => 'company',
            'company_id' => $this->company->id,
        ]);

        $this->hrUser = User::create([
            'name'       => 'HR Officer',
            'email'      => 'hr@testcorp.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::HR,
            'role'       => 'hr',
            'company_id' => $this->company->id,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'first_name'    => 'Maria',
            'last_name'     => 'Santos',
            'email'         => 'maria@testcorp.com',
            'employee_code' => 'EMP-777',
            'monthly_rate'  => 32000,
            'daily_rate'    => 1230.77,
            'hourly_rate'   => 153.85,
            'status'        => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id'   => $this->company->id,
            'period_name'  => 'Aug 1-15, 2026',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-15',
            'payout_date'  => '2026-08-20',
            'status'       => 'open',
        ]);

        $this->draft = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id'       => $this->employee->id,
            'days_worked'       => 10.5,
            'total_gross_pay'   => 16500.50,
            'total_deductions'  => 1200.00,
            'net_pay'           => 15300.50,
            'status'            => 'draft',
        ]);
    }

    private function setModuleAccess(string $module, bool $enabled, int $roleId = Role::HR): void
    {
        $menu = Menu::where('module', $module)->firstOrFail();
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => $roleId, 'menu_id' => $menu->id],
            ['is_enabled' => $enabled ? 1 : 0]
        );
    }

    public function test_new_capability_exists_under_payroll_drafts(): void
    {
        $draftsMenu = Menu::where('module', 'payroll_drafts')->firstOrFail();
        $incomeColumnMenu = Menu::where('module', 'payroll_income_column_visibility')->firstOrFail();

        $this->assertEquals('Payroll Draft — View Income Column', $incomeColumnMenu->name);
        $this->assertEquals($draftsMenu->id, $incomeColumnMenu->parent_id);
        $this->assertEquals(175, $incomeColumnMenu->order);
        $this->assertNull($incomeColumnMenu->route);
    }

    public function test_hr_sees_income_column_by_default_without_explicit_override(): void
    {
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_column_visibility'));

        $response = $this->actingAs($this->hrUser)->get(route('payroll-drafts.index'));
        $response->assertOk();
        $response->assertSee('<th scope="col">Income</th>', false);
        $response->assertSee('16,500.50');
        $response->assertDontSee('<th scope="col">Net Pay</th>', false);
        $response->assertDontSee('15,300.50');
    }

    public function test_explicitly_disabling_capability_hides_income_column_for_hr(): void
    {
        $this->setModuleAccess('payroll_income_column_visibility', false, Role::HR);

        $this->assertFalse(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_column_visibility'));

        $response = $this->actingAs($this->hrUser)->get(route('payroll-drafts.index'));
        $response->assertOk();
        $response->assertDontSee('<th scope="col">Income</th>', false);
        $response->assertDontSee('16,500.50');
        $response->assertDontSee('<th scope="col">Net Pay</th>', false);
    }

    public function test_explicitly_enabling_capability_shows_income_column_for_hr(): void
    {
        $this->setModuleAccess('payroll_income_column_visibility', true, Role::HR);

        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_column_visibility'));

        $response = $this->actingAs($this->hrUser)->get(route('payroll-drafts.index'));
        $response->assertOk();
        $response->assertSee('<th scope="col">Income</th>', false);
        $response->assertSee('16,500.50');
    }

    public function test_company_admin_and_super_admin_retain_income_and_net_pay_visibility_by_default(): void
    {
        // Company Admin
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->companyAdmin, 'payroll_income_column_visibility'));
        $adminResponse = $this->actingAs($this->companyAdmin)->get(route('payroll-drafts.index'));
        $adminResponse->assertOk();
        $adminResponse->assertSee('<th scope="col">Income</th>', false);
        $adminResponse->assertSee('16,500.50');
        $adminResponse->assertSee('<th scope="col">Net Pay</th>', false);
        $adminResponse->assertSee('15,300.50');

        // Super Admin
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->superAdmin, 'payroll_income_column_visibility'));
        $superResponse = $this->actingAs($this->superAdmin)->get(route('admin.payroll-drafts.index', ['company_id' => $this->company->id]));
        $superResponse->assertOk();
        $superResponse->assertSee('<th scope="col">Income</th>', false);
        $superResponse->assertSee('16,500.50');
        $superResponse->assertSee('<th scope="col">Net Pay</th>', false);
        $superResponse->assertSee('15,300.50');
    }

    public function test_payroll_income_rate_visibility_remains_independent_and_unchanged(): void
    {
        // HR with income column enabled by default still has payroll_income_rate_visibility false
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_column_visibility'));
        $this->assertFalse(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_rate_visibility'));

        // Editing view retains rate protection
        $editResponse = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', $this->draft->id));
        $editResponse->assertOk();
        $editResponse->assertDontSee('Rate (Fixed)');
        $editResponse->assertDontSee('Total (Calculated)');

        // Explicitly enabling payroll_income_rate_visibility does not conflict
        $this->setModuleAccess('payroll_income_rate_visibility', true, Role::HR);
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_rate_visibility'));
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_column_visibility'));

        $editResponseWithRates = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', $this->draft->id));
        $editResponseWithRates->assertOk();
        $editResponseWithRates->assertSee('Rate (Fixed)');
    }

    public function test_empty_table_colspan_dynamically_adjusts_for_different_column_combinations(): void
    {
        // Delete drafts to trigger empty state
        PayHeader::query()->delete();

        // 1. HR with Income ON (default): 7 base + 1 income + 0 net_pay = 8
        $hrResponse = $this->actingAs($this->hrUser)->get(route('payroll-drafts.index'));
        $hrResponse->assertOk();
        $hrResponse->assertSee('<td colspan="8" class="text-center">No payroll drafts found</td>', false);

        // 2. HR with Income OFF: 7 base + 0 income + 0 net_pay = 7
        $this->setModuleAccess('payroll_income_column_visibility', false, Role::HR);
        $hrOffResponse = $this->actingAs($this->hrUser)->get(route('payroll-drafts.index'));
        $hrOffResponse->assertOk();
        $hrOffResponse->assertSee('<td colspan="7" class="text-center">No payroll drafts found</td>', false);

        // 3. Company Admin with Income ON: 7 base + 1 income + 1 net_pay = 9
        $adminResponse = $this->actingAs($this->companyAdmin)->get(route('payroll-drafts.index'));
        $adminResponse->assertOk();
        $adminResponse->assertSee('<td colspan="9" class="text-center">No payroll drafts found</td>', false);
    }
}
