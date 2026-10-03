<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
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

class PayrollDraftBatchChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $companyAdmin;
    private User $hr;
    private Company $company;
    private PayrollPeriod $period;
    private Department $dept;
    private IncomeType $allowanceType;
    private IncomeType $bonusType;
    private DeductionType $uniformType;
    private PayHeader $draft1;
    private PayHeader $draft2;
    private PayHeader $draft3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);
        $this->seed(MenusTableSeeder::class);

        Role::firstOrCreate(['id' => Role::COMPANY_ADMIN, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => Role::HR, 'name' => 'HR']);

        $this->companyAdmin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@batchchange.test',
            'password' => Hash::make('secret'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Batch Test Corp',
            'company_code' => 'BATCH-TEST',
            'email' => 'batch@test.com',
            'status' => '1',
        ]);
        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->hr = User::create([
            'name' => 'HR Staff',
            'email' => 'hr@batchchange.test',
            'password' => Hash::make('secret'),
            'role' => 'hr',
            'role_id' => Role::HR,
            'company_id' => $this->company->id,
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $this->allowanceType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'MEAL_ALLOWANCE',
            'name' => 'Meal Allowance',
            'is_active' => true,
        ]);

        $this->bonusType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'PROJECT_BONUS',
            'name' => 'Project Bonus',
            'is_active' => true,
        ]);

        $this->uniformType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'UNIFORM',
            'name' => 'Uniform Deduction',
            'type' => 'other',
            'is_active' => true,
        ]);

        $this->draft1 = $this->createDraftForEmployee('EMP001', 'Alice', 'Smith', 10);
        $this->draft2 = $this->createDraftForEmployee('EMP002', 'Bob', 'Jones', 10);
        $this->draft3 = $this->createDraftForEmployee('EMP003', 'Charlie', 'Brown', 10);

        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_income_inputs', true);
        $this->setModuleAccess('payroll_deduction_inputs', true);
        $this->setModuleAccess('payroll_income_rate_visibility', true);
    }

    private function createDraftForEmployee(string $code, string $first, string $last, int $days, string $status = 'draft'): PayHeader
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => $code,
            'first_name' => $first,
            'last_name' => $last,
            'monthly_rate' => 30000,
            'daily_rate' => 1153.85,
            'hourly_rate' => 144.23,
            'status' => 'active',
        ]);

        $draft = PayHeader::create([
            'employee_id' => $emp->id,
            'payroll_period_id' => $this->period->id,
            'status' => $status,
            'days_worked' => $days,
            'lock_version' => 1,
        ]);

        $basic = IncomeType::where('code', 'BASIC')->firstOrFail();
        PayIn::create(['pay_id' => $draft->id, 'income_type_id' => $basic->id, 'number_input' => $days, 'rate' => 1153.85]);
        $draft->recalculateTotals();

        return $draft;
    }

    private function setModuleAccess(string $module, bool $enabled, int $roleId = Role::HR): void
    {
        $menu = Menu::where('module', $module)->firstOrFail();
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => $roleId, 'menu_id' => $menu->id],
            ['is_enabled' => $enabled ? 1 : 0]
        );
    }

    public function test_add_one_income_to_several_selected_employees_leaves_unselected_untouched(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);
        $emp3OriginalPayInsCount = $this->draft3->payIns()->count();
        $emp3OriginalGross = (float) $this->draft3->total_gross_pay;

        $response = $this->post(route('company.payroll-drafts.batch-change.store'), [
            'payroll_period_id' => $this->period->id,
            'income_type_ids' => [$this->allowanceType->id],
            'draft_ids' => [$this->draft1->id, $this->draft2->id],
            'income_type_configs' => [$this->allowanceType->id => ['number_input' => 5, 'rate' => 150.00]],
        ]);

        $response->assertRedirect(route('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('pay_ins', ['pay_id' => $this->draft1->id, 'income_type_id' => $this->allowanceType->id, 'number_input' => 5, 'rate' => 150.00]);
        $this->assertDatabaseHas('pay_ins', ['pay_id' => $this->draft2->id, 'income_type_id' => $this->allowanceType->id, 'number_input' => 5, 'rate' => 150.00]);
        $this->assertDatabaseMissing('pay_ins', ['pay_id' => $this->draft3->id, 'income_type_id' => $this->allowanceType->id]);
        $this->assertSame($emp3OriginalPayInsCount, $this->draft3->fresh()->payIns()->count());
        $this->assertSame($emp3OriginalGross, (float) $this->draft3->fresh()->total_gross_pay);
    }

    public function test_add_multiple_incomes_to_selected_employees(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.batch-change.store'), [
            'payroll_period_id' => $this->period->id,
            'income_type_ids' => [$this->allowanceType->id, $this->bonusType->id],
            'draft_ids' => [$this->draft1->id],
            'income_type_configs' => [
                $this->allowanceType->id => ['number_input' => 2, 'rate' => 200.00],
                $this->bonusType->id => ['number_input' => 1, 'rate' => 5000.00],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pay_ins', ['pay_id' => $this->draft1->id, 'income_type_id' => $this->allowanceType->id, 'rate' => 200.00]);
        $this->assertDatabaseHas('pay_ins', ['pay_id' => $this->draft1->id, 'income_type_id' => $this->bonusType->id, 'rate' => 5000.00]);
    }

    public function test_add_deductions_to_selected_employees(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.batch-change.store'), [
            'payroll_period_id' => $this->period->id,
            'deduction_type_ids' => [$this->uniformType->id],
            'draft_ids' => [$this->draft1->id, $this->draft2->id],
            'deduction_type_configs' => [
                $this->uniformType->id => ['number_input' => 1, 'rate' => 250.00],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pay_outs', ['pay_id' => $this->draft1->id, 'deduction_type_id' => $this->uniformType->id, 'rate' => 250.00]);
        $this->assertDatabaseHas('pay_outs', ['pay_id' => $this->draft2->id, 'deduction_type_id' => $this->uniformType->id, 'rate' => 250.00]);
    }

    public function test_one_submission_can_add_both_income_and_deduction_to_same_employees(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.batch-change.store'), [
            'payroll_period_id' => $this->period->id,
            'income_type_ids' => [$this->allowanceType->id],
            'income_type_configs' => [$this->allowanceType->id => ['number_input' => 3, 'rate' => 300.00]],
            'deduction_type_ids' => [$this->uniformType->id],
            'deduction_type_configs' => [$this->uniformType->id => ['number_input' => 1, 'rate' => 175.00]],
            'draft_ids' => [$this->draft1->id, $this->draft2->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pay_ins', ['pay_id' => $this->draft1->id, 'income_type_id' => $this->allowanceType->id, 'rate' => 300.00]);
        $this->assertDatabaseHas('pay_outs', ['pay_id' => $this->draft1->id, 'deduction_type_id' => $this->uniformType->id, 'rate' => 175.00]);
        $this->assertDatabaseHas('pay_ins', ['pay_id' => $this->draft2->id, 'income_type_id' => $this->allowanceType->id, 'rate' => 300.00]);
        $this->assertDatabaseHas('pay_outs', ['pay_id' => $this->draft2->id, 'deduction_type_id' => $this->uniformType->id, 'rate' => 175.00]);
    }

    public function test_duplicate_line_causes_complete_atomic_rollback(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        PayIn::create([
            'pay_id' => $this->draft2->id,
            'income_type_id' => $this->allowanceType->id,
            'number_input' => 1,
            'rate' => 100.00,
        ]);

        $response = $this->from(route('company.payroll-drafts.batch-change.create'))
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id, $this->draft2->id],
                'income_type_configs' => [$this->allowanceType->id => ['number_input' => 1, 'rate' => 150.00]],
            ]);

        $response->assertRedirect(route('company.payroll-drafts.batch-change.create'));
        $response->assertSessionHas('generation_errors');
        $this->assertDatabaseMissing('pay_ins', ['pay_id' => $this->draft1->id, 'income_type_id' => $this->allowanceType->id]);
    }

    public function test_missing_recurring_setup_produces_aggregated_errors(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->from(route('company.payroll-drafts.batch-change.create'))
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id, $this->draft2->id],
                'income_type_configs' => [$this->allowanceType->id => ['number_input' => 1]],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('generation_errors');
        $errors = session('generation_errors');
        $this->assertCount(2, $errors);
        $this->assertStringContainsString('no active Recurring Income rate', $errors[0]);
    }

    public function test_get_batch_change_page_loads_eligible_drafts_without_schema_error(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('company.payroll-drafts.batch-change.create', ['payroll_period_id' => $this->period->id]));

        $response->assertOk()
            ->assertSee('Make Batch Changes')
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSee('Charlie');
    }

    public function test_tampered_type_configs_for_unselected_types_are_rejected(): void
    {
        $otherIncomeType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'UNSELECTED_INC',
            'name' => 'Unselected Income',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id],
                'income_type_configs' => [
                    $this->allowanceType->id => ['number_input' => 1, 'rate' => 100],
                    $otherIncomeType->id => ['number_input' => 1, 'rate' => 999],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('generation_errors');
        $this->assertDatabaseMissing('pay_ins', ['pay_id' => $this->draft1->id, 'income_type_id' => $this->allowanceType->id]);
    }

    public function test_selecting_only_absences_submits_only_absences_config_and_keeps_rate(): void
    {
        $absencesType = DeductionType::firstOrCreate(
            ['code' => 'ABSENCES'],
            ['company_id' => $this->company->id, 'name' => 'Absences', 'is_active' => true, 'type' => 'statutory']
        );

        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$absencesType->id],
                'draft_ids' => [$this->draft1->id, $this->draft2->id],
                'deduction_type_configs' => [$absencesType->id => ['number_input' => 1, 'rate' => 1250.00]],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pay_outs', ['pay_id' => $this->draft1->id, 'deduction_type_id' => $absencesType->id, 'rate' => 1250.00]);
        $this->assertDatabaseHas('pay_outs', ['pay_id' => $this->draft2->id, 'deduction_type_id' => $absencesType->id, 'rate' => 1250.00]);
    }

    public function test_batch_change_blade_view_disables_unselected_type_configs_and_prefixes_dom_ids(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('company.payroll-drafts.batch-change.create', ['payroll_period_id' => $this->period->id]));

        $response->assertOk()
            ->assertSee('income_type_chk_' . $this->allowanceType->id, false)
            ->assertSee('deduction_type_chk_' . $this->uniformType->id, false)
            ->assertSee('disabled', false);
    }

    public function test_deduction_type_with_only_active_loan_type_mapping_renders_as_loan_backed_with_auto_rate(): void
    {
        $mappedDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'GENERIC_DED_01',
            'name' => 'Custom Mapped Advance',
            'type' => 'other', // type is not 'loan'
            'is_loan_enabled' => false, // is_loan_enabled is false
            'is_active' => true,
        ]);

        \App\Models\LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $mappedDeduction->id,
            'name' => 'Custom Loan Type Mapping',
            'default_amortization_per_cutoff' => 500,
            'is_active' => true,
        ]);

        $this->assertTrue($mappedDeduction->isLoanBacked());

        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('company.payroll-drafts.batch-change.create', [
                'payroll_period_id' => $this->period->id,
                'tab' => 'deductions',
            ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Custom Mapped Advance', $content);
        $this->assertStringContainsString('Auto (Active Loan / Cutoff)', $content);
        $this->assertStringNotContainsString('name="deduction_type_configs[' . $mappedDeduction->id . '][rate]"', $content);
    }

    public function test_forged_manual_rate_submission_for_loan_backed_deduction_is_rejected(): void
    {
        $mappedDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'LOAN_DED_FORGE',
            'name' => 'Loan Ded Forgery',
            'type' => 'loan',
            'is_loan_enabled' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$mappedDeduction->id],
                'draft_ids' => [$this->draft1->id],
                'deduction_type_configs' => [
                    $mappedDeduction->id => [
                        'number_input' => 1,
                        'rate' => 777.00, // Forged manual rate
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('generation_errors');
        $this->assertStringContainsStringIgnoringCase('manual rate/amount is not allowed for loan-backed deduction', session('generation_errors')[0]);
        $this->assertDatabaseMissing('pay_outs', ['pay_id' => $this->draft1->id, 'deduction_type_id' => $mappedDeduction->id]);
    }
}
