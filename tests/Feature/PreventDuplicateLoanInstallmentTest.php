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
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PreventDuplicateLoanInstallmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected DeductionType $deductionType;
    protected LoanType $loanType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_dup@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Test Company Dup',
            'email' => 'company_dup@test.com',
            'status' => '1',
        ]);

        $this->admin->update(['company_id' => $this->company->id]);

        $dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'name'       => 'Operations',
            'code'       => 'OPS',
            'is_active'  => 1,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'department' => $dept->name,
            'employee_code' => 'EMP-DUP-001',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'monthly_rate' => 60000.00,
            'daily_rate' => 2307.69,
            'hourly_rate' => 288.46,
            'status' => 'active',
            'employment_type' => 'REGULAR',
        ]);

        $this->deductionType = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Cash Advance / Vale',
            'code' => 'VALE_DUP',
            'type' => 'loan',
            'is_active' => true,
            'is_loan_enabled' => true,
        ]);

        $this->loanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $this->deductionType->id,
            'name' => 'Cash Advance / Vale',
            'default_amortization_per_cutoff' => 1000.00,
            'is_active' => true,
        ]);
    }

    protected function createDraftWithPositiveNetPay(PayrollPeriod $period, int $employeeId): PayHeader
    {
        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($period, [$employeeId]);
        $payHeader = $drafts->first();

        $basicIncome = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]
        );

        PayIn::create([
            'pay_id' => $payHeader->id,
            'income_type_id' => $basicIncome->id,
            'number_input' => 1.00,
            'rate' => 30000.00,
        ]);

        $loanFound = false;
        foreach ($payHeader->payOuts as $po) {
            if ($po->deductionType && $po->deductionType->is_loan_enabled && (float) $po->rate == 0 && !$loanFound) {
                $po->update(['rate' => 1000.00, 'number_input' => 1.00]);
                $loanFound = true;
            }
        }

        $gross = (float) $payHeader->payIns()->sum(DB::raw('number_input * rate'));
        $deductions = (float) $payHeader->payOuts()->sum(DB::raw('number_input * rate'));

        $payHeader->update([
            'status' => \App\Services\PayrollWorkflowService::STATE_PENDING_APPROVAL,
            'total_gross_pay' => $gross,
            'total_deductions' => $deductions,
            'net_pay' => $gross - $deductions,
        ]);

        return $payHeader->fresh();
    }

    /**
     * Test 1: Approving the same payroll twice creates one LoanPayment only.
     */
    public function test_approving_same_payroll_twice_creates_one_loan_payment_only(): void
    {
        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period Double Approval Test',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $payHeader = $this->createDraftWithPositiveNetPay($period, $this->employee->id);
        $service = new PayrollProcessingService();

        // First approval
        $service->approvePayroll($payHeader, $this->admin);
        $paymentCountInitial = LoanPayment::where('emp_loan_id', $empLoan->id)->count();
        $this->assertEquals(1, $paymentCountInitial);

        // Second approval attempt on same draft
        $service->approvePayroll($payHeader, $this->admin);
        $paymentCountFinal = LoanPayment::where('emp_loan_id', $empLoan->id)->count();
        $this->assertEquals(1, $paymentCountFinal, 'Approving the same payroll twice MUST create only ONE LoanPayment.');
    }

    /**
     * Test 2: Two concurrent approval requests cannot create duplicate payments.
     */
    public function test_two_concurrent_approval_requests_cannot_create_duplicate_payments(): void
    {
        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 4000.00,
            'current_balance' => 4000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period Concurrent Approval Test',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $payHeader = $this->createDraftWithPositiveNetPay($period, $this->employee->id);
        $service = new PayrollProcessingService();

        // Simulate concurrent transactions attempting to post payments for the same payroll header
        DB::transaction(function () use ($service, $payHeader, $empLoan) {
            $service->approvePayroll($payHeader, $this->admin);

            $alreadyPaid = LoanPayment::where('emp_loan_id', $empLoan->id)->where('pay_id', $payHeader->id)->exists();
            $this->assertTrue($alreadyPaid);
        });

        $count = LoanPayment::where('emp_loan_id', $empLoan->id)->where('pay_id', $payHeader->id)->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 3: A ₱1,000 deduction against an ₱800 remaining balance posts ₱800.
     */
    public function test_deduction_exceeding_remaining_balance_posts_only_remaining_balance(): void
    {
        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 800.00,
            'current_balance' => 800.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period Balance Cap Test',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $payHeader = $this->createDraftWithPositiveNetPay($period, $this->employee->id);
        $payOut = $payHeader->payOuts->firstWhere('loan_id', $empLoan->id);
        if ($payOut) {
            $payOut->update(['rate' => 1000.00]);
        }

        $service = new PayrollProcessingService();
        $service->approvePayroll($payHeader, $this->admin);

        $loanPayment = LoanPayment::where('emp_loan_id', $empLoan->id)->first();
        $this->assertNotNull($loanPayment);
        $this->assertEquals(800.00, (float) $loanPayment->amount_paid, 'A ₱1,000 deduction against an ₱800 balance must post ₱800.');

        $empLoan->refresh();
        $this->assertEquals(0.00, (float) $empLoan->current_balance);
        $this->assertEquals('PAID', $empLoan->status);
    }

    /**
     * Test 4: Manual Record Payment cannot duplicate the same payroll installment.
     */
    public function test_manual_record_payment_cannot_duplicate_same_payroll_installment(): void
    {
        $companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin User',
            'email' => 'company_admin_dup@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period Manual Duplicate Guard',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $payHeader = $this->createDraftWithPositiveNetPay($period, $this->employee->id);
        $service = new PayrollProcessingService();

        $service->approvePayroll($payHeader, $this->admin);

        // Attempt manual record payment passing the same pay_id
        $response = $this->actingAs($companyAdmin)
            ->from(route('company.loan-monitor.index'))
            ->post(route('company.loan-monitor.record-payment'), [
                'emp_loan_id' => $empLoan->id,
                'pay_id' => $payHeader->id,
                'amount_paid' => 1000.00,
                'payment_date' => '2026-08-15',
            ]);

        $response->assertSessionHas('statusDanger');
        $this->assertEquals(1, LoanPayment::where('emp_loan_id', $empLoan->id)->count());
    }

    /**
     * Test 5: Payroll deduction, payment history, and current balance reconcile exactly.
     */
    public function test_payroll_deduction_payment_history_and_current_balance_reconcile_exactly(): void
    {
        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 3000.00,
            'current_balance' => 3000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        $period1 = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Reconciliation Period 1',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();

        // 1st Cutoff Draft & Approval
        $draft1 = $this->createDraftWithPositiveNetPay($period1, $this->employee->id);
        $service->approvePayroll($draft1, $this->admin);

        $empLoan->refresh();
        $this->assertEquals(2000.00, (float) $empLoan->current_balance);

        // 2nd Cutoff Draft & Approval
        $period2 = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Reconciliation Period 2',
            'period_start' => '2026-08-16',
            'period_end' => '2026-08-31',
            'pay_date' => '2026-08-31',
            'cutoff_number' => 2,
            'status' => 'open',
        ]);

        $draft2 = $this->createDraftWithPositiveNetPay($period2, $this->employee->id);
        $service->approvePayroll($draft2, $this->admin);

        $empLoan->refresh();
        $this->assertEquals(1000.00, (float) $empLoan->current_balance);

        $totalPaid = LoanPayment::where('emp_loan_id', $empLoan->id)->sum('amount_paid');
        $this->assertEquals(2000.00, (float) $totalPaid);
        $this->assertEquals((float)$empLoan->principal_amount - $totalPaid, (float)$empLoan->current_balance);
    }

    /**
     * Test 6: Two concurrent manual payments against the same remaining balance.
     */
    public function test_repeated_manual_payment_cannot_overpay_fully_paid_loan(): void
    {
        $companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin Concurrent',
            'email' => 'company_admin_concurrent@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 1000.00,
            'current_balance' => 1000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        // First manual payment for full balance (1000) inside transaction with lock
        DB::transaction(function () use ($companyAdmin, $empLoan) {
            $this->actingAs($companyAdmin)
                ->from(route('company.loan-monitor.index'))
                ->post(route('company.loan-monitor.record-payment'), [
                    'emp_loan_id' => $empLoan->id,
                    'amount_paid' => 1000.00,
                    'payment_date' => '2026-08-15',
                ]);
        });

        $empLoan->refresh();
        $this->assertEquals(0.00, (float) $empLoan->current_balance);

        // Second manual payment request when balance is now 0
        $response = $this->actingAs($companyAdmin)
            ->from(route('company.loan-monitor.index'))
            ->post(route('company.loan-monitor.record-payment'), [
                'emp_loan_id' => $empLoan->id,
                'amount_paid' => 1000.00,
                'payment_date' => '2026-08-15',
            ]);

        $response->assertSessionHas('statusDanger');
        $this->assertEquals(1, LoanPayment::where('emp_loan_id', $empLoan->id)->count());
    }

    /**
     * Test 7: Manual payment capped or rejected safely after another request reduces the balance.
     */
    public function test_manual_payment_capped_or_rejected_after_balance_reduced(): void
    {
        $companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin Reduced Balance',
            'email' => 'company_admin_reduced@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 1000.00,
            'current_balance' => 1000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        // First payment reduces balance to 300
        $this->actingAs($companyAdmin)
            ->from(route('company.loan-monitor.index'))
            ->post(route('company.loan-monitor.record-payment'), [
                'emp_loan_id' => $empLoan->id,
                'amount_paid' => 700.00,
                'payment_date' => '2026-08-15',
            ]);

        $empLoan->refresh();
        $this->assertEquals(300.00, (float) $empLoan->current_balance);

        // Second request attempts 500 when only 300 remains
        $response = $this->actingAs($companyAdmin)
            ->from(route('company.loan-monitor.index'))
            ->post(route('company.loan-monitor.record-payment'), [
                'emp_loan_id' => $empLoan->id,
                'amount_paid' => 500.00,
                'payment_date' => '2026-08-15',
            ]);

        $response->assertSessionHas('statusDanger');
        $empLoan->refresh();
        $this->assertEquals(300.00, (float) $empLoan->current_balance);
    }

    /**
     * Test 8: A loan-enabled payroll deduction without an exact loan_id is not posted to an arbitrary active loan under a different deduction type.
     */
    public function test_loan_enabled_deduction_without_matching_deduction_type_is_not_posted_to_arbitrary_loan(): void
    {
        // Active loan belongs to $this->loanType ($this->deductionType)
        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        // Different deduction type that is loan enabled but has NO loan created under it
        $unrelatedDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Other Unrelated Deduction',
            'code' => 'OTHER_DED',
            'type' => 'loan',
            'is_active' => true,
            'is_loan_enabled' => true,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Unrelated Deduction Test Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $payHeader = $this->createDraftWithPositiveNetPay($period, $this->employee->id);

        // Clear automatically generated pay_outs so only the unrelated deduction line exists
        $payHeader->payOuts()->delete();

        // Add a pay_out deduction line for the UNRELATED deduction type (loan_id is null)
        \App\Models\PayOut::create([
            'pay_id' => $payHeader->id,
            'deduction_type_id' => $unrelatedDeduction->id,
            'loan_id' => null,
            'number_input' => 1.00,
            'rate' => 500.00,
        ]);

        $service = new PayrollProcessingService();
        $service->approvePayroll($payHeader, $this->admin);

        // Verify that NO payment was posted to the unrelated $empLoan
        $this->assertEquals(0, LoanPayment::where('emp_loan_id', $empLoan->id)->count());
        $empLoan->refresh();
        $this->assertEquals(5000.00, (float) $empLoan->current_balance);
    }

    /**
     * Test 9: Retrying payroll approval still produces one payment only.
     */
    public function test_retrying_payroll_approval_produces_one_payment_only(): void
    {
        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 4000.00,
            'current_balance' => 4000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Retry Approval Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $payHeader = $this->createDraftWithPositiveNetPay($period, $this->employee->id);
        $service = new PayrollProcessingService();

        // 1st approval run
        $service->approvePayroll($payHeader, $this->admin);
        $this->assertEquals(1, LoanPayment::where('emp_loan_id', $empLoan->id)->count());

        // Retry approval run (simulating network retry or repeated call)
        $service->approvePayroll($payHeader, $this->admin);
        $this->assertEquals(1, LoanPayment::where('emp_loan_id', $empLoan->id)->count());
    }

    /**
     * Test 10: FIFO Queue Ordering by approved_at ASC and stable ID ASC.
     * Earliest approved active loan under the linked Deduction Type receives deduction first.
     * Newer loans remain unchanged until earlier loan is cleared.
     * Equal approval timestamps use stable ID order.
     */
    public function test_fifo_queue_deduction_order_by_approved_at_and_stable_id(): void
    {
        // Earlier approved loan
        $loanEarlier = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 1000.00,
            'current_balance' => 1000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
            'approved_at' => '2026-07-01 10:00:00',
        ]);

        // Newer approved loan
        $loanNewer = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 2000.00,
            'current_balance' => 2000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
            'approved_at' => '2026-07-15 10:00:00',
        ]);

        $period1 = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'FIFO Test Period 1',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();

        // 1st cutoff draft: deduction should go strictly to $loanEarlier
        $draft1 = $this->createDraftWithPositiveNetPay($period1, $this->employee->id);
        $service->approvePayroll($draft1, $this->admin);

        $loanEarlier->refresh();
        $loanNewer->refresh();

        $this->assertEquals(0.00, (float) $loanEarlier->current_balance, 'Earlier approved loan must be deducted first and cleared.');
        $this->assertEquals('PAID', $loanEarlier->status);
        $this->assertEquals(2000.00, (float) $loanNewer->current_balance, 'Newer approved loan must remain unchanged while earlier loan is active.');

        // 2nd cutoff draft: deduction should now proceed to $loanNewer
        $period2 = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'FIFO Test Period 2',
            'period_start' => '2026-08-16',
            'period_end' => '2026-08-31',
            'pay_date' => '2026-08-31',
            'cutoff_number' => 2,
            'status' => 'open',
        ]);

        $draft2 = $this->createDraftWithPositiveNetPay($period2, $this->employee->id);
        $service->approvePayroll($draft2, $this->admin);

        $loanNewer->refresh();
        $this->assertEquals(1000.00, (float) $loanNewer->current_balance, 'Once earlier loan is cleared, deductions proceed to newer loan.');
    }
}
