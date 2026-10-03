<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
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

class HrIncomeInputPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $companyAdmin;
    protected User $hrUser;
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
            'email' => 'compadmin_hrperm@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'HR Perm Test Corp',
            'company_code' => 'HRPERM',
            'email' => 'hrperm@corp.com',
            'status' => '1',
        ]);

        $this->companyAdmin->update(['company_id' => $this->company->id]);
        $this->companyAdmin->refresh();

        $this->hrUser = User::create([
            'name' => 'HR User',
            'email' => 'hruser_hrperm@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company->id,
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'HRDEPT',
            'name' => 'Human Resources',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->incomeInputMenu = Menu::where('module', 'payroll_income_inputs')->firstOrFail();
        $this->incomeRateVisibilityMenu = Menu::where('module', 'payroll_income_rate_visibility')->firstOrFail();

        session(['active_company_id' => $this->company->id]);
    }

    /**
     * Test 1: Capability defaults to OFF for HR until Company Admin enables it.
     */
    public function test_payroll_income_inputs_defaults_to_off_for_hr(): void
    {
        $this->assertFalse(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_inputs'));
        $this->assertFalse(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_rate_visibility'));
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->companyAdmin, 'payroll_income_inputs'));
        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->companyAdmin, 'payroll_income_rate_visibility'));
    }

    /**
     * Test 2: Company Admin enables permission -> HR can edit Number Input.
     */
    public function test_company_admin_enables_permission_hr_can_edit_number_input(): void
    {
        // Company Admin enables permission
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );

        $this->assertTrue(MenuRoleAccess::hasModuleAccess($this->hrUser, 'payroll_income_inputs'));

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-HRPERM-1',
            'first_name' => 'HR',
            'last_name' => 'Editable',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'OVERTIME', 'display' => 1]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $otPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->firstOrFail();

        $this->actingAs($this->hrUser);

        // HR views edit page
        $viewRes = $this->get(route('admin.payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $this->period->id]));
        $viewRes->assertStatus(200);

        // HR submits pay_ins with number_input
        $updateRes = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $otPayIn->id, 'number_input' => 8.5],
            ],
        ]);

        $updateRes->assertRedirect(route('payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $this->period->id]));

        $otPayIn->refresh();
        $this->assertEquals(8.5, (float) $otPayIn->number_input);
    }

    /**
     * Test 3: Company Admin disables permission / default OFF -> HR cannot edit Number Input via POST/PUT.
     */
    public function test_hr_cannot_edit_income_number_input_when_permission_disabled(): void
    {
        // Ensure permission is explicitly OFF
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => false]
        );

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-HRPERM-2',
            'first_name' => 'HR',
            'last_name' => 'Disabled',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'OVERTIME', 'display' => 1]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $otPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->firstOrFail();

        $this->actingAs($this->hrUser);

        // HR attempts direct PUT request to modify pay_ins
        $response = $this->putJson(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $otPayIn->id, 'number_input' => 12.0],
            ],
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 4: Backend Rate Confidentiality Enforcement -> HR submitting rate in pay_ins is rejected with 403.
     */
    public function test_hr_submitting_rate_in_pay_ins_is_rejected(): void
    {
        // Enable income input permission for HR
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-HRPERM-3',
            'first_name' => 'HR',
            'last_name' => 'RateAttempt',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $payIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->firstOrFail();

        $this->actingAs($this->hrUser);

        // HR sends rate key inside pay_ins
        $response = $this->putJson(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $payIn->id, 'number_input' => 10.0, 'rate' => 999.00],
            ],
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 5: View Rate Confidentiality -> HR page does not expose rate values or data-rate attributes.
     */
    public function test_hr_page_does_not_expose_rate_values_or_data_rate_attribute(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );

        $distinctRate = 8765.43;

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-HRPERM-CONF',
            'first_name' => 'Secret',
            'last_name' => 'Rate',
            'monthly_rate' => $distinctRate,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1, 'amount' => $distinctRate]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $this->actingAs($this->hrUser);

        $response = $this->get(route('admin.payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $this->period->id]));
        $response->assertStatus(200);

        // Assert HR view does NOT expose data-rate attribute or the distinct rate numeric text
        $response->assertDontSee('data-rate');
        $response->assertDontSee('8,765.43');
        $response->assertDontSee('8765.43');
    }

    /**
     * Test 6: Company Admin Continues to Function -> Can edit rates and number inputs regardless of HR toggle.
     */
    public function test_company_admin_continues_to_function_with_full_access(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ADMIN-FULL',
            'first_name' => 'Admin',
            'last_name' => 'FullAccess',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'OVERTIME', 'display' => 1]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $otPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->firstOrFail();

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        // Admin views edit page -> sees Rate and data-rate
        $viewRes = $this->get(route('admin.payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $this->period->id]));
        if ($viewRes->status() !== 200) {
            dd($viewRes->exception?->getMessage());
        }
        $viewRes->assertStatus(200);
        $viewRes->assertSee('data-rate');

        // Admin updates number_input and rate
        $updateRes = $this->withSession(['active_company_id' => $this->company->id])->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $otPayIn->id, 'number_input' => 10.0, 'rate' => 350.00],
            ],
        ]);

        $updateRes->assertRedirect(route('payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $this->period->id]));

        $otPayIn->refresh();
        $this->assertEquals(10.0, (float) $otPayIn->number_input);
        $this->assertEquals(350.00, (float) $otPayIn->rate);
    }

    public function test_rate_visibility_is_view_only_and_independent_from_income_input_permission(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeRateVisibilityMenu->id],
            ['is_enabled' => true]
        );

        $distinctRate = 8765.43;
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-HRPERM-RATE-VIEW',
            'first_name' => 'Rate',
            'last_name' => 'Viewer',
            'monthly_rate' => $distinctRate,
            'status' => 'active',
        ]);

        $headers = (new PayrollProcessingService())->generateDraft($this->period, [$employee->id], [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1, 'amount' => $distinctRate]],
        ]);
        $draft = $headers->firstOrFail();
        $payIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->firstOrFail();

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        // Visibility does not grant Income Configuration or quantity editing.
        $createResponse = $this->get(route('company.payroll-drafts.create'));
        $createResponse->assertDontSee('Income Configuration');

        $viewResponse = $this->get(route('admin.payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $this->period->id]));
        $viewResponse->assertStatus(200)
            ->assertSee('Income / Earnings Grid')
            ->assertSee('Rate (Fixed)')
            ->assertSee('8,765.43')
            ->assertDontSee('data-rate');

        // The visible Number Input remains disabled and rates remain immutable.
        $viewResponse->assertSee('id="pay_in_num_'.$payIn->id.'"', false)
            ->assertSee('disabled', false);

        $this->putJson(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [['id' => $payIn->id, 'number_input' => 10, 'rate' => 999]],
        ])->assertStatus(403);
    }
}
