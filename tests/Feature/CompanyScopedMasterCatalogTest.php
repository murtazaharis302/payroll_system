<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyScopedMasterCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $companyAdminA;
    protected User $companyAdminB;
    protected User $superAdmin;
    protected IncomeType $globalIncome;
    protected DeductionType $globalDeduction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyAdminA = User::create([
            'name'     => 'Company Admin Alpha',
            'email'    => 'admin@alpha.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $this->companyAdminB = User::create([
            'name'     => 'Company Admin Beta',
            'email'    => 'admin@beta.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $this->companyA = Company::create([
            'name'         => 'Alpha Corp',
            'email'        => 'alpha@corp.test',
            'user_id'      => $this->companyAdminA->id,
            'company_code' => 'ALPHA001',
        ]);

        $this->companyB = Company::create([
            'name'         => 'Beta Corp',
            'email'        => 'beta@corp.test',
            'user_id'      => $this->companyAdminB->id,
            'company_code' => 'BETA001',
        ]);

        $this->companyAdminA->update(['company_id' => $this->companyA->id]);
        $this->companyAdminB->update(['company_id' => $this->companyB->id]);

        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'super@admin.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::SUPER_ADMIN,
            'role'     => 'admin',
            'status'   => 1,
        ]);

        $this->globalIncome = IncomeType::create([
            'company_id'  => null,
            'name'        => 'Basic Salary',
            'code'        => 'BASIC',
            'description' => 'System default basic income',
            'is_taxable'  => true,
            'is_active'   => true,
        ]);

        $this->globalDeduction = DeductionType::create([
            'company_id'  => null,
            'name'        => 'SSS Contribution',
            'code'        => 'SSS',
            'description' => 'System default statutory deduction',
            'type'        => 'statutory',
            'is_active'   => true,
        ]);
    }

    public function test_company_admin_can_create_custom_income_type_and_deduction_type(): void
    {
        $incResponse = $this->actingAs($this->companyAdminA)->post(route('company.income-types.store'), [
            'name'        => 'Project Milestone Bonus',
            'code'        => 'PROJ_BONUS',
            'description' => 'Special milestone bonus',
            'is_taxable'  => 1,
            'is_active'   => 1,
        ]);

        $incResponse->assertRedirect();
        $incResponse->assertSessionHas('statusSuccess');

        $this->assertDatabaseHas('income_types', [
            'name'       => 'Project Milestone Bonus',
            'code'       => 'PROJ_BONUS',
            'company_id' => $this->companyA->id,
            'created_by' => $this->companyAdminA->id,
        ]);

        $dedResponse = $this->actingAs($this->companyAdminA)->post(route('company.deduction-types.store'), [
            'name'        => 'Gym Membership',
            'code'        => 'GYM_FEE',
            'description' => 'Monthly gym perk deduction',
            'type'        => 'other',
            'is_active'   => 1,
        ]);

        $dedResponse->assertRedirect();
        $dedResponse->assertSessionHas('statusSuccess');

        $this->assertDatabaseHas('deduction_types', [
            'name'       => 'Gym Membership',
            'code'       => 'GYM_FEE',
            'company_id' => $this->companyA->id,
            'created_by' => $this->companyAdminA->id,
        ]);
    }

    public function test_custom_master_types_are_strictly_tenant_isolated(): void
    {
        $customInc = IncomeType::create([
            'company_id'  => $this->companyA->id,
            'created_by'  => $this->companyAdminA->id,
            'name'        => 'Alpha Special Allowance',
            'code'        => 'ALPHA_ALLOW',
            'is_taxable'  => false,
            'is_active'   => true,
        ]);

        $customDed = DeductionType::create([
            'company_id'  => $this->companyA->id,
            'created_by'  => $this->companyAdminA->id,
            'name'        => 'Alpha Uniform Fee',
            'code'        => 'ALPHA_UNIFORM',
            'type'        => 'other',
            'is_active'   => true,
        ]);

        // Company Admin A sees their custom items and global defaults
        $responseA = $this->actingAs($this->companyAdminA)->get(route('company.income-types.index'));
        $responseA->assertSee('Alpha Special Allowance');
        $responseA->assertSee('Basic Salary');

        $responseDedA = $this->actingAs($this->companyAdminA)->get(route('company.deduction-types.index'));
        $responseDedA->assertSee('Alpha Uniform Fee');
        $responseDedA->assertSee('SSS Contribution');

        // Company Admin B sees global defaults but NOT Company A's custom items
        $responseB = $this->actingAs($this->companyAdminB)->get(route('company.income-types.index'));
        $responseB->assertDontSee('Alpha Special Allowance');
        $responseB->assertSee('Basic Salary');

        $responseDedB = $this->actingAs($this->companyAdminB)->get(route('company.deduction-types.index'));
        $responseDedB->assertDontSee('Alpha Uniform Fee');
        $responseDedB->assertSee('SSS Contribution');

        // Company Admin B attempting to edit Company A's custom item receives 403
        $editAttempt = $this->actingAs($this->companyAdminB)->get(route('company.income-types.edit', $customInc->id));
        $this->assertTrue(in_array($editAttempt->status(), [403, 404], true));

        $updateAttempt = $this->actingAs($this->companyAdminB)->put(route('company.income-types.update', $customInc->id), [
            'name'        => 'Hacked Name',
            'code'        => 'ALPHA_ALLOW',
            'is_taxable'  => 1,
            'is_active'   => 1,
        ]);
        $this->assertTrue(in_array($updateAttempt->status(), [403, 404], true));

        $deleteAttempt = $this->actingAs($this->companyAdminB)->delete(route('company.income-types.destroy', $customInc->id));
        $this->assertTrue(in_array($deleteAttempt->status(), [403, 404], true));
    }

    public function test_company_admin_cannot_delete_global_system_defaults(): void
    {
        $response = $this->actingAs($this->companyAdminA)->delete(route('company.income-types.destroy', $this->globalIncome->id));
        $response->assertStatus(403);

        $responseDed = $this->actingAs($this->companyAdminA)->delete(route('company.deduction-types.destroy', $this->globalDeduction->id));
        $responseDed->assertStatus(403);
    }

    public function test_company_admin_can_toggle_active_status_on_own_custom_types(): void
    {
        $customInc = IncomeType::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Performance Incentive',
            'code'       => 'PERF_INC',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->companyAdminA)->post(route('company.income-types.toggle-status', $customInc->id));
        $response->assertRedirect();

        $this->assertDatabaseHas('income_types', [
            'id'        => $customInc->id,
            'is_active' => false,
        ]);
    }

    public function test_for_company_scope_retrieves_correct_selectable_types_in_emp_income(): void
    {
        IncomeType::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Alpha Meal Card',
            'code'       => 'ALPHA_MEAL',
            'is_active'  => true,
        ]);

        IncomeType::create([
            'company_id' => $this->companyB->id,
            'name'       => 'Beta Travel Allowance',
            'code'       => 'BETA_TRAVEL',
            'is_active'  => true,
        ]);

        $typesForA = IncomeType::forCompany($this->companyA->id)->where('is_active', true)->pluck('name')->toArray();
        $this->assertContains('Basic Salary', $typesForA);
        $this->assertContains('Alpha Meal Card', $typesForA);
        $this->assertNotContains('Beta Travel Allowance', $typesForA);

        $typesForB = IncomeType::forCompany($this->companyB->id)->where('is_active', true)->pluck('name')->toArray();
        $this->assertContains('Basic Salary', $typesForB);
        $this->assertContains('Beta Travel Allowance', $typesForB);
        $this->assertNotContains('Alpha Meal Card', $typesForB);
    }

    public function test_auto_generated_code_when_left_blank(): void
    {
        // Omit code for Income Type creation
        $incResponse = $this->actingAs($this->companyAdminA)->post(route('company.income-types.store'), [
            'name'        => 'Health Allowance',
            'code'        => '',
            'description' => 'Medical perk',
            'is_taxable'  => 0,
            'is_active'   => 1,
        ]);

        $incResponse->assertRedirect();
        $incResponse->assertSessionHas('statusSuccess');

        $this->assertDatabaseHas('income_types', [
            'name' => 'Health Allowance',
            'code' => 'HEALTH_ALLOWANCE',
        ]);

        // Omit code for Deduction Type creation
        $dedResponse = $this->actingAs($this->companyAdminA)->post(route('company.deduction-types.store'), [
            'name'        => 'Uniform Subsidy Fee',
            'code'        => null,
            'description' => 'Uniform fee',
            'type'        => 'other',
            'is_active'   => 1,
        ]);

        $dedResponse->assertRedirect();
        $dedResponse->assertSessionHas('statusSuccess');

        $this->assertDatabaseHas('deduction_types', [
            'name' => 'Uniform Subsidy Fee',
            'code' => 'UNIFORM_SUBSIDY_FEE',
        ]);
    }

    public function test_custom_income_and_deduction_types_are_visible_and_adjustable_in_payroll_draft_edit(): void
    {
        $customInc = IncomeType::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Project Milestone Bonus',
            'code'       => 'MILESTONE_BONUS',
            'is_taxable' => true,
            'is_active'  => true,
        ]);

        $customDec = DeductionType::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Gym Membership Fee',
            'code'       => 'GYM_FEE',
            'type'       => 'other',
            'is_active'  => true,
        ]);

        $period = \App\Models\PayrollPeriod::create([
            'company_id'   => $this->companyA->id,
            'period_name'  => 'Aug 1-15 2026',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-15',
            'pay_date'     => '2026-08-15',
            'status'       => 'open',
        ]);

        $emp = EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'employee_code' => 'EMP-1001',
            'monthly_rate'  => 20000.00,
        ]);

        $draft = \App\Models\PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id'       => $emp->id,
            'status'            => 'draft',
            'total_gross_pay'   => 1000.00,
            'total_deductions'  => 100.00,
            'net_pay'           => 900.00,
        ]);

        // Edit screen should display both custom income and deduction names
        $editResponse = $this->actingAs($this->companyAdminA)->get(route('company.payroll-drafts.edit', $draft->id));
        $editResponse->assertOk();
        $editResponse->assertSee('Project Milestone Bonus');
        $editResponse->assertSee('Gym Membership Fee');

        // Update draft with custom income and custom deduction values
        $updateResponse = $this->actingAs($this->companyAdminA)->put(route('company.payroll-drafts.update', $draft->id), [
            'days_worked'       => 10,
            'basic_pay'         => 1000.00,
            'custom_incomes'    => [
                $customInc->id => 250.00,
            ],
            'custom_deductions' => [
                $customDec->id => 50.00,
            ],
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pay_ins', [
            'pay_id'         => $draft->id,
            'income_type_id' => $customInc->id,
            'rate'           => 250.00,
        ]);

        $this->assertDatabaseHas('pay_outs', [
            'pay_id'            => $draft->id,
            'deduction_type_id' => $customDec->id,
            'rate'              => 50.00,
        ]);
    }
}
