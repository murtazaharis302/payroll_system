<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyAdminIncomeDefaultsPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected Menu $incomeInputMenu;
    protected Menu $incomeRateVisibilityMenu;

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
            'email' => 'compadmin_perm@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Perm Test Corp',
            'company_code' => 'PERMCORP',
            'email' => 'permcorp@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->incomeInputMenu = Menu::where('module', 'payroll_income_inputs')->firstOrFail();
        $this->incomeRateVisibilityMenu = Menu::where('module', 'payroll_income_rate_visibility')->firstOrFail();
    }

    public function test_company_admin_with_income_inputs_disabled_cannot_save_defaults(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::COMPANY_ADMIN, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => false]
        );

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.save-company-income-defaults'), [
            'company_id' => $this->company->id,
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'number_input' => '1.0'],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_company_admin_with_rate_visibility_disabled_cannot_save_amounts_or_fixed_mode(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::COMPANY_ADMIN, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::COMPANY_ADMIN, 'menu_id' => $this->incomeRateVisibilityMenu->id],
            ['is_enabled' => false]
        );

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        // Attempt 1: submit use_fixed_income_amount = 1
        $res1 = $this->post(route('company.payroll-drafts.save-company-income-defaults'), [
            'company_id' => $this->company->id,
            'use_fixed_income_amount' => 1,
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'number_input' => '1.0'],
            ],
        ]);
        $res1->assertStatus(403);

        // Attempt 2: submit amount field
        $res2 = $this->post(route('company.payroll-drafts.save-company-income-defaults'), [
            'company_id' => $this->company->id,
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'amount' => '1000.00'],
            ],
        ]);
        $res2->assertStatus(403);

        // Attempt 3: submit rate field
        $res3 = $this->post(route('company.payroll-drafts.save-company-income-defaults'), [
            'company_id' => $this->company->id,
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'rate' => '1000.00'],
            ],
        ]);
        $res3->assertStatus(403);
    }

    public function test_company_admin_with_rate_visibility_disabled_can_save_number_inputs_and_preserves_existing_amounts(): void
    {
        $this->company->setIncomeDefaults([
            'use_fixed_income_amount' => false,
            'incomes' => [
                ['display' => true, 'income_type' => 'BASIC', 'amount' => 15000.00],
                ['display' => true, 'income_type' => 'ALLOWANCE', 'amount' => 500.00],
            ],
        ]);

        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::COMPANY_ADMIN, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::COMPANY_ADMIN, 'menu_id' => $this->incomeRateVisibilityMenu->id],
            ['is_enabled' => false]
        );

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.save-company-income-defaults'), [
            'company_id' => $this->company->id,
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'number_input' => '10.0'],
                ['display' => 1, 'income_type' => 'ALLOWANCE', 'number_input' => '2.5'],
            ],
        ]);

        $response->assertSessionHas('status');

        $this->company->refresh();
        $defaults = $this->company->income_defaults;
        $incomes = collect($defaults['incomes']);

        $basicDef = $incomes->firstWhere('income_type', 'BASIC');
        $this->assertEquals(10.0, $basicDef['number_input']);
        $this->assertEquals(15000.00, $basicDef['amount']);

        $allowanceDef = $incomes->firstWhere('income_type', 'ALLOWANCE');
        $this->assertEquals(2.5, $allowanceDef['number_input']);
        $this->assertEquals(500.00, $allowanceDef['amount']);
    }
}
