<?php

namespace Tests\Feature;

use App\Exports\PagIBIGReportExport;
use App\Exports\PhilHealthReportExport;
use App\Exports\SSSReportExport;
use App\Exports\WithholdingTaxReportExport;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatutoryReportFinalizedScopingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $emp1;
    protected EmployeeMaster $emp2;
    protected PayrollPeriod $periodAug1;
    protected PayrollPeriod $periodAug2;
    protected DeductionType $sssDeduction;
    protected DeductionType $phDeduction;
    protected DeductionType $pagibigDeduction;
    protected DeductionType $taxDeduction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\StatutoryRulesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Statutory Scope Co',
            'company_code' => 'STATCO',
            'email'        => 'stat@example.com',
            'status'       => 1,
        ]);

        $this->emp1 = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'employee_code' => 'EMP-01',
            'first_name'    => 'Juan',
            'last_name'     => 'Luna',
            'status'        => 'active',
            'date_hired'    => '2026-01-01',
            'monthly_rate'  => 20000.00,
        ]);

        $this->emp2 = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'employee_code' => 'EMP-02',
            'first_name'    => 'Jose',
            'last_name'     => 'Rizal',
            'status'        => 'active',
            'date_hired'    => '2026-01-01',
            'monthly_rate'  => 30000.00,
        ]);

        $this->periodAug1 = PayrollPeriod::create([
            'company_id'   => $this->company->id,
            'period_name'  => 'August 2026 1st Half',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-15',
            'pay_date'     => '2026-08-15',
            'frequency'    => 'semi_monthly',
            'status'       => 'closed',
        ]);

        $this->periodAug2 = PayrollPeriod::create([
            'company_id'   => $this->company->id,
            'period_name'  => 'August 2026 2nd Half',
            'period_start' => '2026-08-16',
            'period_end'   => '2026-08-31',
            'pay_date'     => '2026-08-31',
            'frequency'    => 'semi_monthly',
            'status'       => 'closed',
        ]);

        $this->sssDeduction = DeductionType::where('code', 'SSS')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'name' => 'SSS Contribution', 'code' => 'SSS', 'is_active' => true]);

        $this->phDeduction = DeductionType::where('code', 'PHILHEALTH')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'name' => 'PhilHealth Contribution', 'code' => 'PHILHEALTH', 'is_active' => true]);

        $this->pagibigDeduction = DeductionType::where('code', 'PAGIBIG')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'name' => 'Pag-IBIG Contribution', 'code' => 'PAGIBIG', 'is_active' => true]);

        $this->taxDeduction = DeductionType::where('code', 'TAX')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'name' => 'Withholding Tax', 'code' => 'TAX', 'is_active' => true]);
    }

    public function test_statutory_reports_strictly_scope_to_finalized_pay_headers(): void
    {
        // 1. Finalized PayHeader for emp1
        $finalHeader = PayHeader::create([
            'payroll_period_id' => $this->periodAug1->id,
            'employee_id'       => $this->emp1->id,
            'total_gross_pay'   => 10000.00,
            'total_deductions'  => 800.00,
            'net_pay'           => 9200.00,
            'status'            => 'approved',
        ]);

        PayOut::create([
            'pay_id'            => $finalHeader->id,
            'deduction_type_id' => $this->sssDeduction->id,
            'rate'              => 450.00,
            'number_input'      => 1.00,
        ]);

        PayOut::create([
            'pay_id'            => $finalHeader->id,
            'deduction_type_id' => $this->phDeduction->id,
            'rate'              => 250.00,
            'number_input'      => 1.00,
        ]);

        PayOut::create([
            'pay_id'            => $finalHeader->id,
            'deduction_type_id' => $this->pagibigDeduction->id,
            'rate'              => 100.00,
            'number_input'      => 1.00,
        ]);

        GovernmentContribution::create([
            'employee_id'        => $this->emp1->id,
            'company_id'         => $this->company->id,
            'payroll_period_id'  => $this->periodAug1->id,
            'payroll_draft_id'   => $finalHeader->id,
            'type'               => 'sss',
            'employee_share'     => 450.00,
            'employer_share'     => 965.00,
            'total_contribution' => 1415.00,
            'period_start'       => $this->periodAug1->period_start,
            'period_end'         => $this->periodAug1->period_end,
            'status'             => 'approved',
        ]);

        GovernmentContribution::create([
            'employee_id'        => $this->emp1->id,
            'company_id'         => $this->company->id,
            'payroll_period_id'  => $this->periodAug1->id,
            'payroll_draft_id'   => $finalHeader->id,
            'type'               => 'philhealth',
            'employee_share'     => 250.00,
            'employer_share'     => 250.00,
            'total_contribution' => 500.00,
            'period_start'       => $this->periodAug1->period_start,
            'period_end'         => $this->periodAug1->period_end,
            'status'             => 'approved',
        ]);

        GovernmentContribution::create([
            'employee_id'        => $this->emp1->id,
            'company_id'         => $this->company->id,
            'payroll_period_id'  => $this->periodAug1->id,
            'payroll_draft_id'   => $finalHeader->id,
            'type'               => 'pagibig',
            'employee_share'     => 100.00,
            'employer_share'     => 100.00,
            'total_contribution' => 200.00,
            'period_start'       => $this->periodAug1->period_start,
            'period_end'         => $this->periodAug1->period_end,
            'status'             => 'approved',
        ]);

        // 2. Draft/Rejected PayHeader for emp2
        $draftHeader = PayHeader::create([
            'payroll_period_id' => $this->periodAug1->id,
            'employee_id'       => $this->emp2->id,
            'total_gross_pay'   => 15000.00,
            'total_deductions'  => 1200.00,
            'net_pay'           => 13800.00,
            'status'            => 'draft',
        ]);

        GovernmentContribution::create([
            'employee_id'        => $this->emp2->id,
            'company_id'         => $this->company->id,
            'payroll_period_id'  => $this->periodAug1->id,
            'payroll_draft_id'   => $draftHeader->id,
            'type'               => 'sss',
            'employee_share'     => 675.00,
            'employer_share'     => 1425.00,
            'total_contribution' => 2100.00,
            'period_start'       => $this->periodAug1->period_start,
            'period_end'         => $this->periodAug1->period_end,
            'status'             => 'pending',
        ]);

        // SSS Export
        $sssExport = new SSSReportExport($this->company->id, '2026-08');
        $sssRows = $sssExport->collection();
        $this->assertCount(1, $sssRows);
        $this->assertEquals('EMP-01', $sssRows[0]['employee_number']);
        $this->assertEquals(450.00, $sssRows[0]['employee_contribution']);
        $this->assertEquals(950.00, $sssRows[0]['employer_contribution']);
        $this->assertEquals(15.00, $sssRows[0]['ec_contribution']);
        $this->assertEquals(1415.00, $sssRows[0]['total_contribution']);

        // PhilHealth Export
        $phExport = new PhilHealthReportExport($this->company->id, '2026-08');
        $phRows = $phExport->collection();
        $this->assertCount(1, $phRows);
        $this->assertEquals('EMP-01', $phRows[0]['employee_number']);
        $this->assertEquals(250.00, $phRows[0]['employee_contribution']);
        $this->assertEquals(250.00, $phRows[0]['employer_contribution']);
        $this->assertEquals(500.00, $phRows[0]['total_contribution']);

        // Pag-IBIG Export
        $piExport = new PagIBIGReportExport($this->company->id, '2026-08');
        $piRows = $piExport->collection();
        $this->assertCount(1, $piRows);
        $this->assertEquals('EMP-01', $piRows[0]['employee_number']);
        $this->assertEquals(100.00, $piRows[0]['employee_contribution']);
        $this->assertEquals(100.00, $piRows[0]['employer_contribution']);
        $this->assertEquals(200.00, $piRows[0]['total_contribution']);
    }

    public function test_multi_cutoff_sss_er_ec_reconciliation(): void
    {
        // 1st Half August for emp1
        $h1 = PayHeader::create([
            'payroll_period_id' => $this->periodAug1->id,
            'employee_id'       => $this->emp1->id,
            'total_gross_pay'   => 10000.00,
            'total_deductions'  => 450.00,
            'net_pay'           => 9550.00,
            'status'            => 'posted',
        ]);

        PayOut::create([
            'pay_id'            => $h1->id,
            'deduction_type_id' => $this->sssDeduction->id,
            'rate'              => 450.00,
            'number_input'      => 1.00,
        ]);

        GovernmentContribution::create([
            'employee_id'        => $this->emp1->id,
            'company_id'         => $this->company->id,
            'payroll_period_id'  => $this->periodAug1->id,
            'payroll_draft_id'   => $h1->id,
            'type'               => 'sss',
            'employee_share'     => 450.00,
            'employer_share'     => 965.00,
            'total_contribution' => 1415.00,
            'period_start'       => $this->periodAug1->period_start,
            'period_end'         => $this->periodAug1->period_end,
            'status'             => 'posted',
        ]);

        // 2nd Half August for emp1
        $h2 = PayHeader::create([
            'payroll_period_id' => $this->periodAug2->id,
            'employee_id'       => $this->emp1->id,
            'total_gross_pay'   => 10000.00,
            'total_deductions'  => 450.00,
            'net_pay'           => 9550.00,
            'status'            => 'paid',
        ]);

        PayOut::create([
            'pay_id'            => $h2->id,
            'deduction_type_id' => $this->sssDeduction->id,
            'rate'              => 450.00,
            'number_input'      => 1.00,
        ]);

        GovernmentContribution::create([
            'employee_id'        => $this->emp1->id,
            'company_id'         => $this->company->id,
            'payroll_period_id'  => $this->periodAug2->id,
            'payroll_draft_id'   => $h2->id,
            'type'               => 'sss',
            'employee_share'     => 450.00,
            'employer_share'     => 965.00,
            'total_contribution' => 1415.00,
            'period_start'       => $this->periodAug2->period_start,
            'period_end'         => $this->periodAug2->period_end,
            'status'             => 'paid',
        ]);

        $export = new SSSReportExport($this->company->id, '2026-08');
        $rows = $export->collection();

        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertEquals(20000.00, $row['gross_income']);
        $this->assertEquals(900.00, $row['employee_contribution']);
        $this->assertEquals(1900.00, $row['employer_contribution']);
        $this->assertEquals(30.00, $row['ec_contribution']);
        $this->assertEquals(2830.00, $row['total_contribution']);
    }

    public function test_statutory_recalculation_and_reopened_correction_synchronizes_contributions(): void
    {
        $processingService = app(\App\Services\PayrollProcessingService::class);
        $workflowService = app(\App\Services\PayrollWorkflowService::class);

        $incomeType = \App\Models\IncomeType::where('code', 'BASIC')->first()
            ?? \App\Models\IncomeType::create([
                'company_id' => $this->company->id,
                'name'       => 'Basic Pay',
                'code'       => 'BASIC',
                'is_taxable' => true,
                'is_active'  => true,
            ]);

        // 1. Initial Draft with 10,000 gross (20,000 monthly basis)
        $header = PayHeader::create([
            'payroll_period_id' => $this->periodAug1->id,
            'employee_id'       => $this->emp1->id,
            'total_gross_pay'   => 10000.00,
            'total_deductions'  => 450.00,
            'net_pay'           => 9550.00,
            'status'            => 'draft',
        ]);

        $payIn = PayIn::create([
            'pay_id'         => $header->id,
            'income_type_id' => $incomeType->id,
            'rate'           => 10000.00,
            'number_input'   => 1.00,
        ]);

        $sssPayOut = PayOut::create([
            'pay_id'            => $header->id,
            'deduction_type_id' => $this->sssDeduction->id,
            'rate'              => 450.00,
            'number_input'      => 1.00,
        ]);

        $phPayOut = PayOut::create([
            'pay_id'            => $header->id,
            'deduction_type_id' => $this->phDeduction->id,
            'rate'              => 250.00,
            'number_input'      => 1.00,
        ]);

        $piPayOut = PayOut::create([
            'pay_id'            => $header->id,
            'deduction_type_id' => $this->pagibigDeduction->id,
            'rate'              => 100.00,
            'number_input'      => 1.00,
        ]);

        $processingService->recalculateDraftStatutoryDeductions($header);

        $initialSssContrib = GovernmentContribution::where('payroll_draft_id', $header->id)->where('type', 'sss')->first();
        $this->assertNotNull($initialSssContrib);
        $this->assertEquals(500.00, (float) $initialSssContrib->employee_share);
        $this->assertEquals(1015.00, (float) $initialSssContrib->employer_share);

        // 2. Change income: update rate from 10,000 to 20,000 (40,000 monthly basis)
        $payIn->update(['rate' => 20000.00]);
        $processingService->recalculateDraftStatutoryDeductions($header);

        // Assert PayOuts were updated
        $sssPayOut->refresh();
        $this->assertEquals(875.00, (float) $sssPayOut->rate);

        // Assert GovernmentContribution was synchronized
        $updatedSssContrib = GovernmentContribution::where('payroll_draft_id', $header->id)->where('type', 'sss')->first();
        $this->assertEquals(875.00, (float) $updatedSssContrib->employee_share);
        $this->assertEquals(1765.00, (float) $updatedSssContrib->employer_share);

        // 3. Finalize & Export
        $header->update(['status' => 'approved']);
        $updatedSssContrib->update(['status' => 'approved']);

        $export = new SSSReportExport($this->company->id, '2026-08');
        $rows = $export->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals(875.00, $rows[0]['employee_contribution']);
        $this->assertEquals(1750.00, $rows[0]['employer_contribution']);
        $this->assertEquals(15.00, $rows[0]['ec_contribution']);
        $this->assertEquals(2640.00, $rows[0]['total_contribution']);

        // 4. Reopen for correction and edit again (reopened-approved correction flow)
        $workflowService->reopenForCorrection($header, 'Salary adjustment needed', $this->adminUser);
        $this->assertEquals('pending_approval', $header->fresh()->status);

        // Change income to 15,000 (30,000 monthly basis)
        $payIn->update(['rate' => 15000.00]);
        $processingService->recalculateDraftStatutoryDeductions($header->fresh());
        $header = $header->fresh();
        $header->recalculateTotals();
        $workflowService->transition($header, \App\Services\PayrollWorkflowService::STATE_APPROVED);

        $reExport = new SSSReportExport($this->company->id, '2026-08');
        $reRows = $reExport->collection();
        $this->assertCount(1, $reRows);
        $this->assertEquals(750.00, $reRows[0]['employee_contribution']);
        $this->assertEquals(1500.00, $reRows[0]['employer_contribution']);
        $this->assertEquals(15.00, $reRows[0]['ec_contribution']);
        $this->assertEquals(2265.00, $reRows[0]['total_contribution']);
    }
}

