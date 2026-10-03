<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatutoryZeroIncomeAndLoanIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected DeductionType $sssDeduction;
    protected DeductionType $phDeduction;
    protected DeductionType $pagibigDeduction;
    protected IncomeType $basicIncome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\StatutoryRulesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $this->adminUser = User::factory()->create(['role' => 'admin']);

        $this->basicIncome = IncomeType::where('code', 'BASIC')->first()
            ?? IncomeType::create([
                'name'       => 'Basic Pay',
                'code'       => 'BASIC',
                'is_taxable' => true,
                'is_active'  => true,
            ]);

        $this->sssDeduction = DeductionType::where('code', 'SSS')->first()
            ?? DeductionType::create(['name' => 'SSS Contribution', 'code' => 'SSS', 'is_active' => true]);

        $this->phDeduction = DeductionType::where('code', 'PHILHEALTH')->first()
            ?? DeductionType::create(['name' => 'PhilHealth Contribution', 'code' => 'PHILHEALTH', 'is_active' => true]);

        $this->pagibigDeduction = DeductionType::where('code', 'PAGIBIG')->first()
            ?? DeductionType::create(['name' => 'Pag-IBIG Contribution', 'code' => 'PAGIBIG', 'is_active' => true]);
    }

    public function test_normal_statutory_starting_at_zero_income_recalculates_above_zero_when_income_increases(): void
    {
        $service = app(PayrollProcessingService::class);

        // Standard company without fixed deduction defaults
        $company = Company::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Normal Recalc Co',
            'company_code' => 'NRC',
            'email'        => 'nrc@example.com',
            'status'       => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id'    => $company->id,
            'employee_code' => 'EMP-NR-01',
            'first_name'    => 'Normal',
            'last_name'     => 'Employee',
            'monthly_rate'  => 0.00,
            'status'        => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id'   => $company->id,
            'period_name'  => 'August 2026 Normal Period',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-15',
            'pay_date'     => '2026-08-15',
            'frequency'    => 'semi_monthly',
            'status'       => 'open',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id'       => $employee->id,
            'total_gross_pay'   => 0.00,
            'total_deductions'  => 0.00,
            'net_pay'           => 0.00,
            'status'            => 'draft',
        ]);

        $payIn = PayIn::create([
            'pay_id'         => $draft->id,
            'income_type_id' => $this->basicIncome->id,
            'rate'           => 0.00,
            'number_input'   => 1.00,
        ]);

        $sssPayOut = PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $this->sssDeduction->id, 'rate' => 0.00, 'number_input' => 1.00]);
        $phPayOut = PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $this->phDeduction->id, 'rate' => 0.00, 'number_input' => 1.00]);
        $piPayOut = PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $this->pagibigDeduction->id, 'rate' => 0.00, 'number_input' => 1.00]);

        GovernmentContribution::create([
            'employee_id'        => $employee->id,
            'company_id'         => $company->id,
            'payroll_period_id'  => $period->id,
            'payroll_draft_id'   => $draft->id,
            'type'               => 'sss',
            'employee_share'     => 0.00,
            'employer_share'     => 0.00,
            'total_contribution' => 0.00,
            'period_start'       => $period->period_start,
            'period_end'         => $period->period_end,
            'status'             => 'draft',
        ]);

        // Income increases from 0.00 to 20,000.00 (40,000 monthly basis)
        $payIn->update(['rate' => 20000.00]);
        $service->recalculateDraftStatutoryDeductions($draft->fresh());

        // Assert PayOut rates recalculated above zero
        $this->assertGreaterThan(0.00, (float) $sssPayOut->refresh()->rate);
        $this->assertGreaterThan(0.00, (float) $phPayOut->refresh()->rate);
        $this->assertGreaterThan(0.00, (float) $piPayOut->refresh()->rate);

        // Assert GovernmentContribution employer shares recalculated above zero
        $sssContrib = GovernmentContribution::where('payroll_draft_id', $draft->id)->where('type', 'sss')->first();
        $this->assertGreaterThan(0.00, (float) $sssContrib->employer_share);
        $this->assertEquals((float) $sssPayOut->rate, (float) $sssContrib->employee_share);
    }

    public function test_fixed_sss_loan_does_not_freeze_normal_statutory_sss(): void
    {
        $service = app(PayrollProcessingService::class);

        // Company with fixed SSS_LOAN default, but normal statutory SSS
        $company = Company::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Fixed Loan Default Co',
            'company_code' => 'FLDC',
            'email'        => 'fldc@example.com',
            'status'       => 1,
            'deduction_defaults' => [
                'use_fixed_amount' => true,
                'deductions' => [
                    ['deduction_type' => 'SSS_LOAN', 'display' => true, 'amount' => 500.00],
                ],
            ],
        ]);

        $emp = EmployeeMaster::create([
            'company_id'    => $company->id,
            'employee_code' => 'EMP-FL-01',
            'first_name'    => 'Loan',
            'last_name'     => 'Tester',
            'monthly_rate'  => 20000.00,
            'status'        => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id'   => $company->id,
            'period_name'  => 'August 2026 Loan Period',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-15',
            'pay_date'     => '2026-08-15',
            'frequency'    => 'semi_monthly',
            'status'       => 'open',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id'       => $emp->id,
            'total_gross_pay'   => 10000.00,
            'total_deductions'  => 500.00,
            'net_pay'           => 9500.00,
            'status'            => 'draft',
        ]);

        $payIn = PayIn::create([
            'pay_id'         => $draft->id,
            'income_type_id' => $this->basicIncome->id,
            'rate'           => 10000.00,
            'number_input'   => 1.00,
        ]);

        $sssPayOut = PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $this->sssDeduction->id, 'rate' => 500.00, 'number_input' => 1.00]);

        GovernmentContribution::create([
            'employee_id'        => $emp->id,
            'company_id'         => $company->id,
            'payroll_period_id'  => $period->id,
            'payroll_draft_id'   => $draft->id,
            'type'               => 'sss',
            'employee_share'     => 500.00,
            'employer_share'     => 1015.00,
            'total_contribution' => 1515.00,
            'period_start'       => $period->period_start,
            'period_end'         => $period->period_end,
            'status'             => 'draft',
        ]);

        // Change income to 20,000.00
        $payIn->update(['rate' => 20000.00]);
        $service->recalculateDraftStatutoryDeductions($draft->fresh());

        // Statutory SSS must recalculate dynamically to 875.00 (not frozen to 500.00)
        $this->assertEquals(875.00, (float) $sssPayOut->refresh()->rate, 'Statutory SSS must recalculate dynamically and not be frozen by fixed SSS_LOAN.');

        $sssContrib = GovernmentContribution::where('payroll_draft_id', $draft->id)->where('type', 'sss')->first();
        $this->assertEquals(875.00, (float) $sssContrib->employee_share);
        $this->assertEquals(1765.00, (float) $sssContrib->employer_share);
    }
}
