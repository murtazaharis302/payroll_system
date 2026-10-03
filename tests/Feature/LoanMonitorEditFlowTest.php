<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\EmpLoan;
use App\Models\LoanPayment;
use App\Models\LoanType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanMonitorEditFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $companyAdminA;
    protected EmployeeMaster $employeeA;
    protected EmployeeMaster $employeeB;
    protected LoanType $loanTypeA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyAdminA = User::create([
            'name'     => 'Company Admin A',
            'email'    => 'admin.a@alpha.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $ownerB = User::create([
            'name'     => 'Company Admin B',
            'email'    => 'admin.b@beta.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $this->companyA = Company::create([
            'name'         => 'Alpha Company',
            'email'        => 'alpha@company.test',
            'user_id'      => $this->companyAdminA->id,
            'company_code' => 'ALPHA01',
        ]);

        $this->companyB = Company::create([
            'name'         => 'Beta Company',
            'email'        => 'beta@company.test',
            'user_id'      => $ownerB->id,
            'company_code' => 'BETA01',
        ]);

        $this->companyAdminA->update(['company_id' => $this->companyA->id]);

        $this->employeeA = EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'employee_code' => 'EMP-A101',
            'first_name'    => 'John',
            'middle_name'   => '',
            'last_name'     => 'Alpha',
            'status'        => 'active',
        ]);

        $this->employeeB = EmployeeMaster::create([
            'company_id'    => $this->companyB->id,
            'employee_code' => 'EMP-B202',
            'first_name'    => 'Bob',
            'middle_name'   => '',
            'last_name'     => 'Beta',
            'status'        => 'active',
        ]);

        $deductionA = DeductionType::withoutGlobalScopes()->where('code', 'VALE')->first();
        if (!$deductionA) {
            $deductionA = DeductionType::create([
                'company_id'      => $this->companyA->id,
                'name'            => 'Cash Advance',
                'code'            => 'VALE',
                'type'            => 'loan',
                'is_active'       => true,
                'is_loan_enabled' => true,
            ]);
        }

        $this->loanTypeA = LoanType::withoutGlobalScopes()->create([
            'company_id'        => $this->companyA->id,
            'name'              => 'Cash Advance',
            'deduction_type_id' => $deductionA->id,
        ]);
    }

    public function test_company_admin_can_edit_loan_amount_and_recalculate_balance(): void
    {
        $loan = EmpLoan::create([
            'employee_id'      => $this->employeeA->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 10000.00,
            'total_amount'     => 10000.00,
            'current_balance'  => 10000.00,
            'amount_paid'      => 0.00,
            'status'           => 'ACTIVE',
        ]);

        LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 2000.00,
            'payment_date' => '2026-08-01',
            'source'       => 'MANUAL',
        ]);
        $loan->recalculateBalance();

        $this->assertEquals(8000.00, (float) $loan->fresh()->current_balance);

        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.update-loan', $loan->id), [
            'principal_amount' => 15000.00,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('statusSuccess');

        $freshLoan = $loan->fresh();
        $this->assertEquals(15000.00, (float) $freshLoan->principal_amount);
        $this->assertEquals(15000.00, (float) $freshLoan->total_amount);
        $this->assertEquals(2000.00, (float) $freshLoan->amount_paid);
        $this->assertEquals(13000.00, (float) $freshLoan->current_balance);
        $this->assertEquals('ACTIVE', $freshLoan->status);
    }

    public function test_editing_loan_amount_respects_company_scoping(): void
    {
        $loanB = EmpLoan::create([
            'employee_id'      => $this->employeeB->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 10000.00,
            'total_amount'     => 10000.00,
            'current_balance'  => 10000.00,
            'status'           => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.update-loan', $loanB->id), [
            'principal_amount' => 12000.00,
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
        $this->assertEquals(10000.00, (float) $loanB->fresh()->principal_amount);
    }

    public function test_company_admin_can_edit_manual_loan_payment_and_recalculate_balance(): void
    {
        $loan = EmpLoan::create([
            'employee_id'      => $this->employeeA->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 10000.00,
            'total_amount'     => 10000.00,
            'current_balance'  => 10000.00,
            'status'           => 'ACTIVE',
        ]);

        $payment = LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 2000.00,
            'payment_date' => '2026-08-01',
            'source'       => 'MANUAL',
        ]);
        $loan->recalculateBalance();

        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.update-payment', $payment->id), [
            'amount_paid'  => 3500.00,
            'payment_date' => '2026-08-05',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('statusSuccess');

        $freshPayment = $payment->fresh();
        $this->assertEquals(3500.00, (float) $freshPayment->amount_paid);
        $this->assertEquals('2026-08-05', $freshPayment->payment_date->format('Y-m-d'));

        $freshLoan = $loan->fresh();
        $this->assertEquals(3500.00, (float) $freshLoan->amount_paid);
        $this->assertEquals(6500.00, (float) $freshLoan->current_balance);
        $this->assertEquals('ACTIVE', $freshLoan->status);
    }

    public function test_cannot_edit_payroll_generated_loan_payment(): void
    {
        $loan = EmpLoan::create([
            'employee_id'      => $this->employeeA->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 10000.00,
            'total_amount'     => 10000.00,
            'current_balance'  => 10000.00,
            'status'           => 'ACTIVE',
        ]);

        $payrollPayment = LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 1000.00,
            'payment_date' => '2026-08-01',
            'source'       => 'PAYROLL',
        ]);
        $loan->recalculateBalance();

        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.update-payment', $payrollPayment->id), [
            'amount_paid'  => 5000.00,
            'payment_date' => '2026-08-10',
        ]);

        $response->assertStatus(403);
        $this->assertEquals(1000.00, (float) $payrollPayment->fresh()->amount_paid);
    }

    public function test_editing_manual_payment_respects_company_scoping(): void
    {
        $loanB = EmpLoan::create([
            'employee_id'      => $this->employeeB->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 5000.00,
            'total_amount'     => 5000.00,
            'current_balance'  => 5000.00,
            'status'           => 'ACTIVE',
        ]);

        $paymentB = LoanPayment::create([
            'emp_loan_id'  => $loanB->id,
            'amount_paid'  => 1000.00,
            'payment_date' => '2026-08-01',
            'source'       => 'MANUAL',
        ]);

        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.update-payment', $paymentB->id), [
            'amount_paid'  => 2000.00,
            'payment_date' => '2026-08-05',
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
        $this->assertEquals(1000.00, (float) $paymentB->fresh()->amount_paid);
    }

    public function test_over_editing_manual_payment_is_rejected_and_leaves_state_unchanged(): void
    {
        $loan = EmpLoan::create([
            'employee_id'      => $this->employeeA->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 10000.00,
            'total_amount'     => 10000.00,
            'current_balance'  => 10000.00,
            'status'           => 'ACTIVE',
        ]);

        $payment1 = LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 3000.00,
            'payment_date' => '2026-08-01',
            'source'       => 'MANUAL',
        ]);

        $payment2 = LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 2000.00,
            'payment_date' => '2026-08-02',
            'source'       => 'MANUAL',
        ]);
        $loan->recalculateBalance();
        $this->assertEquals(5000.00, (float) $loan->fresh()->current_balance);

        // Max editable for payment2 is 10000 - 3000 = 7000. Propose 8500 -> should fail validation.
        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.update-payment', $payment2->id), [
            'amount_paid'  => 8500.00,
            'payment_date' => '2026-08-05',
        ]);

        $response->assertSessionHasErrors('amount_paid');

        // State remains unchanged
        $this->assertEquals(2000.00, (float) $payment2->fresh()->amount_paid);
        $this->assertEquals(5000.00, (float) $loan->fresh()->current_balance);
        $this->assertEquals('ACTIVE', $loan->fresh()->status);
    }

    public function test_reducing_loan_amount_below_already_paid_total_is_rejected_and_leaves_state_unchanged(): void
    {
        $loan = EmpLoan::create([
            'employee_id'      => $this->employeeA->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 10000.00,
            'total_amount'     => 10000.00,
            'current_balance'  => 10000.00,
            'status'           => 'ACTIVE',
        ]);

        LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 6000.00,
            'payment_date' => '2026-08-01',
            'source'       => 'MANUAL',
        ]);
        $loan->recalculateBalance();
        $this->assertEquals(4000.00, (float) $loan->fresh()->current_balance);

        // Propose reducing principal to 4000 (total = 4000 < already paid 6000)
        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.update-loan', $loan->id), [
            'principal_amount' => 4000.00,
        ]);

        $response->assertSessionHasErrors('principal_amount');

        // State remains unchanged
        $freshLoan = $loan->fresh();
        $this->assertEquals(10000.00, (float) $freshLoan->principal_amount);
        $this->assertEquals(10000.00, (float) $freshLoan->total_amount);
        $this->assertEquals(4000.00, (float) $freshLoan->current_balance);
    }

    public function test_valid_loan_decrease_above_paid_total_succeeds_and_recalculates_balance(): void
    {
        $loan = EmpLoan::create([
            'employee_id'      => $this->employeeA->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 10000.00,
            'total_amount'     => 10000.00,
            'current_balance'  => 10000.00,
            'status'           => 'ACTIVE',
        ]);

        LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 4000.00,
            'payment_date' => '2026-08-01',
            'source'       => 'MANUAL',
        ]);
        $loan->recalculateBalance();

        // Propose reducing principal to 7000 (> already paid 4000)
        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.update-loan', $loan->id), [
            'principal_amount' => 7000.00,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('statusSuccess');

        $freshLoan = $loan->fresh();
        $this->assertEquals(7000.00, (float) $freshLoan->principal_amount);
        $this->assertEquals(7000.00, (float) $freshLoan->total_amount);
        $this->assertEquals(4000.00, (float) $freshLoan->amount_paid);
        $this->assertEquals(3000.00, (float) $freshLoan->current_balance);
        $this->assertEquals('ACTIVE', $freshLoan->status);
    }

    public function test_repayment_progress_uses_total_amount_and_is_clamped(): void
    {
        // Interest bearing loan: principal = 10000, 5% monthly for 2 months -> total_amount = 11000
        $loan = EmpLoan::create([
            'employee_id'           => $this->employeeA->id,
            'loan_type_id'          => $this->loanTypeA->id,
            'principal_amount'      => 10000.00,
            'monthly_interest_rate' => 5.00,
            'terms_months'          => 2,
            'interest_amount'       => 1000.00,
            'total_amount'          => 11000.00,
            'current_balance'       => 11000.00,
            'status'                => 'ACTIVE',
        ]);

        LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 10500.00,
            'payment_date' => '2026-08-01',
            'source'       => 'MANUAL',
        ]);
        $loan->recalculateBalance();

        $response = $this->actingAs($this->companyAdminA)->get(panelRoute('loan-monitor.employee-loans', $this->employeeA->id));
        $response->assertOk();

        $loansData = $response->json('loans');
        $this->assertNotEmpty($loansData);
        // 10500 / 11000 = 95.5% (relative to total_amount, not principal 105%)
        $this->assertEquals(95.5, $loansData[0]['progress_percent']);
    }
}
