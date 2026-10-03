<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
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

class AutomatedVsManualLoanDeductionLedgerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $companyAdmin;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected LoanType $valeLoanType;
    protected DeductionType $valeDeductionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super_auto_manual@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Auto vs Manual Test Company',
            'email' => 'automanualcomp@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'email' => 'admin_auto_manual@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-AM-001',
            'first_name' => 'Arthur',
            'last_name' => 'Dent',
            'date_hired' => '2026-01-01',
            'position' => 'Reporter',
            'monthly_rate' => 26000.00,
            'daily_rate' => 1000.00,
            'hourly_rate' => 125.00,
            'status' => 'active',
        ]);

        $this->valeDeductionType = DeductionType::where('code', 'VALE')->firstOrFail();
        $this->valeDeductionType->update(['is_loan_enabled' => true]);

        $this->valeLoanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $this->valeDeductionType->id,
            'name' => 'Vale / Cash Advance',
            'default_amortization_per_cutoff' => 800.00,
            'default_installments_count' => 10,
            'is_active' => true,
        ]);
    }

    public function test_automated_mode_auto_fills_min_of_per_cutoff_amount_and_current_balance(): void
    {
        // Active loan with per_cutoff_amount = 800.00
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'total_amount' => 5000.00,
            'per_cutoff_amount' => 800.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-08-1st',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        // Generate draft in Automated Mode (use_fixed_amount = false)
        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$this->employee->id], [
            'use_fixed_amount' => false,
        ]);

        $draft = $headers->first();
        $this->assertNotNull($draft);

        // Verify that Vale loan payout is auto-filled with 800.00
        $valePayOut = $draft->payOuts()->where('deduction_type_id', $this->valeDeductionType->id)->first();
        $this->assertNotNull($valePayOut);
        $this->assertEquals(800.00, (float) $valePayOut->rate);
    }

    public function test_manual_override_mode_replaces_automated_loan_deduction_amount(): void
    {
        // Active loan with per_cutoff_amount = 800.00
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'total_amount' => 5000.00,
            'per_cutoff_amount' => 800.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-08-2nd',
            'period_start' => '2026-08-16',
            'period_end' => '2026-08-31',
            'pay_date' => '2026-08-31',
            'status' => 'open',
        ]);

        // Generate draft in Manual Mode (use_fixed_amount = true) with fixed amount 1000.00
        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$this->employee->id], [
            'use_fixed_amount' => true,
            'deductions' => [
                [
                    'deduction_type' => 'VALE',
                    'display' => '1',
                    'amount' => '1000.00',
                ],
            ],
        ]);

        $draft = $headers->first();
        $this->assertNotNull($draft);

        $valePayOut = $draft->payOuts()->where('deduction_type_id', $this->valeDeductionType->id)->first();
        $this->assertNotNull($valePayOut);
        $this->assertEquals(1000.00, (float) $valePayOut->rate);

        // Finalize draft
        $service->approvePayroll($draft, $this->superAdmin);

        // Check loan ledger update
        $loan->refresh();
        $this->assertEquals(1000.00, (float) $loan->amount_paid);
        $this->assertEquals(4000.00, (float) $loan->current_balance);

        $payment = LoanPayment::where('emp_loan_id', $loan->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(1000.00, (float) $payment->amount_paid);
        $this->assertEquals('PAYROLL', $payment->source);
    }

    public function test_unchecked_loan_deduction_checkbox_omits_loan_deduction(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'total_amount' => 5000.00,
            'per_cutoff_amount' => 800.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-09-1st',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'pay_date' => '2026-09-15',
            'status' => 'open',
        ]);

        // Generate draft with VALE checkbox unchecked (display = '0')
        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$this->employee->id], [
            'use_fixed_amount' => false,
            'deductions' => [
                [
                    'deduction_type' => 'VALE',
                    'display' => '0',
                ],
            ],
        ]);

        $draft = $headers->first();
        $valePayOut = $draft->payOuts()->where('deduction_type_id', $this->valeDeductionType->id)->first();
        $this->assertNull($valePayOut);

        // Finalize draft
        $service->approvePayroll($draft, $this->superAdmin);

        // Verify zero payment recorded on ledger
        $loan->refresh();
        $this->assertEquals(0.00, (float) $loan->amount_paid);
        $this->assertEquals(5000.00, (float) $loan->current_balance);
        $this->assertDatabaseMissing('loan_payments', ['emp_loan_id' => $loan->id]);
    }
}
