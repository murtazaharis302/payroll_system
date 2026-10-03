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

class ActiveAugustLoanDraftGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $companyAdmin;
    protected User $hrUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected LoanType $globalLoanType;
    protected LoanType $companyLoanType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super_august_loan@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'August Payroll Test Company',
            'email' => 'augustpayroll@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'email' => 'admin_august_loan@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->hrUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'HR Staff',
            'email' => 'hr_august_loan@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-AUG-001',
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'date_hired' => '2026-08-01',
            'position' => 'Security Specialist',
            'monthly_rate' => 35000.00,
            'daily_rate' => 1346.15,
            'hourly_rate' => 168.27,
            'status' => 'active',
        ]);

        $globalDeduction = \App\Models\DeductionType::create([
            'company_id' => null,
            'name' => 'Cash Advance / Vale',
            'code' => 'VALE',
            'type' => 'loan',
            'is_active' => true,
            'is_loan_enabled' => true,
        ]);

        $companyDeduction = \App\Models\DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Company Emergency Loan',
            'code' => 'EMERGENCY_LOAN',
            'type' => 'loan',
            'is_active' => true,
            'is_loan_enabled' => true,
        ]);

        // 1. Global Loan Type (company_id IS NULL)
        $this->globalLoanType = LoanType::create([
            'company_id' => null,
            'deduction_type_id' => $globalDeduction->id,
            'name' => 'Cash Advance / Vale',
            'default_amortization_per_cutoff' => 1200.00,
            'default_installments_count' => 10,
            'is_active' => true,
        ]);

        // 2. Company-specific Loan Type
        $this->companyLoanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $companyDeduction->id,
            'name' => 'Company Emergency Loan',
            'default_amortization_per_cutoff' => 1500.00,
            'default_installments_count' => 6,
            'is_active' => true,
        ]);
    }

    public function test_generating_august_payroll_draft_with_active_august_loan_succeeds_without_purge_error(): void
    {
        // Create an active loan in August 2026 for the employee
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->globalLoanType->id,
            'principal_amount' => 12000.00,
            'total_amount' => 12000.00,
            'per_cutoff_amount' => 1200.00,
            'current_balance' => 12000.00,
            'status' => 'ACTIVE',
            'start_date' => '2026-08-01',
        ]);

        // Create August 2026 Payroll Period
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 - 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        // Generate Payroll Draft via HTTP POST
        $response = $this->actingAs($this->companyAdmin)->post(
            route('payroll-drafts.generate'),
            [
                'company_id' => $this->company->id,
                'payroll_period_id' => $period->id,
                'use_fixed_amount' => 0,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('statusDanger');

        // Confirm PayHeader was created
        $draft = PayHeader::where('payroll_period_id', $period->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($draft);
        $this->assertNotNull($draft->employee);
        $this->assertNotNull($draft->payrollPeriod);

        // Edit screen GET test to ensure "purged" error is never thrown
        $editResponse = $this->actingAs($this->companyAdmin)->get(route('payroll-drafts.edit', $draft->id));
        $editResponse->assertStatus(200);
        $editResponse->assertDontSee('purged');
        $editResponse->assertSee($this->employee->full_name);

        // Check that active loan payout was auto-populated
        $valeType = DeductionType::where('code', 'VALE')->firstOrFail();
        $valePayOut = $draft->payOuts()->where('deduction_type_id', $valeType->id)->first();
        $this->assertNotNull($valePayOut);
        $this->assertEquals(1200.00, (float) $valePayOut->rate);
    }

    public function test_finalizing_august_draft_updates_loan_ledger_properly(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->companyLoanType->id,
            'principal_amount' => 9000.00,
            'total_amount' => 9000.00,
            'per_cutoff_amount' => 1500.00,
            'current_balance' => 9000.00,
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

        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$this->employee->id], ['use_fixed_amount' => false]);
        $draft = $headers->first();

        // Finalize payroll draft
        $service->approvePayroll($draft, $this->superAdmin);

        $loan->refresh();
        $this->assertEquals(1500.00, (float) $loan->amount_paid);
        $this->assertEquals(7500.00, (float) $loan->current_balance);
        $this->assertEquals('ACTIVE', $loan->status);

        $payment = LoanPayment::where('emp_loan_id', $loan->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(1500.00, (float) $payment->amount_paid);
        $this->assertEquals('PAYROLL', $payment->source);
    }
}
