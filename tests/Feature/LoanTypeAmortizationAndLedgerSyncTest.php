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

class LoanTypeAmortizationAndLedgerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $companyAdmin;
    protected User $hrUser;
    protected User $superAdmin;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected LoanType $loanType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(\Database\Seeders\MenusTableSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_loan@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Loan Module Test Company',
            'email' => 'loancomp@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'email' => 'compadmin_loan@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->hrUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'HR Staff',
            'email' => 'hr_loan@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-LOAN-001',
            'first_name' => 'Gordon',
            'last_name' => 'Freeman',
            'date_hired' => '2026-01-01',
            'position' => 'Physicist',
            'monthly_rate' => 30000.00,
            'daily_rate' => 1153.85,
            'hourly_rate' => 144.23,
            'status' => 'active',
        ]);

        $valeDeduction = DeductionType::firstOrCreate(
            ['code' => 'VALE'],
            ['name' => 'Cash Advance / Vale', 'is_active' => true, 'is_loan_enabled' => true]
        );
        $valeDeduction->update(['is_loan_enabled' => true]);

        $this->loanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $valeDeduction->id,
            'name' => 'Cash Advance / Vale',
            'default_amortization_per_cutoff' => 500.00,
            'default_installments_count' => 20,
            'is_active' => true,
        ]);
    }

    public function test_company_admin_can_configure_loan_types_amortization_settings(): void
    {
        $deduction = DeductionType::first();
        $response = $this->actingAs($this->companyAdmin)->post(
            route('loan-types.store'),
            [
                'name' => 'SSS Loan',
                'deduction_type_id' => $deduction->id,
                'default_amortization_per_cutoff' => 750.00,
                'default_installments_count' => 12,
                'is_active' => 1,
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_types', [
            'name' => 'SSS Loan',
            'company_id' => $this->company->id,
            'default_amortization_per_cutoff' => 750.00,
            'default_installments_count' => 12,
        ]);
    }

    public function test_hr_user_is_locked_out_from_loan_setup_when_permission_disabled(): void
    {
        $loanTypeMenu = \App\Models\Menu::where('module', 'loan_types')->firstOrFail();
        \App\Models\MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $loanTypeMenu->id],
            ['is_enabled' => 0]
        );

        $indexResponse = $this->actingAs($this->hrUser)->get(route('loan-types.index'));
        $indexResponse->assertStatus(403);

        $storeResponse = $this->actingAs($this->hrUser)->post(
            route('loan-types.store'),
            [
                'name' => 'Unauthorized Loan Type',
                'default_amortization_per_cutoff' => 1000.00,
            ]
        );
        $storeResponse->assertStatus(403);
    }

    public function test_creating_employee_loan_auto_populates_per_cutoff_amortization(): void
    {
        $response = $this->actingAs($this->companyAdmin)->post(
            route('loan-monitor.store-loan'),
            [
                'employee_id'           => $this->employee->id,
                'loan_type_id'          => $this->loanType->id,
                'principal_amount'      => 10000.00,
                'start_date'            => '2026-08-01',
                'purpose'               => 'Cash Loan',
                'terms_months'          => 10,
                'monthly_interest_rate' => 0.00,
            ]
        );

        $response->assertRedirect();

        $loan = EmpLoan::where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($loan);
        $this->assertEquals(10000.00, (float) $loan->principal_amount);
        $this->assertEquals(10000.00, (float) $loan->current_balance);
        $this->assertEquals(500.00, (float) $loan->per_cutoff_amount);
    }

    public function test_payroll_finalization_syncs_loan_payment_and_updates_ledger_balance(): void
    {
        // 1. Create employee loan
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 10000.00,
            'total_amount' => 10000.00,
            'per_cutoff_amount' => 500.00,
            'current_balance' => 10000.00,
            'status' => 'ACTIVE',
        ]);

        // 2. Create payroll period & draft
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-08-A',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'days_worked' => 13.0,
            'total_gross_pay' => 15000.00,
            'total_deductions' => 500.00,
            'net_pay' => 14500.00,
            'status' => 'draft',
        ]);

        $deductionType = DeductionType::where('code', 'VALE')->firstOrFail();

        $incomeType = \App\Models\IncomeType::withoutGlobalScopes()->where('code', 'BASIC')->first();
        if (!$incomeType) {
            $incomeType = \App\Models\IncomeType::create([
                'company_id' => $this->company->id,
                'name' => 'Basic Salary',
                'code' => 'BASIC',
                'type' => 'taxable',
                'is_active' => true,
            ]);
        }
        $draft->payIns()->create([
            'pay_id' => $draft->id,
            'income_type_id' => $incomeType->id,
            'rate' => 15000.00,
            'number_input' => 1.00,
        ]);

        // Attach loan payout line item
        $draft->payOuts()->create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $deductionType->id,
            'loan_id' => $loan->id,
            'number_input' => 1.00,
            'rate' => 500.00,
        ]);

        // 3. Approve / Finalize payroll draft
        $draft->update(['status' => \App\Services\PayrollWorkflowService::STATE_PENDING_APPROVAL]);
        $service = app(PayrollProcessingService::class);
        $service->approvePayroll($draft, $this->superAdmin);

        // 4. Verify LoanPayment ledger entry and balance recalculation
        $loan->refresh();
        $this->assertEquals(500.00, (float) $loan->amount_paid);
        $this->assertEquals(9500.00, (float) $loan->current_balance);
        $this->assertEquals('ACTIVE', $loan->status);

        $payment = LoanPayment::where('emp_loan_id', $loan->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(500.00, (float) $payment->amount_paid);
        $this->assertEquals($draft->id, $payment->pay_header_id);
    }
}
