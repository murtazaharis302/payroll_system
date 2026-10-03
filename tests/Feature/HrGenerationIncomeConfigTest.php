<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HrGenerationIncomeConfigTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected User $hrUser;
    protected PayrollPeriod $period;
    protected EmployeeMaster $employee;
    protected Menu $incomeInputMenu;
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

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'compadmin_gen@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Gen Test Corp',
            'company_code' => 'GENTEST',
            'email' => 'gen@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->hrUser = User::create([
            'name' => 'HR Staff',
            'email' => 'hr_gen@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company->id,
        ]);

        $dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'GENDEPT',
            'name' => 'Generation Dept',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 Cutoff Gen',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-GEN-1',
            'first_name' => 'Gen',
            'last_name' => 'Worker',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        $this->incomeInputMenu = Menu::where('module', 'payroll_income_inputs')->firstOrFail();
        $this->deductionInputMenu = Menu::where('module', 'payroll_deduction_inputs')->firstOrFail();
    }

    /**
     * Test 1: Authorized HR sees Income Configuration tab and selection checkboxes without amount inputs or rates.
     */
    public function test_authorized_hr_sees_income_configuration_without_amount_inputs(): void
    {
        // Enable permission
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->get(route('company.payroll-drafts.create'));
        $response->assertStatus(200);

        // Sees Income Configuration
        $response->assertSee('Income Configuration');
        $response->assertSee('incomes[0][display]');
        $response->assertSee('Number Input');
        $response->assertSee('name="incomes[0][number_input]"', false);

        // Does NOT see Manual Value / Amount column header, amount input fields, or save income defaults button
        $response->assertDontSee('Manual Value / Amount');
        $response->assertDontSee('name="incomes[0][amount]"', false);
        $response->assertDontSee('use_fixed_income_amount');
        $response->assertDontSee('data-rate');
        $response->assertDontSee('25000.00');
        $response->assertDontSee('<button type="button" onclick="saveCompanyIncomeDefaults()"', false);
    }

    /**
     * Test 2: Unauthorized HR does not see Income Configuration in draft generation view.
     */
    public function test_unauthorized_hr_does_not_see_income_configuration(): void
    {
        // Ensure permission disabled
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => false]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->get(route('company.payroll-drafts.create'));
        $response->assertStatus(200);

        // Does NOT see Income Configuration tab or card
        $response->assertDontSee('Income Configuration &amp; Source Mode', false);
        $response->assertDontSee('Deduction Configuration');
    }

    /**
     * Test 3: Authorized HR posting fixed monetary income amounts or fixed mode receives 403 Forbidden.
     */
    public function test_authorized_hr_posting_amount_or_fixed_mode_returns_403(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        // Attempt 1: submit use_fixed_income_amount = 1
        $res1 = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'use_fixed_income_amount' => '1',
        ]);
        $res1->assertStatus(403);

        // Attempt 2: submit incomes[0][amount] = 5000.00
        $res2 = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'amount' => '5000.00'],
            ],
        ]);
        $res2->assertStatus(403);

        // Attempt 3: a raw rate key is never accepted from HR either.
        $res3 = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'rate' => '5000.00'],
            ],
        ]);
        $res3->assertStatus(403);
    }

    /**
     * Test 4: Unauthorized HR posting income configuration payload receives 403 Forbidden.
     */
    public function test_unauthorized_hr_posting_income_config_returns_403(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => false]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC'],
            ],
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 5: Authorized HR generates payroll draft using selected income inclusions.
     */
    public function test_authorized_hr_generates_draft_using_selected_income_inclusions(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC'],
                ['display' => 0, 'income_type' => 'OVERTIME'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $this->assertDatabaseHas('pay_headers', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    /**
     * Test 6: Company Admin generates draft using fixed income amounts normally.
     */
    public function test_company_admin_uses_fixed_income_amounts_normally(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'use_fixed_income_amount' => '1',
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'amount' => '12345.67'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $this->assertDatabaseHas('pay_headers', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    public function test_income_and_deduction_generation_capabilities_are_independent(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->deductionInputMenu->id],
            ['is_enabled' => false]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);
        $incomeOnly = $this->get(route('company.payroll-drafts.create'));
        $incomeOnly->assertSee('Income Configuration')
            ->assertDontSee('Deduction Configuration')
            ->assertDontSee('Save as Company Defaults');

        $forbiddenDeduction = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'deductions' => [['display' => 1, 'deduction_type' => 'SSS', 'amount' => '100.00']],
        ]);
        $forbiddenDeduction->assertStatus(403);

        MenuRoleAccess::where('company_id', $this->company->id)
            ->where('role_id', Role::HR)
            ->where('menu_id', $this->incomeInputMenu->id)
            ->update(['is_enabled' => false]);
        MenuRoleAccess::where('company_id', $this->company->id)
            ->where('role_id', Role::HR)
            ->where('menu_id', $this->deductionInputMenu->id)
            ->update(['is_enabled' => true]);

        $deductionOnly = $this->get(route('company.payroll-drafts.create'));
        $deductionOnly->assertDontSee('Income Configuration')
            ->assertSee('Deduction Configuration')
            ->assertDontSee('Save as Company Defaults');

        $allowedDeduction = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'use_fixed_amount' => '1',
            'deductions' => [['display' => 1, 'deduction_type' => 'SSS', 'amount' => '100.00']],
        ]);
        $allowedDeduction->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));
    }

    public function test_deduction_capability_defaults_off_and_hr_with_neither_sees_no_configuration(): void
    {
        $this->assertFalse(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_deduction_inputs'));

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);
        $response = $this->get(route('company.payroll-drafts.create'));
        $response->assertDontSee('Income Configuration')
            ->assertDontSee('Deduction Configuration')
            ->assertDontSee('Save as Company Defaults');
    }

    public function test_privileged_users_see_both_company_default_actions(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);
        $response = $this->get(route('company.payroll-drafts.create'));
        $response->assertSee('saveCompanyIncomeDefaults()', false)
            ->assertSee('saveCompanyDefaults()', false);
    }

    public function test_hr_cannot_save_company_defaults_directly(): void
    {
        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $this->post(route('company.payroll-drafts.save-company-income-defaults'), [
            'company_id' => $this->company->id,
        ])->assertStatus(403);

        $this->post(route('company.payroll-drafts.save-company-deduction-defaults'), [
            'company_id' => $this->company->id,
        ])->assertStatus(403);
    }
}
