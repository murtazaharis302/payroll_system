<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\IncomeType;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreatePayrollDraftActiveTypesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $companyA;
    protected Company $companyB;
    protected PayrollPeriod $periodA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User A',
            'email' => 'admin_comp_a@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company Alpha',
            'company_code' => 'COMP_A',
            'email' => 'compa@test.com',
            'status' => '1',
        ]);
        $this->adminUser->update(['company_id' => $this->companyA->id]);

        $adminUserB = User::create([
            'name' => 'Admin User B',
            'email' => 'admin_comp_b@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->companyB = Company::create([
            'user_id' => $adminUserB->id,
            'name' => 'Company Beta',
            'company_code' => 'COMP_B',
            'email' => 'compb@test.com',
            'status' => '1',
        ]);
        $adminUserB->update(['company_id' => $this->companyB->id]);

        $this->periodA = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'August 2026 Cutoff 1',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);
    }

    /**
     * Test 1: Active custom company income type appears on /admin/payroll-drafts/create.
     */
    public function test_active_custom_company_income_type_appears_on_create_draft_screen(): void
    {
        $customIncome = IncomeType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Project Hazard Allowance',
            'code' => 'HAZARD_PAY',
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.create', ['company_id' => $this->companyA->id]));
        $response->assertStatus(200);

        $viewIncomes = collect($response->viewData('allCompanyIncomeTypes'));
        $this->assertTrue($viewIncomes->contains('code', 'HAZARD_PAY'));
    }

    /**
     * Test 2: Active custom company deduction type appears on /admin/payroll-drafts/create.
     */
    public function test_active_custom_company_deduction_type_appears_on_create_draft_screen(): void
    {
        $customDeduction = DeductionType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Parking Fee Deduction',
            'code' => 'PARKING_FEE',
            'is_taxable' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.create', ['company_id' => $this->companyA->id]));
        $response->assertStatus(200);

        $viewDeductions = collect($response->viewData('allCompanyDeductionTypes'));
        $this->assertTrue($viewDeductions->contains('code', 'PARKING_FEE'));
    }

    /**
     * Test 3: BASIC does not appear as an additional income on create draft screen.
     */
    public function test_basic_income_type_does_not_appear_as_additional_income(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.create', ['company_id' => $this->companyA->id]));
        $response->assertStatus(200);

        $viewIncomes = collect($response->viewData('allCompanyIncomeTypes'));
        $this->assertFalse($viewIncomes->contains('code', 'BASIC'));
    }

    /**
     * Test 4: Active mapped loan deduction types (e.g. SSS_LOAN / PAGIBIG_LOAN) appear.
     */
    public function test_active_mapped_loan_deduction_types_appear_on_create_draft_screen(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.create', ['company_id' => $this->companyA->id]));
        $response->assertStatus(200);

        $viewDeductions = collect($response->viewData('allCompanyDeductionTypes'));
        $this->assertTrue($viewDeductions->contains('code', 'SSS_LOAN'));
        $this->assertTrue($viewDeductions->contains('code', 'PAGIBIG_LOAN'));
    }

    /**
     * Test 5: Inactive types and types belonging to another company do not appear (tenant scoping & active filtering).
     */
    public function test_inactive_and_other_company_types_do_not_appear_on_create_draft_screen(): void
    {
        // Company A inactive custom income
        $inactiveIncomeA = IncomeType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Inactive Incentive',
            'code' => 'INACTIVE_INC_A',
            'is_taxable' => true,
            'is_active' => false,
        ]);

        // Company B active custom income
        $activeIncomeB = IncomeType::create([
            'company_id' => $this->companyB->id,
            'name' => 'Company B Secret Bonus',
            'code' => 'SECRET_BONUS_B',
            'is_taxable' => true,
            'is_active' => true,
        ]);

        // Company A inactive custom deduction
        $inactiveDeductionA = DeductionType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Inactive Gym Membership',
            'code' => 'INACTIVE_GYM_A',
            'is_taxable' => false,
            'is_active' => false,
        ]);

        // Company B active custom deduction
        $activeDeductionB = DeductionType::create([
            'company_id' => $this->companyB->id,
            'name' => 'Company B Union Fee',
            'code' => 'UNION_FEE_B',
            'is_taxable' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.create', ['company_id' => $this->companyA->id]));
        $response->assertStatus(200);

        $viewIncomes = collect($response->viewData('allCompanyIncomeTypes'));
        $viewDeductions = collect($response->viewData('allCompanyDeductionTypes'));

        $this->assertFalse($viewIncomes->contains('code', 'INACTIVE_INC_A'));
        $this->assertFalse($viewIncomes->contains('code', 'SECRET_BONUS_B'));

        $this->assertFalse($viewDeductions->contains('code', 'INACTIVE_GYM_A'));
        $this->assertFalse($viewDeductions->contains('code', 'UNION_FEE_B'));
    }

    /**
     * Test 6: Standard types are not duplicated.
     */
    public function test_standard_types_are_not_duplicated(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.create', ['company_id' => $this->companyA->id]));
        $response->assertStatus(200);

        $viewIncomes = collect($response->viewData('allCompanyIncomeTypes'));
        $viewDeductions = collect($response->viewData('allCompanyDeductionTypes'));

        // Confirm unique codes
        $incomeCodes = $viewIncomes->pluck('code');
        $this->assertEquals($incomeCodes->count(), $incomeCodes->unique()->count());

        $deductionCodes = $viewDeductions->pluck('code');
        $this->assertEquals($deductionCodes->count(), $deductionCodes->unique()->count());
    }

    /**
     * Test 7: /admin/payroll-drafts/create renders the dedicated BASIC Pay row exactly once.
     */
    public function test_create_payroll_draft_renders_dedicated_basic_row_exactly_once(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.create', ['company_id' => $this->companyA->id]));
        $response->assertStatus(200);

        // Rendered HTML contains Basic Pay / Salary or Basic Number Input
        $response->assertSee('Basic Pay / Salary');
        $content = $response->getContent();

        // Exactly ONE occurrence of name="incomes[0][income_type]" value="BASIC"
        $basicOccurrences = substr_count($content, 'value="BASIC"');
        $this->assertEquals(1, $basicOccurrences);
    }

    /**
     * Test 8: Submitted BASIC Number Input reaches calculation flow for Daily-rate employees.
     */
    public function test_submitted_basic_number_input_calculates_daily_employee_pay(): void
    {
        $dept = \App\Models\Department::create([
            'company_id' => $this->companyA->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $employee = \App\Models\EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-DAILY-01',
            'first_name' => 'Daily',
            'last_name' => 'Worker',
            'daily_rate' => 600.00,
            'status' => 'active',
        ]);

        $service = new \App\Services\PayrollProcessingService();

        // Pass manual BASIC number_input = 12 days
        $headers = $service->generateDraft($this->periodA, [$employee->id], [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'BASIC', 'amount' => 12, 'display' => 1],
            ],
        ]);

        $draft = $headers->first();
        $basicType = IncomeType::where('code', 'BASIC')->firstOrFail();

        $basicPayIn = \App\Models\PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $basicType->id)
            ->firstOrFail();

        $this->assertEquals(600.00, (float) $basicPayIn->rate);
        $this->assertEquals(12.0, (float) $basicPayIn->number_input);
        $this->assertEquals(7200.00, (float) $basicPayIn->amount); // 600 * 12
    }

    /**
     * Test 9: Monthly and Hourly employee calculations remain intact.
     */
    public function test_monthly_and_hourly_employee_calculations_remain_intact(): void
    {
        $dept = \App\Models\Department::create([
            'company_id' => $this->companyA->id,
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $monthlyEmp = \App\Models\EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-MONTHLY-01',
            'first_name' => 'Monthly',
            'last_name' => 'Salaried',
            'monthly_rate' => 50000.00,
            'status' => 'active',
        ]);

        $hourlyEmp = \App\Models\EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-HOURLY-01',
            'first_name' => 'Hourly',
            'last_name' => 'Worker',
            'hourly_rate' => 150.00,
            'status' => 'active',
        ]);

        $service = new \App\Services\PayrollProcessingService();

        // 1. Monthly Employee (Half month = 0.5 factor => 25000.00)
        $monthlyHeaders = $service->generateDraft($this->periodA, [$monthlyEmp->id]);
        $monthlyDraft = $monthlyHeaders->first();
        $basicType = IncomeType::where('code', 'BASIC')->firstOrFail();

        $monthlyPayIn = \App\Models\PayIn::withoutGlobalScopes()
            ->where('pay_id', $monthlyDraft->id)
            ->where('income_type_id', $basicType->id)
            ->firstOrFail();

        $this->assertEquals(50000.00, (float) $monthlyPayIn->rate);
        $this->assertEquals(0.50, (float) $monthlyPayIn->number_input);
        $this->assertEquals(25000.00, (float) $monthlyPayIn->amount);

        // 2. Hourly Employee (80 hours * 150 = 12000.00)
        $hourlyHeaders = $service->generateDraft($this->periodA, [$hourlyEmp->id], [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'BASIC', 'amount' => 80, 'display' => 1],
            ],
        ]);
        $hourlyDraft = $hourlyHeaders->first();

        $hourlyPayIn = \App\Models\PayIn::withoutGlobalScopes()
            ->where('pay_id', $hourlyDraft->id)
            ->where('income_type_id', $basicType->id)
            ->firstOrFail();

        $this->assertEquals(150.00, (float) $hourlyPayIn->rate);
        $this->assertEquals(80.0, (float) $hourlyPayIn->number_input);
        $this->assertEquals(12000.00, (float) $hourlyPayIn->amount);
    }

    /**
     * Test 10: BASIC is still not created or saved as an EmpIncome.
     */
    public function test_basic_is_never_saved_as_emp_income(): void
    {
        $basicType = IncomeType::where('code', 'BASIC')->firstOrFail();

        $hasBasicEmpIncome = \App\Models\EmpIncome::withoutGlobalScopes()
            ->where('income_type_id', $basicType->id)
            ->exists();

        $this->assertFalse($hasBasicEmpIncome);
    }
}
