<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomatedVsManualDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        // Seed basic income & deduction types required by PayrollProcessingService
        IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'category' => 'basic']);
        IncomeType::firstOrCreate(['code' => 'OVERTIME'], ['name' => 'Overtime Pay', 'category' => 'overtime']);
        IncomeType::firstOrCreate(['code' => 'ALLOWANCE'], ['name' => 'Allowance', 'category' => 'allowance']);
        IncomeType::firstOrCreate(['code' => 'BONUS'], ['name' => 'Bonus', 'category' => 'bonus']);
        IncomeType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other Income', 'category' => 'other']);

        DeductionType::firstOrCreate(['code' => 'SSS'], ['name' => 'SSS Contribution', 'category' => 'statutory']);
        DeductionType::firstOrCreate(['code' => 'PHILHEALTH'], ['name' => 'PhilHealth Contribution', 'category' => 'statutory']);
        DeductionType::firstOrCreate(['code' => 'PAGIBIG'], ['name' => 'Pag-IBIG Contribution', 'category' => 'statutory']);
        DeductionType::firstOrCreate(['code' => 'TAX'], ['name' => 'Withholding Tax', 'category' => 'tax']);
        DeductionType::firstOrCreate(['code' => 'SSS_LOAN'], ['name' => 'SSS Loan', 'category' => 'loan']);
        DeductionType::firstOrCreate(['code' => 'PAGIBIG_LOAN'], ['name' => 'Pag-IBIG Loan', 'category' => 'loan']);
        DeductionType::firstOrCreate(['code' => 'VALE'], ['name' => 'Vale / Cash Advance', 'category' => 'loan']);
        DeductionType::firstOrCreate(['code' => 'GOODS'], ['name' => 'Goods Purchase', 'category' => 'loan']);
        DeductionType::firstOrCreate(['code' => 'TARDINESS'], ['name' => 'Late / Tardiness', 'category' => 'attendance']);
        DeductionType::firstOrCreate(['code' => 'ABSENCES'], ['name' => 'Absences', 'category' => 'attendance']);
        DeductionType::firstOrCreate(['code' => 'UNDERTIME'], ['name' => 'Undertime', 'category' => 'attendance']);
        DeductionType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other Deductions', 'category' => 'other']);
    }

    public function test_excluded_deductions_produce_no_payouts_or_contributions(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Auto vs Manual Comp',
            'company_code' => 'AVM',
            'email' => 'avm@test.com',
            'status' => 1,
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'name'       => 'Operations',
            'code'       => 'OPS',
            'is_active'  => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id'    => $dept->id,
            'employee_code' => 'EMP-AVM-01',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Test Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => 'open',
        ]);

        $service = app(PayrollProcessingService::class);

        // Options payload with SSS & SSS Loan excluded (display = false)
        $options = [
            'use_fixed_amount' => false,
            'deductions' => [
                ['deduction_type' => 'SSS', 'display' => false, 'amount' => 0.00],
                ['deduction_type' => 'SSS Loan', 'display' => false, 'amount' => 0.00],
                ['deduction_type' => 'PhilHealth', 'display' => true, 'amount' => 0.00],
            ],
        ];

        $drafts = $service->generateDraft($period, [$employee->id], $options);

        $this->assertCount(1, $drafts);
        $header = $drafts->first();

        $sssDeductionType = DeductionType::where('code', 'SSS')->first();
        $sssLoanDeductionType = DeductionType::where('code', 'SSS_LOAN')->first();

        // Assert no PayOut created for SSS or SSS Loan
        $this->assertDatabaseMissing('pay_outs', [
            'pay_id' => $header->id,
            'deduction_type_id' => $sssDeductionType->id,
        ]);

        $this->assertDatabaseMissing('pay_outs', [
            'pay_id' => $header->id,
            'deduction_type_id' => $sssLoanDeductionType->id,
        ]);

        // Assert no GovernmentContribution created for SSS
        $this->assertDatabaseMissing('government_contributions', [
            'payroll_draft_id' => $header->id,
            'type' => 'sss',
        ]);

        // Assert PhilHealth WAS created
        $philhealthDeductionType = DeductionType::where('code', 'PHILHEALTH')->first();
        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $header->id,
            'deduction_type_id' => $philhealthDeductionType->id,
        ]);
    }

    public function test_manual_fixed_amount_overrides_calculated_rates(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Fixed Amount Comp',
            'company_code' => 'FAC',
            'email' => 'fac@test.com',
            'status' => 1,
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'name'       => 'Finance',
            'code'       => 'FIN',
            'is_active'  => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id'    => $dept->id,
            'employee_code' => 'EMP-FAC-01',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Fixed Test Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => 'open',
        ]);

        $service = app(PayrollProcessingService::class);

        // Options payload with use_fixed_amount = true and custom PhilHealth amount = 550.00
        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'PhilHealth', 'display' => true, 'amount' => 550.00],
                ['deduction_type' => 'Withholding Tax', 'display' => true, 'amount' => 1200.00],
            ],
        ];

        $drafts = $service->generateDraft($period, [$employee->id], $options);

        $this->assertCount(1, $drafts);
        $header = $drafts->first();

        $philhealthDeductionType = DeductionType::where('code', 'PHILHEALTH')->first();
        $taxDeductionType = DeductionType::where('code', 'TAX')->first();

        // Verify PhilHealth payout rate is exactly 550.00
        $phPayOut = PayOut::where('pay_id', $header->id)
            ->where('deduction_type_id', $philhealthDeductionType->id)
            ->first();

        $this->assertNotNull($phPayOut);
        $this->assertEquals(550.00, (float) $phPayOut->rate);

        // Verify Tax payout rate is exactly 1200.00
        $taxPayOut = PayOut::where('pay_id', $header->id)
            ->where('deduction_type_id', $taxDeductionType->id)
            ->first();

        $this->assertNotNull($taxPayOut);
        $this->assertEquals(1200.00, (float) $taxPayOut->rate);
    }

    public function test_non_statutory_manual_fixed_deduction_overrides_for_employees_without_prior_deduction_setup(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Non Stat Comp',
            'company_code' => 'NSC',
            'email' => 'nsc@test.com',
            'status' => 1,
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'name'       => 'General',
            'code'       => 'GEN',
            'is_active'  => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id'    => $dept->id,
            'employee_code' => 'EMP-NSC-01',
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Non Stat Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => 'open',
        ]);

        $service = app(PayrollProcessingService::class);

        // Options payload specifying fixed amounts for Tardiness (350.00) and Vale (500.00) for employee with no prior deduction setups
        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'TARDINESS', 'display' => true, 'amount' => 350.00],
                ['deduction_type' => 'VALE', 'display' => true, 'amount' => 500.00],
            ],
        ];

        $valeType = DeductionType::where('code', 'VALE')->first();
        $loanType = \App\Models\LoanType::create([
            'company_id' => $company->id,
            'name' => 'Vale Loan',
            'code' => 'VALE',
            'deduction_type_id' => $valeType->id,
            'is_active' => true,
        ]);

        EmpLoan::create([
            'employee_id' => $employee->id,
            'loan_type_id' => $loanType->id,
            'loan_reference_no' => 'VALE-001',
            'principal_amount' => 5000.00,
            'total_repayment' => 5000.00,
            'current_balance' => 5000.00,
            'per_cutoff_amount' => 500.00,
            'loan_date' => '2026-08-01',
            'start_period_id' => $period->id,
            'status' => 'approved',
        ]);

        $drafts = $service->generateDraft($period, [$employee->id], $options);

        $this->assertCount(1, $drafts);
        $header = $drafts->first();

        $tardinessType = DeductionType::where('code', 'TARDINESS')->first();
        $valeType = DeductionType::where('code', 'VALE')->first();

        $tardinessPayOut = PayOut::where('pay_id', $header->id)
            ->where('deduction_type_id', $tardinessType->id)
            ->first();
        $this->assertNotNull($tardinessPayOut);
        $this->assertEquals(350.00, (float) $tardinessPayOut->rate);

        $valePayOut = PayOut::where('pay_id', $header->id)
            ->where('deduction_type_id', $valeType->id)
            ->first();
        $this->assertNotNull($valePayOut);
        $this->assertEquals(500.00, (float) $valePayOut->rate);
    }
}
