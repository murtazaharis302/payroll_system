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
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActiveLoanAmortizationFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected User $companyAdmin;
    protected User $superAdmin;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected LoanType $valeLoanType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super_fallback@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Amortization Fallback Test Co',
            'email' => 'fallback@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'email' => 'admin_fallback@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-FB-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_hired' => '2026-08-01',
            'position' => 'Developer',
            'monthly_rate' => 30000.00,
            'daily_rate' => 1153.85,
            'hourly_rate' => 144.23,
            'status' => 'active',
        ]);

        $valeDeduction = \App\Models\DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Cash Advance / Vale',
            'code' => 'VALE',
            'type' => 'loan',
            'is_active' => true,
            'is_loan_enabled' => true,
        ]);

        $this->valeLoanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $valeDeduction->id,
            'name' => 'Cash Advance / Vale',
            'default_amortization_per_cutoff' => 500.00,
            'default_installments_count' => 16,
            'is_active' => true,
        ]);
    }

    public function test_active_loan_with_zero_per_cutoff_amount_falls_back_to_loan_type_default(): void
    {
        // Active loan with balance 8,000 and per_cutoff_amount = 0.00
        $loan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 8000.00,
            'total_amount' => 8000.00,
            'per_cutoff_amount' => 0.00,
            'current_balance' => 8000.00,
            'status' => 'ACTIVE',
            'start_date' => '2026-08-01',
        ]);

        // Model accessor test
        $this->assertEquals(500.00, (float) $loan->per_cutoff_amount);
        $this->assertEquals(500.00, (float) $loan->deduction_per_cutoff);

        // Payroll period creation
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 - 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        // Draft generation via service
        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$this->employee->id]);
        $draft = $headers->first();

        $this->assertNotNull($draft);

        // Verify VALE payout deduction is 500.00 (min(500, 8000))
        $valeType = DeductionType::where('code', 'VALE')->firstOrFail();
        $valePayOut = $draft->payOuts()->where('deduction_type_id', $valeType->id)->first();
        $this->assertNotNull($valePayOut);
        $this->assertEquals(500.00, (float) $valePayOut->rate);

        // GET edit screen response check
        $editResponse = $this->actingAs($this->companyAdmin)->get(route('payroll-drafts.edit', $draft->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('(₱500.00/cut)');
        $editResponse->assertDontSee('(₱0.00/cut)');
    }

    public function test_vale_loan_does_not_bleed_into_government_loans_block(): void
    {
        $loan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'total_amount' => 5000.00,
            'per_cutoff_amount' => 500.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
            'start_date' => '2026-08-01',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 - 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$this->employee->id]);
        $draft = $headers->first();

        $editResponse = $this->actingAs($this->companyAdmin)->get(route('payroll-drafts.edit', $draft->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Vale / Cash Advances');
        $editResponse->assertDontSee('Government Loans');
    }

    public function test_employee_without_active_loan_receives_zero_loan_deduction_in_fixed_mode(): void
    {
        // Employee has NO active loan.
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 - 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'VALE', 'display' => true, 'amount' => 1000.00],
                ['deduction_type' => 'SSS_LOAN', 'display' => true, 'amount' => 750.00],
            ],
        ];

        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$this->employee->id], $options);
        $draft = $headers->first();

        $this->assertNotNull($draft);
        
        $valeType = DeductionType::where('code', 'VALE')->firstOrFail();
        $valePayOut = $draft->payOuts()->where('deduction_type_id', $valeType->id)->first();
        $this->assertNull($valePayOut);

        $sssLoanType = DeductionType::where('code', 'SSS_LOAN')->firstOrFail();
        $sssLoanPayOut = $draft->payOuts()->where('deduction_type_id', $sssLoanType->id)->first();
        $this->assertNull($sssLoanPayOut);

        $this->assertEquals(0.00, (float) $draft->vale_loan_deduction);
        $this->assertEquals(0.00, (float) $draft->sss_loan_deduction);
    }

    public function test_deduction_exceeding_loan_balance_is_capped_at_remaining_balance_and_finalized_properly(): void
    {
        $this->actingAs($this->companyAdmin);

        // Employee with active loan where remaining balance = 800.00.
        // total_amount is set to 800 to match current_balance so that after
        // recalculateBalance() (total - sumPaid = 800 - 800 = 0) status becomes PAID.
        $loan = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'total_amount' => 800.00,
            'per_cutoff_amount' => 500.00,
            'current_balance' => 800.00,
            'status' => 'ACTIVE',
            'start_date' => '2026-08-01',
        ]);


        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 - 2nd Cutoff',
            'period_start' => '2026-08-16',
            'period_end' => '2026-08-31',
            'pay_date' => '2026-08-31',
            'status' => 'open',
        ]);

        // Generate draft with fixed mode deduction = 1000.00 (exceeding balance of 800.00)
        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'VALE', 'display' => true, 'amount' => 1000.00],
            ],
        ];

        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$this->employee->id], $options);
        $draft = $headers->first();

        $this->assertNotNull($draft);
        $draft->refresh();

        // Confirm deduction was capped at 800.00
        $valeType = DeductionType::where('code', 'VALE')->firstOrFail();
        $valePayOut = $draft->payOuts()->withoutGlobalScopes()->where('deduction_type_id', $valeType->id)->first();
        $this->assertNotNull($valePayOut);
        $this->assertEquals(800.00, (float) $valePayOut->rate);

        // Also test manual edit HTTP PUT with 1000.00 submitted
        $updateResponse = $this->actingAs($this->companyAdmin)->put(
            route('payroll-drafts.update', $draft->id),
            [
                'days_worked' => 11,
                'vale_loan_deduction' => 1000.00,
            ]
        );
        $updateResponse->assertRedirect();

        $draft->refresh();
        $valePayOut = $draft->payOuts()->withoutGlobalScopes()->where('deduction_type_id', $valeType->id)->first();
        $this->assertEquals(800.00, (float) $valePayOut->rate);

        // Finalize draft -> verify Loan Ledger records payment of 800.00, current_balance = 0.00, status = PAID
        $service->approvePayroll($draft, $this->superAdmin);

        $loan->refresh();
        // recalculateBalance() sums loan_payments.amount_paid; only 1 payroll payment of 800.00 exists
        $this->assertEquals(800.00, (float) $loan->amount_paid);
        $this->assertEquals(0.00, (float) $loan->current_balance);
        $this->assertEquals('PAID', $loan->status);

        $payment = LoanPayment::withoutGlobalScopes()->where('emp_loan_id', $loan->id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(800.00, (float) $payment->amount_paid);
    }
}
