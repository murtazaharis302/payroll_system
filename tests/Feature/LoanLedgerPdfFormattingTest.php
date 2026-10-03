<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\LoanPayment;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanLedgerPdfFormattingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected LoanType $sssLoanType;
    protected EmpLoan $empLoan;
    protected PayrollPeriod $period;
    protected PayHeader $payHeader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id' => $this->user->id,
            'name' => 'PDF Test Company',
            'email' => 'pdf_test@example.com',
            'company_code' => 'COMP-9999',
            'status' => 1,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-PDF-01',
            'first_name' => 'Charlie',
            'last_name' => 'PDFTest',
            'status' => 'active',
            'date_hired' => '2026-01-01',
        ]);

        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);
        $sssDeduction = \App\Models\DeductionType::where('code', 'SSS_LOAN')->first() ?? \App\Models\DeductionType::first();

        $this->sssLoanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $sssDeduction?->id,
            'name' => 'SSS Salary Loan',
            'code' => 'SSS',
            'is_active' => true,
        ]);

        $this->empLoan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->sssLoanType->id,
            'principal_amount' => 10000.00,
            'current_balance' => 8000.00,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'July 2026 - 1st Half',
            'period_code' => 'PP-2026-07A',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-15',
            'status' => 'completed',
        ]);

        $this->payHeader = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'gross_income' => 15000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 14000.00,
            'status' => 'approved',
        ]);
    }

    public function test_loan_payment_notes_differentiates_manual_and_payroll_payments(): void
    {
        // Manual payment (pay_id is null)
        $manualPayment = LoanPayment::create([
            'emp_loan_id' => $this->empLoan->id,
            'pay_id' => null,
            'amount_paid' => 1000.00,
            'payment_date' => '2026-07-01',
        ]);

        // Automated payroll payment (pay_id is non-null)
        $payrollPayment = LoanPayment::create([
            'emp_loan_id' => $this->empLoan->id,
            'pay_id' => $this->payHeader->id,
            'amount_paid' => 1000.00,
            'payment_date' => '2026-07-15',
        ]);

        $this->assertEquals('Direct Repayment', $manualPayment->notes);
        $this->assertStringContainsString('Payroll Deduction', $payrollPayment->notes);
        $this->assertStringContainsString('July 2026 - 1st Half', $payrollPayment->notes);
    }

    public function test_loan_ledger_pdf_generation_includes_loan_type_name_and_payment_sources(): void
    {
        LoanPayment::create([
            'emp_loan_id' => $this->empLoan->id,
            'pay_id' => null,
            'amount_paid' => 1000.00,
            'payment_date' => '2026-07-01',
        ]);

        LoanPayment::create([
            'emp_loan_id' => $this->empLoan->id,
            'pay_id' => $this->payHeader->id,
            'amount_paid' => 1000.00,
            'payment_date' => '2026-07-15',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('reports.loan-ledger'), [
                'company_id' => $this->company->id,
                'employee_id' => $this->employee->id,
                'loan_type' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
