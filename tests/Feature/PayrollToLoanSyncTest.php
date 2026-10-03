<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\EmpLoan;
use App\Models\LoanPayment;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollLoanSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollToLoanSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected EmployeeMaster $employee;
    protected DeductionType $deductionType;
    protected LoanType $loanType;
    protected EmpLoan $loan;
    protected PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Company Admin',
            'email'    => 'admin@sync.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $this->company = Company::create([
            'name'         => 'Sync Company',
            'email'        => 'sync@company.test',
            'user_id'      => $this->admin->id,
            'company_code' => 'SYNC01',
        ]);

        $this->admin->update(['company_id' => $this->company->id]);

        $this->employee = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'employee_code' => 'EMP-SYNC-01',
            'first_name'    => 'Gordon',
            'last_name'     => 'Freeman',
            'status'        => 'active',
        ]);

        $this->deductionType = DeductionType::withoutGlobalScopes()->where('code', 'VALE')->first();
        if (!$this->deductionType) {
            $this->deductionType = DeductionType::create([
                'company_id'      => $this->company->id,
                'name'            => 'Cash Advance',
                'code'            => 'VALE',
                'type'            => 'loan',
                'is_active'       => true,
                'is_loan_enabled' => true,
            ]);
        } else {
            $this->deductionType->update(['is_loan_enabled' => true]);
        }

        $this->loanType = LoanType::withoutGlobalScopes()->create([
            'company_id'        => $this->company->id,
            'name'              => 'Cash Advance',
            'deduction_type_id' => $this->deductionType->id,
        ]);

        $this->loan = EmpLoan::create([
            'employee_id'      => $this->employee->id,
            'loan_type_id'     => $this->loanType->id,
            'principal_amount' => 10000.00,
            'total_amount'     => 10000.00,
            'current_balance'  => 10000.00,
            'amount_paid'      => 0.00,
            'status'           => 'ACTIVE',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id'    => $this->company->id,
            'period_name'   => 'August 2026 1st Cutoff',
            'period_start'  => '2026-08-01',
            'period_end'    => '2026-08-15',
            'pay_date'      => '2026-08-15',
            'status'        => 'open',
        ]);
    }

    public function test_payroll_approval_creates_payroll_loan_payment_and_updates_loan_balance(): void
    {
        $payHeader = PayHeader::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'gross_pay'         => 15000.00,
            'total_deductions'  => 500.00,
            'net_pay'           => 14500.00,
            'status'            => 'pending_approval',
        ]);

        PayOut::create([
            'pay_id'            => $payHeader->id,
            'pay_header_id'     => $payHeader->id,
            'deduction_type_id' => $this->deductionType->id,
            'loan_id'           => $this->loan->id,
            'amount'            => 500.00,
            'rate'              => 500.00,
        ]);

        $payHeader->update(['status' => 'approved']);

        $this->assertDatabaseHas('loan_payments', [
            'emp_loan_id'   => $this->loan->id,
            'pay_header_id' => $payHeader->id,
            'amount_paid'   => 500.00,
            'source'        => 'PAYROLL',
        ]);

        $freshLoan = $this->loan->fresh();
        $this->assertEquals(500.00, (float) $freshLoan->amount_paid);
        $this->assertEquals(9500.00, (float) $freshLoan->current_balance);
        $this->assertEquals('ACTIVE', $freshLoan->status);
    }

    public function test_changing_payroll_deduction_updates_existing_payroll_loan_payment_in_place(): void
    {
        $payHeader = PayHeader::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'gross_pay'         => 15000.00,
            'total_deductions'  => 500.00,
            'net_pay'           => 14500.00,
            'status'            => 'approved',
        ]);

        $payOut = PayOut::create([
            'pay_id'            => $payHeader->id,
            'pay_header_id'     => $payHeader->id,
            'deduction_type_id' => $this->deductionType->id,
            'loan_id'           => $this->loan->id,
            'amount'            => 500.00,
            'rate'              => 500.00,
        ]);

        app(PayrollLoanSyncService::class)->sync($payHeader);

        $initialPayment = LoanPayment::where('pay_header_id', $payHeader->id)->first();
        $this->assertNotNull($initialPayment);
        $this->assertEquals(500.00, (float) $initialPayment->amount_paid);

        // Change payout amount from 500 to 1200
        $payOut->update(['amount' => 1200.00, 'rate' => 1200.00]);
        app(PayrollLoanSyncService::class)->sync($payHeader);

        $this->assertEquals(1, LoanPayment::where('pay_header_id', $payHeader->id)->count());

        $updatedPayment = LoanPayment::where('pay_header_id', $payHeader->id)->first();
        $this->assertEquals($initialPayment->id, $updatedPayment->id); // Updated in place
        $this->assertEquals(1200.00, (float) $updatedPayment->amount_paid);

        $freshLoan = $this->loan->fresh();
        $this->assertEquals(1200.00, (float) $freshLoan->amount_paid);
        $this->assertEquals(8800.00, (float) $freshLoan->current_balance);
    }

    public function test_zeroing_or_removing_payroll_deduction_deletes_payroll_payment_and_restores_balance(): void
    {
        $payHeader = PayHeader::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'gross_pay'         => 15000.00,
            'total_deductions'  => 500.00,
            'net_pay'           => 14500.00,
            'status'            => 'approved',
        ]);

        $payOut = PayOut::create([
            'pay_id'            => $payHeader->id,
            'pay_header_id'     => $payHeader->id,
            'deduction_type_id' => $this->deductionType->id,
            'loan_id'           => $this->loan->id,
            'amount'            => 500.00,
            'rate'              => 500.00,
        ]);

        app(PayrollLoanSyncService::class)->sync($payHeader);
        $this->assertEquals(9500.00, (float) $this->loan->fresh()->current_balance);

        // Remove payout line
        $payOut->delete();
        app(PayrollLoanSyncService::class)->sync($payHeader);

        $this->assertDatabaseMissing('loan_payments', [
            'pay_header_id' => $payHeader->id,
        ]);

        $freshLoan = $this->loan->fresh();
        $this->assertEquals(0.00, (float) $freshLoan->amount_paid);
        $this->assertEquals(10000.00, (float) $freshLoan->current_balance);
    }

    public function test_manual_loan_payments_are_never_modified_or_deleted_during_sync(): void
    {
        $manualPayment = LoanPayment::create([
            'emp_loan_id'  => $this->loan->id,
            'amount_paid'  => 1500.00,
            'payment_date' => '2026-08-05',
            'notes'        => 'Direct Cash',
            'source'       => 'MANUAL',
        ]);
        $this->loan->recalculateBalance();

        $payHeader = PayHeader::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'gross_pay'         => 15000.00,
            'total_deductions'  => 500.00,
            'net_pay'           => 14500.00,
            'status'            => 'approved',
        ]);

        PayOut::create([
            'pay_id'            => $payHeader->id,
            'pay_header_id'     => $payHeader->id,
            'deduction_type_id' => $this->deductionType->id,
            'loan_id'           => $this->loan->id,
            'amount'            => 500.00,
            'rate'              => 500.00,
        ]);

        app(PayrollLoanSyncService::class)->sync($payHeader);

        // Manual payment is preserved
        $freshManual = $manualPayment->fresh();
        $this->assertNotNull($freshManual);
        $this->assertEquals(1500.00, (float) $freshManual->amount_paid);
        $this->assertEquals('MANUAL', $freshManual->source);

        // Both manual and payroll payment exist
        $this->assertEquals(2, LoanPayment::where('emp_loan_id', $this->loan->id)->count());

        $freshLoan = $this->loan->fresh();
        $this->assertEquals(2000.00, (float) $freshLoan->amount_paid); // 1500 + 500
        $this->assertEquals(8000.00, (float) $freshLoan->current_balance);
    }

    public function test_sync_uses_payout_amount_when_number_input_is_greater_than_one(): void
    {
        $payHeader = PayHeader::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'gross_pay'         => 15000.00,
            'total_deductions'  => 1000.00,
            'net_pay'           => 14000.00,
            'status'            => 'approved',
        ]);

        PayOut::create([
            'pay_id'            => $payHeader->id,
            'pay_header_id'     => $payHeader->id,
            'deduction_type_id' => $this->deductionType->id,
            'loan_id'           => $this->loan->id,
            'number_input'      => 2.00,
            'rate'              => 500.00,
            'amount'            => 1000.00,
        ]);

        app(PayrollLoanSyncService::class)->sync($payHeader);

        $payment = LoanPayment::where('pay_header_id', $payHeader->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(1000.00, (float) $payment->amount_paid);

        $freshLoan = $this->loan->fresh();
        $this->assertEquals(1000.00, (float) $freshLoan->amount_paid);
        $this->assertEquals(9000.00, (float) $freshLoan->current_balance);
    }

    public function test_sync_ignores_cross_employee_malformed_loan_id(): void
    {
        $employeeOther = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'employee_code' => 'EMP-OTHER-02',
            'first_name'    => 'Alyx',
            'last_name'     => 'Vance',
            'status'        => 'active',
        ]);

        $loanOther = EmpLoan::create([
            'employee_id'      => $employeeOther->id,
            'loan_type_id'     => $this->loanType->id,
            'principal_amount' => 5000.00,
            'total_amount'     => 5000.00,
            'current_balance'  => 5000.00,
            'status'           => 'ACTIVE',
        ]);

        $payHeader = PayHeader::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'gross_pay'         => 15000.00,
            'total_deductions'  => 500.00,
            'net_pay'           => 14500.00,
            'status'            => 'approved',
        ]);

        // Malformed payout: payHeader belongs to Gordon, but loan_id belongs to Alyx
        PayOut::create([
            'pay_id'            => $payHeader->id,
            'pay_header_id'     => $payHeader->id,
            'deduction_type_id' => $this->deductionType->id,
            'loan_id'           => $loanOther->id,
            'amount'            => 500.00,
            'rate'              => 500.00,
        ]);

        app(PayrollLoanSyncService::class)->sync($payHeader);

        // LoanOther for Alyx should NOT have received a payment
        $this->assertEquals(0, LoanPayment::where('emp_loan_id', $loanOther->id)->count());
        $this->assertEquals(5000.00, (float) $loanOther->fresh()->current_balance);
    }
}

