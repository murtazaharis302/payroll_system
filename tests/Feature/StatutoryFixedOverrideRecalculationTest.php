<?php

namespace Tests\Feature;

use App\Exports\SSSReportExport;
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

class StatutoryFixedOverrideRecalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected PayrollPeriod $period;
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

        $this->company = Company::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Fixed Zero Override Co',
            'company_code' => 'FZOC',
            'email'        => 'fzoc@example.com',
            'status'       => 1,
        ]);

        $this->company->setDeductionDefaults([
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'SSS', 'display' => true, 'amount' => 0.00],
                ['deduction_type' => 'PHILHEALTH', 'display' => true, 'amount' => 0.00],
                ['deduction_type' => 'PAGIBIG', 'display' => true, 'amount' => 0.00],
            ],
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'employee_code' => 'EMP-FZ-01',
            'first_name'    => 'Juan',
            'last_name'     => 'Dela Cruz',
            'status'        => 'active',
            'date_hired'    => '2026-01-01',
            'monthly_rate'  => 30000.00,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id'   => $this->company->id,
            'period_name'  => 'August 2026 1st Half',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-15',
            'pay_date'     => '2026-08-15',
            'frequency'    => 'semi_monthly',
            'status'       => 'closed',
        ]);

        $this->basicIncome = IncomeType::where('code', 'BASIC')->first()
            ?? IncomeType::create([
                'company_id' => $this->company->id,
                'name'       => 'Basic Pay',
                'code'       => 'BASIC',
                'is_taxable' => true,
                'is_active'  => true,
            ]);

        $this->sssDeduction = DeductionType::where('code', 'SSS')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'name' => 'SSS Contribution', 'code' => 'SSS', 'is_active' => true]);

        $this->phDeduction = DeductionType::where('code', 'PHILHEALTH')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'name' => 'PhilHealth Contribution', 'code' => 'PHILHEALTH', 'is_active' => true]);

        $this->pagibigDeduction = DeductionType::where('code', 'PAGIBIG')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'name' => 'Pag-IBIG Contribution', 'code' => 'PAGIBIG', 'is_active' => true]);
    }

    public function test_explicit_zero_fixed_statutory_amount_remains_zero_on_income_change_and_recalculation(): void
    {
        $service = app(PayrollProcessingService::class);

        // 1. Create draft with initial 0.00 fixed amounts and initial income
        $draft = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id'       => $this->employee->id,
            'total_gross_pay'   => 15000.00,
            'total_deductions'  => 0.00,
            'net_pay'           => 15000.00,
            'status'            => 'draft',
        ]);

        $payIn = PayIn::create([
            'pay_id'         => $draft->id,
            'income_type_id' => $this->basicIncome->id,
            'rate'           => 15000.00,
            'number_input'   => 1.00,
        ]);

        $sssPayOut = PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $this->sssDeduction->id, 'rate' => 0.00, 'number_input' => 1.00]);
        $phPayOut = PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $this->phDeduction->id, 'rate' => 0.00, 'number_input' => 1.00]);
        $piPayOut = PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $this->pagibigDeduction->id, 'rate' => 0.00, 'number_input' => 1.00]);

        $createContrib = fn($type) => GovernmentContribution::create([
            'employee_id'        => $this->employee->id,
            'company_id'         => $this->company->id,
            'payroll_period_id'  => $this->period->id,
            'payroll_draft_id'   => $draft->id,
            'type'               => $type,
            'employee_share'     => 0.00,
            'employer_share'     => 0.00,
            'total_contribution' => 0.00,
            'period_start'       => $this->period->period_start,
            'period_end'         => $this->period->period_end,
            'status'             => 'draft',
            'notes'              => 'Fixed statutory override - ' . ucfirst($type),
        ]);

        $sssContrib = $createContrib('sss');
        $phContrib = $createContrib('philhealth');
        $piContrib = $createContrib('pagibig');

        // 2. Change income significantly (increase rate to 35,000 -> 70,000 monthly basis)
        $payIn->update(['rate' => 35000.00]);

        // 3. Recalculate draft statutory deductions
        $service->recalculateDraftStatutoryDeductions($draft->fresh());

        // 4. Assert PayOut rates remain 0.00
        $this->assertEquals(0.00, (float) $sssPayOut->refresh()->rate);
        $this->assertEquals(0.00, (float) $phPayOut->refresh()->rate);
        $this->assertEquals(0.00, (float) $piPayOut->refresh()->rate);

        // 5. Assert GovernmentContribution rows remain 0.00
        $this->assertEquals(0.00, (float) $sssContrib->refresh()->employee_share);
        $this->assertEquals(0.00, (float) $sssContrib->employer_share);
        $this->assertEquals(0.00, (float) $phContrib->refresh()->employee_share);
        $this->assertEquals(0.00, (float) $phContrib->employer_share);
        $this->assertEquals(0.00, (float) $piContrib->refresh()->employee_share);
        $this->assertEquals(0.00, (float) $piContrib->employer_share);

        // 6. Finalize & Export
        $draft->update(['status' => 'approved', 'total_gross_pay' => 35000.00]);
        $sssContrib->update(['status' => 'approved']);
        $phContrib->update(['status' => 'approved']);
        $piContrib->update(['status' => 'approved']);

        $sssExport = new SSSReportExport($this->company->id, '2026-08');
        $sssRows = $sssExport->collection();
        $this->assertCount(1, $sssRows);
        $this->assertEquals(0.00, $sssRows[0]['employee_contribution']);
        $this->assertEquals(0.00, $sssRows[0]['employer_contribution']);
        $this->assertEquals(0.00, $sssRows[0]['ec_contribution']);
        $this->assertEquals(0.00, $sssRows[0]['total_contribution']);
    }

    public function test_positive_fixed_amount_override_preserves_custom_rate_and_zero_employer(): void
    {
        $this->company->setDeductionDefaults([
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'PHILHEALTH', 'display' => true, 'amount' => 550.00],
            ],
        ]);

        $service = app(PayrollProcessingService::class);

        $draft = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id'       => $this->employee->id,
            'total_gross_pay'   => 15000.00,
            'total_deductions'  => 550.00,
            'net_pay'           => 14450.00,
            'status'            => 'draft',
        ]);

        $payIn = PayIn::create([
            'pay_id'         => $draft->id,
            'income_type_id' => $this->basicIncome->id,
            'rate'           => 15000.00,
            'number_input'   => 1.00,
        ]);

        $phPayOut = PayOut::create([
            'pay_id'            => $draft->id,
            'deduction_type_id' => $this->phDeduction->id,
            'rate'              => 550.00,
            'number_input'      => 1.00,
        ]);

        $phContrib = GovernmentContribution::create([
            'employee_id'        => $this->employee->id,
            'company_id'         => $this->company->id,
            'payroll_period_id'  => $this->period->id,
            'payroll_draft_id'   => $draft->id,
            'type'               => 'philhealth',
            'employee_share'     => 550.00,
            'employer_share'     => 0.00,
            'total_contribution' => 550.00,
            'period_start'       => $this->period->period_start,
            'period_end'         => $this->period->period_end,
            'status'             => 'draft',
            'notes'              => 'Fixed statutory override - Philhealth',
        ]);

        // Change income
        $payIn->update(['rate' => 25000.00]);
        $service->recalculateDraftStatutoryDeductions($draft->fresh());

        $phPayOut->refresh();
        $this->assertEquals(550.00, (float) $phPayOut->rate);

        $phContrib->refresh();
        $this->assertEquals(550.00, (float) $phContrib->employee_share);
        $this->assertEquals(0.00, (float) $phContrib->employer_share);
        $this->assertEquals(550.00, (float) $phContrib->total_contribution);
    }
}
