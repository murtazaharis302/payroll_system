<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpDeduction;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\IncomeType;
use App\Models\LoanPayment;
use App\Models\LoanType;
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

class PayrollDraftBatchChangeWorkflowAndRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $companyAdmin;
    private Department $dept;
    private PayrollPeriod $period;
    private EmployeeMaster $emp1;
    private EmployeeMaster $emp2;
    private PayHeader $draft1;
    private PayHeader $draft2;
    private DeductionType $valeDeductionType;
    private LoanType $loanType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);
        $this->seed(MenusTableSeeder::class);

        Role::firstOrCreate(['id' => Role::COMPANY_ADMIN, 'name' => 'CompanyAdmin']);

        $this->companyAdmin = User::create([
            'name' => 'Batch Regression Admin',
            'email' => 'batch-reg-admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Batch Regression Corp',
            'company_code' => 'BATCHREG',
            'email' => 'batchreg@example.test',
            'status' => '1',
        ]);
        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'REGDEPT',
            'name' => 'Regression Department',
            'is_active' => true,
        ]);

        $this->emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'REG-001',
            'first_name' => 'Diana',
            'last_name' => 'Prince',
            'monthly_rate' => 24000,
            'status' => 'active',
        ]);

        $this->emp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'REG-002',
            'first_name' => 'Bruce',
            'last_name' => 'Wayne',
            'monthly_rate' => 30000,
            'status' => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Regression Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->draft1 = $this->createDraft($this->emp1, 10, 10000);
        $this->draft2 = $this->createDraft($this->emp2, 10, 15000);

        $this->valeDeductionType = DeductionType::firstOrCreate(
            ['code' => 'VALE'],
            [
                'company_id' => $this->company->id,
                'name' => 'Cash Advance Vale',
                'is_active' => true,
                'is_loan_enabled' => true,
            ]
        );
        $this->valeDeductionType->update(['is_loan_enabled' => true, 'is_active' => true]);

        $this->loanType = LoanType::create([
            'company_id' => $this->company->id,
            'name' => 'Emergency Cash Loan',
            'code' => 'EMERGENCY_LOAN',
            'deduction_type_id' => $this->valeDeductionType->id,
            'is_active' => true,
        ]);
    }

    private function createDraft(EmployeeMaster $emp, float $days, float $basicAmount): PayHeader
    {
        $draft = PayHeader::create([
            'employee_id' => $emp->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'draft',
            'days_worked' => $days,
            'lock_version' => 1,
        ]);

        $basic = IncomeType::where('code', 'BASIC')->firstOrFail();
        PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $basic->id,
            'number_input' => 1,
            'rate' => $basicAmount,
        ]);
        $draft->recalculateTotals();

        return $draft;
    }

    public function test_multiple_active_loans_of_same_type_expand_to_individual_payouts(): void
    {
        $loan1 = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->emp1->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 5000,
            'current_balance' => 5000,
            'per_cutoff_amount' => 500,
            'status' => 'ACTIVE',
            'start_date' => '2026-08-01',
        ]);

        $loan2 = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->emp1->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 3000,
            'current_balance' => 3000,
            'per_cutoff_amount' => 300,
            'status' => 'ACTIVE',
            'start_date' => '2026-08-01',
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$this->valeDeductionType->id],
                'draft_ids' => [$this->draft1->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft1->id,
            'loan_id' => $loan1->id,
            'rate' => 500.00,
        ]);
        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft1->id,
            'loan_id' => $loan2->id,
            'rate' => 300.00,
        ]);
        $this->assertEquals(800.00, (float) $this->draft1->fresh()->total_deductions);
    }

    public function test_no_loan_payment_and_no_balance_movement_on_unapproved_draft(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->emp1->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 4000,
            'current_balance' => 4000,
            'per_cutoff_amount' => 400,
            'status' => 'ACTIVE',
            'start_date' => '2026-08-01',
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$this->valeDeductionType->id],
                'draft_ids' => [$this->draft1->id],
            ])
            ->assertRedirect();

        $this->assertEquals(4000.00, (float) $loan->fresh()->current_balance);
        $this->assertEquals(0, LoanPayment::where('emp_loan_id', $loan->id)->count());
    }

    public function test_loan_deduction_skips_employee_without_loan_and_appends_review_reason(): void
    {
        // emp1 has existing review reason
        $this->draft1->update([
            'requires_review' => true,
            'review_reason' => 'Existing attendance discrepancy',
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$this->valeDeductionType->id],
                'draft_ids' => [$this->draft1->id],
            ])
            ->assertRedirect();

        $fresh = $this->draft1->fresh();
        $this->assertTrue((bool) $fresh->requires_review);
        $this->assertStringContainsString('Existing attendance discrepancy', (string) $fresh->review_reason);
        $this->assertStringContainsString("Skipped loan deduction '{$this->valeDeductionType->name}'", (string) $fresh->review_reason);
        $this->assertDatabaseMissing('pay_outs', ['pay_id' => $this->draft1->id, 'deduction_type_id' => $this->valeDeductionType->id]);
    }

    public function test_negative_net_pay_allowed_on_batch_change_but_blocked_on_submit(): void
    {
        $largeDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'LARGE_FEE',
            'name' => 'Large Penalty',
            'is_active' => true,
            'type' => 'other',
        ]);

        // draft1 gross is 10,000; deducting 15,000 yields negative net pay (-5,000)
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$largeDeduction->id],
                'draft_ids' => [$this->draft1->id],
                'deduction_type_configs' => [
                    $largeDeduction->id => ['number_input' => 1, 'rate' => 15000.00],
                ],
            ])
            ->assertRedirect();

        $fresh = $this->draft1->fresh();
        $this->assertLessThan(0, (float) $fresh->net_pay);

        // Submitting negative net pay draft is blocked
        $submitResponse = $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.submit', $this->draft1->id));

        $submitResponse->assertSessionHas('statusDanger');
        $this->assertSame('draft', $this->draft1->fresh()->status);
    }

    public function test_emp_deduction_date_effective_validation(): void
    {
        $customDed = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'GYM_MEMBERSHIP',
            'name' => 'Gym Fee',
            'is_active' => true,
            'type' => 'other',
        ]);

        // emp1 has EmpDeduction expired before period_start ('2026-08-01')
        EmpDeduction::create([
            'employee_id' => $this->emp1->id,
            'deduction_type_id' => $customDed->id,
            'rate' => 200,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->from(route('company.payroll-drafts.batch-change.create'))
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$customDed->id],
                'draft_ids' => [$this->draft1->id],
                'deduction_type_configs' => [
                    $customDed->id => ['number_input' => 1],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('generation_errors');
        $this->assertStringContainsString('no active Recurring Deduction assignment/rate', session('generation_errors')[0]);
    }

    public function test_statutory_and_government_contributions_synchronized_after_income_addition(): void
    {
        $sssType = DeductionType::where('code', 'SSS')->firstOrFail();
        PayOut::create([
            'pay_id' => $this->draft1->id,
            'deduction_type_id' => $sssType->id,
            'number_input' => 1,
            'rate' => 500.00,
        ]);
        $this->draft1->recalculateTotals();

        $allowance = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'BIG_ALLOWANCE',
            'name' => 'Executive Allowance',
            'is_active' => true,
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$allowance->id],
                'draft_ids' => [$this->draft1->id],
                'income_type_configs' => [
                    $allowance->id => ['number_input' => 1, 'rate' => 15000.00],
                ],
            ])
            ->assertRedirect();

        $fresh = $this->draft1->fresh();
        $this->assertEquals(25000.00, (float) $fresh->total_gross_pay);

        // Government contributions synchronized
        $this->assertDatabaseHas('government_contributions', [
            'payroll_draft_id' => $this->draft1->id,
            'employee_id' => $this->emp1->id,
            'type' => 'sss',
        ]);
    }

    public function test_absences_deduction_resolves_quantity_one_with_exact_rate(): void
    {
        $absencesType = DeductionType::where('code', 'ABSENCES')->firstOrFail();

        $this->draft1->update(['days_worked' => 8, 'days_absent' => 3]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$absencesType->id],
                'draft_ids' => [$this->draft1->id],
                'deduction_type_configs' => [
                    $absencesType->id => ['number_input' => 1, 'rate' => 1500.00],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft1->id,
            'deduction_type_id' => $absencesType->id,
            'number_input' => 1.00,
            'rate' => 1500.00,
        ]);
    }

    public function test_department_validity_check_blocks_batch_change(): void
    {
        // emp2 has null dept_id
        $this->emp2->update(['dept_id' => null]);

        $customIncome = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'TEST_INC',
            'name' => 'Test Income',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->from(route('company.payroll-drafts.batch-change.create'))
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$customIncome->id],
                'draft_ids' => [$this->draft1->id, $this->draft2->id],
                'income_type_configs' => [
                    $customIncome->id => ['number_input' => 1, 'rate' => 500.00],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('generation_errors');
        $this->assertStringContainsString('no valid assigned department', session('generation_errors')[0]);

        // draft1 must NOT be updated (atomic rollback)
        $this->assertDatabaseMissing('pay_ins', ['pay_id' => $this->draft1->id, 'income_type_id' => $customIncome->id]);
    }
}
