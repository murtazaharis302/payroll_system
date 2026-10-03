<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanPayment;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManualLoanDeductionInputTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected PayrollPeriod $period;
    protected DeductionType $valeDeductionType;
    protected LoanType $valeLoanType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_c03@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'C03 Test Company',
            'email' => 'c03@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-C03-001',
            'first_name' => 'John',
            'last_name' => 'Loan',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-C03',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $this->valeDeductionType = DeductionType::withoutGlobalScopes()->where('code', 'VALE')->first();
        $this->valeDeductionType->update(['is_loan_enabled' => true]);

        $this->valeLoanType = LoanType::create([
            'company_id' => $this->company->id,
            'name' => 'Cash Advance / Vale',
            'deduction_type_id' => $this->valeDeductionType->id,
            'default_amortization_per_cutoff' => 1000.00,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Draft generation creates loan PayOut line with rate = 0.00 and loan_id linked.
     */
    public function test_draft_generation_creates_loan_payout_with_rate_zero_and_loan_id(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id]);

        $this->assertCount(1, $drafts);
        $draft = $drafts->first();

        $loanPayOut = PayOut::where('pay_id', $draft->id)
            ->where('loan_id', $loan->id)
            ->first();

        $this->assertNotNull($loanPayOut);
        $this->assertEquals($this->valeDeductionType->id, $loanPayOut->deduction_type_id);
        $this->assertEquals(0.00, (float) $loanPayOut->rate);
        $this->assertEquals(1.00, (float) $loanPayOut->number_input);

        // Verify no LoanPayment was posted during draft generation
        $this->assertEquals(0, LoanPayment::where('emp_loan_id', $loan->id)->count());
    }

    /**
     * Test 2: Two active loans using the same Deduction Type generate two separate PayOut lines linked to their respective loan_ids.
     */
    public function test_two_active_loans_same_deduction_type_generate_separate_payout_lines(): void
    {
        $loan1 = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'notes' => 'Vale Loan 1',
            'status' => 'ACTIVE',
            'approved_at' => now()->subDays(5),
        ]);

        $loan2 = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 3000.00,
            'current_balance' => 3000.00,
            'notes' => 'Vale Loan 2',
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id]);

        $draft = $drafts->first();

        $payOuts = PayOut::where('pay_id', $draft->id)
            ->where('deduction_type_id', $this->valeDeductionType->id)
            ->get();

        $this->assertCount(2, $payOuts);
        $loanIds = $payOuts->pluck('loan_id')->toArray();
        $this->assertContains($loan1->id, $loanIds);
        $this->assertContains($loan2->id, $loanIds);

        foreach ($payOuts as $payOut) {
            $this->assertEquals(0.00, (float) $payOut->rate);
            $this->assertEquals(1.00, (float) $payOut->number_input);
        }
    }

    /**
     * Test 3: HR can manually enter and save loan deduction amount.
     */
    public function test_hr_can_manually_enter_and_save_loan_deduction_amount(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id]);
        $draft = $drafts->first();

        $payOut = PayOut::where('pay_id', $draft->id)->where('loan_id', $loan->id)->firstOrFail();

        $payload = [
            'days_worked' => 15.0,
            'pay_outs' => [
                ['id' => $payOut->id, 'rate' => 750.00],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(200);

        $payOut->refresh();
        $this->assertEquals(750.00, (float) $payOut->rate);

        $expectedTotalDeductions = (float) PayOut::where('pay_id', $draft->id)->sum('rate');
        $draft->refresh();
        $this->assertEquals($expectedTotalDeductions, (float) $draft->total_deductions);
    }

    /**
     * Test 4: Submitting loan deduction exceeding current loan balance is rejected (422).
     */
    public function test_submitting_loan_deduction_exceeding_current_loan_balance_is_rejected(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 1000.00,
            'current_balance' => 1000.00,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id]);
        $draft = $drafts->first();

        $payOut = PayOut::where('pay_id', $draft->id)->where('loan_id', $loan->id)->firstOrFail();

        $payload = [
            'days_worked' => 15.0,
            'pay_outs' => [
                ['id' => $payOut->id, 'rate' => 1500.00],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Loan deduction amount (₱1,500.00) cannot exceed the current loan balance (₱1,000.00).');
    }

    /**
     * Test 5: Submitting negative loan deduction amount is rejected (422).
     */
    public function test_submitting_negative_loan_deduction_amount_is_rejected(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id]);
        $draft = $drafts->first();

        $payOut = PayOut::where('pay_id', $draft->id)->where('loan_id', $loan->id)->firstOrFail();

        $payload = [
            'days_worked' => 15.0,
            'pay_outs' => [
                ['id' => $payOut->id, 'rate' => -200.00],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Deduction input cannot be negative.');
    }

    /**
     * Test 6: Payroll approval posts single LoanPayment and updates loan balance.
     */
    public function test_payroll_approval_posts_single_loan_payment_and_updates_balance(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id]);
        $draft = $drafts->first();

        $payOut = PayOut::where('pay_id', $draft->id)->where('loan_id', $loan->id)->firstOrFail();
        $payOut->update(['rate' => 1200.00]);

        $draft->update(['status' => 'pending_approval']);

        $approvedDraft = $service->approvePayroll($draft->fresh(), $this->adminUser);

        $this->assertEquals('approved', $approvedDraft->status);

        $payments = LoanPayment::where('emp_loan_id', $loan->id)->get();
        $this->assertCount(1, $payments);
        $payment = $payments->first();
        $this->assertEquals(1200.00, (float) $payment->amount_paid);

        $loan->refresh();
        $this->assertEquals(3800.00, (float) $loan->current_balance);
    }

    /**
     * Test 7: Repeated approval does not create a second LoanPayment.
     */
    public function test_repeated_approval_does_not_create_second_loan_payment(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id]);
        $draft = $drafts->first();

        $payOut = PayOut::where('pay_id', $draft->id)->where('loan_id', $loan->id)->firstOrFail();
        $payOut->update(['rate' => 1000.00]);

        $draft->update(['status' => 'pending_approval']);
        $service->approvePayroll($draft->fresh(), $this->adminUser);

        // Attempt repeated approval / posting
        $service->approvePayroll($draft->fresh(), $this->adminUser);

        $this->assertEquals(1, LoanPayment::where('emp_loan_id', $loan->id)->count());

        $loan->refresh();
        $this->assertEquals(4000.00, (float) $loan->current_balance);
    }

    /**
     * Test 8: Submitting rate edit on a non-loan deduction line is rejected (422).
     */
    public function test_submitting_rate_edit_on_non_loan_deduction_line_is_rejected(): void
    {
        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id]);
        $draft = $drafts->first();

        $nonLoanPayOut = PayOut::where('pay_id', $draft->id)->whereNull('loan_id')->firstOrFail();

        $payload = [
            'days_worked' => 15.0,
            'pay_outs' => [
                ['id' => $nonLoanPayOut->id, 'rate' => 999.00],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Manual rate editing is allowed only for loan deduction lines.');
    }
}
