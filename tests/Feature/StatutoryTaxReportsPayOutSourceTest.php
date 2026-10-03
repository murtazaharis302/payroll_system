<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\SSSReportExport;
use App\Exports\PhilHealthReportExport;
use App\Exports\PagIBIGReportExport;
use App\Exports\WithholdingTaxReportExport;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StatutoryTaxReportsPayOutSourceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected EmployeeMaster $empWithDeductions;
    protected EmployeeMaster $empWithoutGovtContrib;
    protected EmployeeMaster $empWithoutDeductions;
    protected PayrollPeriod $period;
    protected string $monthStr = '2026-10';

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_stat_test@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::SUPER_ADMIN,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Statutory Source Test Corp',
            'email' => 'stat_corp@test.com',
            'status' => 1,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin_stat_corp@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => $this->company->id,
            'status' => 1,
        ]);

        // Emp 1: Has PayOuts and GovernmentContribution records
        $this->empWithDeductions = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-ST01',
            'first_name' => 'Alice',
            'last_name' => 'Statutory',
            'status' => 'active',
            'monthly_rate' => 30000,
        ]);

        // Emp 2: Has PayOuts but NO GovernmentContribution records
        $this->empWithoutGovtContrib = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-ST02',
            'first_name' => 'Bob',
            'last_name' => 'NoGovtRow',
            'status' => 'active',
            'monthly_rate' => 35000,
        ]);

        // Emp 3: Has PayHeader with loan deductions (SSS_LOAN, PAGIBIG_LOAN) but NO statutory contribution PayOuts
        $this->empWithoutDeductions = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-ST03',
            'first_name' => 'Charlie',
            'last_name' => 'LoansOnly',
            'status' => 'active',
            'monthly_rate' => 40000,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'October 1-15 Cutoff',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'pay_date' => '2026-10-20',
            'status' => 'completed',
        ]);

        $basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        $sssType = DeductionType::firstOrCreate(['code' => 'SSS'], ['name' => 'SSS Contribution', 'is_active' => true]);
        $phType = DeductionType::firstOrCreate(['code' => 'PHILHEALTH'], ['name' => 'PhilHealth Contribution', 'is_active' => true]);
        $pagibigType = DeductionType::firstOrCreate(['code' => 'PAGIBIG'], ['name' => 'Pag-IBIG Contribution', 'is_active' => true]);
        $taxType = DeductionType::firstOrCreate(['code' => 'TAX'], ['name' => 'Withholding Tax', 'is_active' => true]);

        $sssLoanType = DeductionType::firstOrCreate(['code' => 'SSS_LOAN'], ['name' => 'SSS Loan Deduction', 'is_active' => true]);
        $pagibigLoanType = DeductionType::firstOrCreate(['code' => 'PAGIBIG_LOAN'], ['name' => 'Pag-IBIG Loan Deduction', 'is_active' => true]);

        // PayHeader 1 (Emp 1)
        $ph1 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->empWithDeductions->id,
            'status' => 'approved',
            'total_gross_pay' => 15000.00,
            'total_deductions' => 1700.00,
            'net_pay' => 13300.00,
        ]);
        PayIn::create(['pay_id' => $ph1->id, 'income_type_id' => $basicType->id, 'rate' => 15000.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph1->id, 'deduction_type_id' => $sssType->id, 'rate' => 750.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph1->id, 'deduction_type_id' => $phType->id, 'rate' => 300.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph1->id, 'deduction_type_id' => $pagibigType->id, 'rate' => 200.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph1->id, 'deduction_type_id' => $taxType->id, 'rate' => 450.00, 'number_input' => 1]);

        GovernmentContribution::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->empWithDeductions->id,
            'payroll_period_id' => $this->period->id,
            'type' => 'sss',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'employee_share' => 750.00,
            'employer_share' => 1500.00,
        ]);
        GovernmentContribution::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->empWithDeductions->id,
            'payroll_period_id' => $this->period->id,
            'type' => 'philhealth',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'employee_share' => 300.00,
            'employer_share' => 300.00,
        ]);
        GovernmentContribution::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->empWithDeductions->id,
            'payroll_period_id' => $this->period->id,
            'type' => 'pagibig',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'employee_share' => 200.00,
            'employer_share' => 200.00,
        ]);

        // PayHeader 2 (Emp 2: Has PayOuts but NO GovernmentContribution row)
        $ph2 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->empWithoutGovtContrib->id,
            'status' => 'approved',
            'total_gross_pay' => 17500.00,
            'total_deductions' => 1250.00,
            'net_pay' => 16250.00,
        ]);
        PayIn::create(['pay_id' => $ph2->id, 'income_type_id' => $basicType->id, 'rate' => 17500.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph2->id, 'deduction_type_id' => $sssType->id, 'rate' => 600.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph2->id, 'deduction_type_id' => $phType->id, 'rate' => 250.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph2->id, 'deduction_type_id' => $pagibigType->id, 'rate' => 100.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph2->id, 'deduction_type_id' => $taxType->id, 'rate' => 300.00, 'number_input' => 1]);

        // PayHeader 3 (Emp 3: Has NO statutory PayOut contributions, but HAS loan deductions SSS_LOAN and PAGIBIG_LOAN)
        $ph3 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->empWithoutDeductions->id,
            'status' => 'approved',
            'total_gross_pay' => 20000.00,
            'total_deductions' => 2000.00,
            'net_pay' => 18000.00,
        ]);
        PayIn::create(['pay_id' => $ph3->id, 'income_type_id' => $basicType->id, 'rate' => 20000.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph3->id, 'deduction_type_id' => $sssLoanType->id, 'rate' => 1200.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph3->id, 'deduction_type_id' => $pagibigLoanType->id, 'rate' => 800.00, 'number_input' => 1]);
    }

    /**
     * Test SSS report exports employee share from PayOut and includes employee without GovernmentContribution.
     */
    public function test_sss_report_sources_ee_share_from_payout(): void
    {
        $export = new SSSReportExport($this->company->id, $this->monthStr);
        $rows = $export->collection();

        $this->assertCount(3, $rows, 'All 3 employees with approved PayHeaders must appear in SSS report.');

        $emp1Row = $rows->firstWhere('employee_number', 'EMP-ST01');
        $this->assertEquals(750.00, $emp1Row['employee_contribution']);

        // Emp 2 has PayOut of 600.00 but NO GovernmentContribution row
        $emp2Row = $rows->firstWhere('employee_number', 'EMP-ST02');
        $this->assertNotNull($emp2Row, 'Employee without GovernmentContribution row must still appear.');
        $this->assertEquals(600.00, $emp2Row['employee_contribution']);

        // Emp 3 has NO SSS PayOut
        $emp3Row = $rows->firstWhere('employee_number', 'EMP-ST03');
        $this->assertEquals(0.00, $emp3Row['employee_contribution'], 'Employee without SSS PayOut must report 0.00 employee contribution.');
    }

    /**
     * Test PhilHealth report exports employee share from PayOut and handles missing GovernmentContribution.
     */
    public function test_philhealth_report_sources_ee_share_from_payout(): void
    {
        $export = new PhilHealthReportExport($this->company->id, $this->monthStr);
        $rows = $export->collection();

        $this->assertCount(3, $rows);

        $emp1Row = $rows->firstWhere('employee_number', 'EMP-ST01');
        $this->assertEquals(300.00, $emp1Row['employee_contribution']);

        $emp2Row = $rows->firstWhere('employee_number', 'EMP-ST02');
        $this->assertNotNull($emp2Row);
        $this->assertEquals(250.00, $emp2Row['employee_contribution']);

        $emp3Row = $rows->firstWhere('employee_number', 'EMP-ST03');
        $this->assertEquals(0.00, $emp3Row['employee_contribution']);
    }

    /**
     * Test Pag-IBIG report exports employee share from PayOut and handles missing GovernmentContribution.
     */
    public function test_pagibig_report_sources_ee_share_from_payout(): void
    {
        $export = new PagIBIGReportExport($this->company->id, $this->monthStr);
        $rows = $export->collection();

        $this->assertCount(3, $rows);

        $emp1Row = $rows->firstWhere('employee_number', 'EMP-ST01');
        $this->assertEquals(200.00, $emp1Row['employee_contribution']);

        $emp2Row = $rows->firstWhere('employee_number', 'EMP-ST02');
        $this->assertNotNull($emp2Row);
        $this->assertEquals(100.00, $emp2Row['employee_contribution']);

        $emp3Row = $rows->firstWhere('employee_number', 'EMP-ST03');
        $this->assertEquals(0.00, $emp3Row['employee_contribution']);
    }

    /**
     * Test Withholding Tax report exports withholding tax strictly from PayOut.
     */
    public function test_withholding_tax_report_sources_tax_from_payout(): void
    {
        $export = new WithholdingTaxReportExport($this->company->id, $this->monthStr);
        $rows = $export->collection();

        $this->assertCount(3, $rows);

        $emp1Row = $rows->firstWhere('employee_number', 'EMP-ST01');
        $this->assertEquals(450.00, $emp1Row['withholding_tax']);

        $emp2Row = $rows->firstWhere('employee_number', 'EMP-ST02');
        $this->assertEquals(300.00, $emp2Row['withholding_tax']);

        $emp3Row = $rows->firstWhere('employee_number', 'EMP-ST03');
        $this->assertEquals(0.00, $emp3Row['withholding_tax']);
    }

    /**
     * Regression test proving SSS_LOAN and PAGIBIG_LOAN are NOT counted as statutory employee contributions or non-taxable statutory deductions.
     */
    public function test_loan_deductions_are_not_counted_as_statutory_employee_shares(): void
    {
        // SSS report: EMP-ST03 has SSS_LOAN of 1200.00 but 0.00 SSS statutory contribution
        $sssRows = (new SSSReportExport($this->company->id, $this->monthStr))->collection();
        $emp3Sss = $sssRows->firstWhere('employee_number', 'EMP-ST03');
        $this->assertEquals(0.00, $emp3Sss['employee_contribution'], 'SSS_LOAN must NOT be counted as SSS statutory employee contribution.');

        // Pag-IBIG report: EMP-ST03 has PAGIBIG_LOAN of 800.00 but 0.00 Pag-IBIG statutory contribution
        $pagibigRows = (new PagIBIGReportExport($this->company->id, $this->monthStr))->collection();
        $emp3Pagibig = $pagibigRows->firstWhere('employee_number', 'EMP-ST03');
        $this->assertEquals(0.00, $emp3Pagibig['employee_contribution'], 'PAGIBIG_LOAN must NOT be counted as Pag-IBIG statutory employee contribution.');

        // Withholding Tax report: EMP-ST03 SSS_LOAN and PAGIBIG_LOAN must NOT be counted as non-taxable statutory deductions
        $taxRows = (new WithholdingTaxReportExport($this->company->id, $this->monthStr))->collection();
        $emp3Tax = $taxRows->firstWhere('employee_number', 'EMP-ST03');
        $this->assertEquals(0.00, $emp3Tax['total_deductions'], 'Loans (SSS_LOAN, PAGIBIG_LOAN) must NOT be subtracted as non-taxable statutory deductions.');
        $this->assertEquals(20000.00, $emp3Tax['taxable_income'], 'Taxable income must remain full gross when loans are deducted.');
    }
}
