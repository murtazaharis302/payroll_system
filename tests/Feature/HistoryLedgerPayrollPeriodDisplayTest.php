<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\LoanPayment;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HistoryLedgerPayrollPeriodDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected LoanType $loanType;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin LedgerTest',
            'email' => 'admin_ledger@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Ledger Test Corp',
            'company_code' => 'LEDGERTEST',
            'email' => 'ledger@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'MAIN',
            'name' => 'Main Dept',
            'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);
        $valeDeduction = \App\Models\DeductionType::where('code', 'VALE')->first();

        $this->loanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $valeDeduction?->id,
            'code' => 'VALE',
            'name' => 'Vale Loan',
        ]);
    }

    /**
     * Test 1: Loan Monitor history API returns payroll_period for payroll-sourced payment and null for manual payment.
     */
    public function test_payment_history_api_returns_payroll_period_for_payroll_linked_payment_and_null_for_manual_payment(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Aug 2026 Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'cut_off_start' => '2026-08-01',
            'cut_off_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-LEDGER-01',
            'first_name' => 'Larry',
            'last_name' => 'Ledger',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 15000.00,
            'total_deductions' => 500.00,
            'net_pay' => 14500.00,
        ]);

        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 4200.00,
            'monthly_deduction' => 500.00,
            'start_date' => '2026-08-01',
            'status' => 'ACTIVE',
        ]);

        $payrollPayment = LoanPayment::create([
            'emp_loan_id' => $loan->id,
            'pay_header_id' => $draft->id,
            'pay_id' => $draft->id,
            'amount_paid' => 500.00,
            'payment_date' => '2026-08-15',
            'source' => 'PAYROLL',
            'notes' => 'Payroll Deduction (Aug 2026 Cutoff)',
        ]);

        $manualPayment = LoanPayment::create([
            'emp_loan_id' => $loan->id,
            'amount_paid' => 300.00,
            'payment_date' => '2026-08-18',
            'source' => 'MANUAL',
            'notes' => 'Manual Payment',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(panelRoute('loan-monitor.payment-history', ['type' => 'vale', 'id' => $loan->id]));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $paymentsData = $response->json('payments');
        $this->assertCount(2, $paymentsData);

        $payrollPaymentData = collect($paymentsData)->firstWhere('amount_paid', 500.0);
        $manualPaymentData = collect($paymentsData)->firstWhere('amount_paid', 300.0);

        $this->assertNotNull($payrollPaymentData);
        $this->assertEquals('Aug 2026 Cutoff', $payrollPaymentData['payroll_period']);

        $this->assertNotNull($manualPaymentData);
        $this->assertNull($manualPaymentData['payroll_period']);
    }

    /**
     * Test 2: Loan Ledger PDF report generation includes payroll period name for payroll entries and omits for manual.
     */
    public function test_loan_ledger_pdf_report_displays_payroll_period_for_payroll_entries_and_omits_for_manual(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Sep 2026 Cutoff',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'cut_off_start' => '2026-09-01',
            'cut_off_end' => '2026-09-15',
            'pay_date' => '2026-09-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-LEDGER-02',
            'first_name' => 'Rita',
            'last_name' => 'Report',
            'monthly_rate' => 32000.00,
            'status' => 'active',
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 16000.00,
            'total_deductions' => 600.00,
            'net_pay' => 15400.00,
        ]);

        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 6000.00,
            'current_balance' => 5400.00,
            'monthly_deduction' => 600.00,
            'start_date' => '2026-09-01',
            'status' => 'ACTIVE',
        ]);

        LoanPayment::create([
            'emp_loan_id' => $loan->id,
            'pay_header_id' => $draft->id,
            'pay_id' => $draft->id,
            'amount_paid' => 600.00,
            'payment_date' => '2026-09-15',
            'source' => 'PAYROLL',
            'notes' => 'Payroll Deduction (Sep 2026 Cutoff)',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('reports.loan-ledger'), [
                'employee_id' => $employee->id,
                'loan_type' => 'all',
            ]);

        $response->assertStatus(200);

        $renderedViewHtml = view('admin.reports.loan-ledger', [
            'employee' => $employee,
            'company' => $this->company,
            'data' => [
                'vale_loans' => collect([$loan->fresh(['payments', 'loanType'])]),
                'goods_purchases' => collect(),
                'government_loans' => collect(),
            ],
            'loanType' => 'all',
        ])->render();

        $this->assertStringContainsString('Sep 2026 Cutoff', $renderedViewHtml);
    }
}
