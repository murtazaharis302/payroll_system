<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpDeduction;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EditableDraftDeductionsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_draft_ded@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Draft Deduction Test Corp',
            'company_code' => 'DRAFTDED',
            'email' => 'draftded@corp.com',
            'status' => '1',
        ]);

        session(['active_company_id' => $this->company->id]);
        $this->actingAs($this->adminUser);

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
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);
    }

    /**
     * Test 1: Manually overridden Withholding Tax survives save/refresh and is not overwritten by statutory recalculation.
     */
    public function test_manually_overridden_withholding_tax_survives_save_and_refresh(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-TAX-OVERRIDE',
            'first_name' => 'Tax',
            'last_name' => 'Override',
            'monthly_rate' => 50000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [
                ['income_type' => 'BASIC', 'display' => 1],
            ],
            'deductions' => [
                ['deduction_type' => 'TAX', 'display' => 1],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $taxPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('deductionType', fn($q) => $q->where('code', 'TAX'))->firstOrFail();

        // Override Tax rate to 1234.56
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_outs' => [
                ['id' => $taxPayOut->id, 'rate' => 1234.56],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $draft->refresh();
        $taxPayOut->refresh();

        $this->assertEquals(1234.56, (float) $taxPayOut->rate);
    }

    /**
     * Test 2: Editable Statutory SSS deduction override persists on draft save.
     */
    public function test_editable_statutory_sss_deduction_override_persists(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-SSS-OVERRIDE',
            'first_name' => 'SSS',
            'last_name' => 'Override',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1]],
            'deductions' => [['deduction_type' => 'SSS', 'display' => 1]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $sssPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('deductionType', fn($q) => $q->where('code', 'SSS'))->firstOrFail();

        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_outs' => [
                ['id' => $sssPayOut->id, 'rate' => 750.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $sssPayOut->refresh();
        $this->assertEquals(750.00, (float) $sssPayOut->rate);
    }

    /**
     * Test 3: Editable Recurring Deduction override persists on draft save.
     */
    public function test_editable_recurring_deduction_override_persists(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-OVERRIDE',
            'first_name' => 'Rec',
            'last_name' => 'Override',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $unionType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'UNION_DUES',
            'name' => 'Union Dues',
            'category' => 'other',
            'is_active' => true,
        ]);

        $empDeduction = EmpDeduction::create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $unionType->id,
            'amount' => 500.00,
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $unionPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('deduction_type_id', $unionType->id)->firstOrFail();
        $this->assertEquals(500.00, (float) $unionPayOut->rate);

        // Override to 350.00
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_outs' => [
                ['id' => $unionPayOut->id, 'rate' => 350.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $unionPayOut->refresh();
        $this->assertEquals(350.00, (float) $unionPayOut->rate);

        // Verify EmpDeduction master record remains unchanged at 500.00
        $empDeduction->refresh();
        $this->assertEquals(500.00, (float) $empDeduction->amount);
    }

    /**
     * Test 4: Editable Custom Deduction override persists on draft save.
     */
    public function test_editable_custom_deduction_override_persists(): void
    {
        $customType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'UNIFORM',
            'name' => 'Uniform Fee',
            'category' => 'other',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-CUSTOM-DED',
            'first_name' => 'Custom',
            'last_name' => 'Ded',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1]],
            'deductions' => [['deduction_type' => 'UNIFORM', 'display' => 1, 'amount' => 200.00]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $uniformPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('deduction_type_id', $customType->id)->firstOrFail();

        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_outs' => [
                ['id' => $uniformPayOut->id, 'rate' => 150.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $uniformPayOut->refresh();
        $this->assertEquals(150.00, (float) $uniformPayOut->rate);
    }

    /**
     * Test 5: Attendance deduction with number_input > 1 calculates rate * quantity correctly.
     */
    public function test_attendance_deduction_with_number_input_greater_than_one_calculates_rate_times_quantity(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-TARDINESS',
            'first_name' => 'Late',
            'last_name' => 'Emp',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $tardType = DeductionType::withoutGlobalScopes()->where('code', 'TARDINESS')->firstOrFail();

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        // Create a PayOut line for Tardiness with number_input = 5.0 (5 late hours) @ rate 100.00
        $tardPayOut = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $tardType->id,
            'number_input' => 5.0, // 5 hours
            'rate' => 100.00,
        ]);

        // Override rate to 120.00
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_outs' => [
                ['id' => $tardPayOut->id, 'rate' => 120.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $draft->refresh();
        $tardPayOut->refresh();

        $this->assertEquals(120.00, (float) $tardPayOut->rate);
        $this->assertEquals(5.0, (float) $tardPayOut->number_input);

        // Expected line total = 120.00 * 5.0 = 600.00
        $expectedLineTotal = 120.00 * 5.0;
        $this->assertEquals($expectedLineTotal, (float) ($tardPayOut->rate * $tardPayOut->number_input));

        // Verify total deductions includes 600.00 from tardiness
        $allPayOutsSum = (float) PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->sum(\DB::raw('rate * number_input'));
        $this->assertEquals($allPayOutsSum, (float) $draft->total_deductions);
    }

    /**
     * Test 6: Eligible Loan Deduction override within balance succeeds, but exceeding balance returns 422 error.
     */
    public function test_eligible_loan_deduction_override_respects_balance_constraints(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-LOAN-OVERRIDE',
            'first_name' => 'Loan',
            'last_name' => 'Override',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $sssLoanDedType = DeductionType::withoutGlobalScopes()->where('code', 'SSS_LOAN')->firstOrFail();

        $loanType = LoanType::create([
            'company_id' => $this->company->id,
            'name' => 'SSS Salary Loan',
            'deduction_type_id' => $sssLoanDedType->id,
            'is_active' => true,
        ]);

        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 10000.00,
            'current_balance' => 3000.00,
            'monthly_deduction' => 1000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1]],
            'deductions' => [['deduction_type' => 'SSS_LOAN', 'display' => 1, 'amount' => 1000.00]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $loanPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('loan_id', $loan->id)->firstOrFail();
        $this->assertEquals(1000.00, (float) $loanPayOut->rate);

        // 6a: Override loan deduction to 2500.00 (within 3000.00 balance) -> SUCCEEDS
        $response1 = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_outs' => [
                ['id' => $loanPayOut->id, 'rate' => 2500.00],
            ],
        ]);
        $response1->assertSessionHasNoErrors();
        $loanPayOut->refresh();
        $this->assertEquals(2500.00, (float) $loanPayOut->rate);

        // 6b: Attempt override to 5000.00 (exceeding 3000.00 balance) -> FAILS 422
        $response2 = $this->putJson(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_outs' => [
                ['id' => $loanPayOut->id, 'rate' => 5000.00],
            ],
        ]);
        $response2->assertStatus(422);

        // Verify EmpLoan principal and balance remain untouched in database
        $loan->refresh();
        $this->assertEquals(10000.00, (float) $loan->principal_amount);
        $this->assertEquals(3000.00, (float) $loan->current_balance);
    }

    /**
     * Test 7: Regression - Lowering Basic Pay while submitting unchanged deduction grid recalculates and decreases Withholding Tax.
     */
    public function test_lowering_basic_pay_with_unchanged_deduction_grid_recalculates_and_decreases_withholding_tax(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-TAX-DECREASE',
            'first_name' => 'Tax',
            'last_name' => 'Decrease',
            'monthly_rate' => 60000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1]],
            'deductions' => [['deduction_type' => 'TAX', 'display' => 1]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))->firstOrFail();
        $taxPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('deductionType', fn($q) => $q->where('code', 'TAX'))->firstOrFail();

        $initialTaxRate = (float) $taxPayOut->rate;
        $this->assertGreaterThan(0, $initialTaxRate);

        // Lower Basic Pay rate from 30,000.00 to 10,000.00 while submitting unchanged deduction grid (pay_outs with original tax rate)
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 1.0, 'rate' => 10000.00],
            ],
            'pay_outs' => [
                ['id' => $taxPayOut->id, 'rate' => $initialTaxRate], // Unchanged form submission
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $draft->refresh();
        $taxPayOut->refresh();

        // Tax must decrease from initial high rate, NOT revert to initialTaxRate
        $this->assertLessThan($initialTaxRate, (float) $taxPayOut->rate);
    }
}
