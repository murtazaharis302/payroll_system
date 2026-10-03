<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\PagIBIGReportExport;
use App\Exports\PhilHealthReportExport;
use App\Exports\SSSReportExport;
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
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportDeductionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected PayrollPeriod $periodApproved;
    protected PayrollPeriod $periodDraft;
    protected PayrollPeriod $periodPending;
    protected PayrollPeriod $periodRejected;
    protected PayrollPeriod $periodPosted;
    protected DeductionType $sssType;
    protected DeductionType $phType;
    protected DeductionType $pagibigType;
    protected DeductionType $taxType;
    protected DeductionType $valeType;
    protected DeductionType $customType;
    protected IncomeType $basicType;
    protected string $monthStr = '2026-09';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_reconcile@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::SUPER_ADMIN,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Reconciliation Test Corp',
            'email' => 'reconcile_corp@test.com',
            'status' => 1,
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'code' => 'OPS',
            'name' => 'Operations',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-REC-01',
            'first_name' => 'John',
            'last_name' => 'Reconcile',
            'status' => 'active',
            'monthly_rate' => 30000.00,
        ]);

        $this->basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        $this->sssType = DeductionType::where('code', 'SSS')->firstOrFail();
        $this->phType = DeductionType::where('code', 'PHILHEALTH')->firstOrFail();
        $this->pagibigType = DeductionType::where('code', 'PAGIBIG')->firstOrFail();
        $this->taxType = DeductionType::where('code', 'TAX')->firstOrFail();
        $this->valeType = DeductionType::where('code', 'VALE')->firstOrFail();

        $this->customType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'UNIFORM',
            'name' => 'Uniform Deduction',
            'type' => 'custom',
            'is_active' => true,
        ]);

        $standardDeductions = [
            $this->sssType->id => 500.00,
            $this->phType->id => 300.00,
            $this->pagibigType->id => 200.00,
            $this->taxType->id => 400.00,
            $this->valeType->id => 150.00,
            $this->customType->id => 100.00,
        ];

        // Period 1 (Approved): 2026-09-01 to 2026-09-07
        $this->periodApproved = $this->createPeriod('Sep Cutoff 1 (Approved)', '2026-09-01', '2026-09-07', 'completed');
        $this->createHeaderWithDeductions($this->periodApproved, 'approved', 10000.00, $standardDeductions);

        GovernmentContribution::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'payroll_period_id' => $this->periodApproved->id,
            'type' => 'sss',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-07',
            'employee_share' => 500.00,
            'employer_share' => 1000.00,
        ]);

        // Period 2 (Draft): 2026-09-08 to 2026-09-14
        $this->periodDraft = $this->createPeriod('Sep Cutoff 2 (Draft)', '2026-09-08', '2026-09-14', 'open');
        $this->createHeaderWithDeductions($this->periodDraft, 'draft', 10000.00, $standardDeductions);

        // Period 3 (Pending): 2026-09-15 to 2026-09-21
        $this->periodPending = $this->createPeriod('Sep Cutoff 3 (Pending)', '2026-09-15', '2026-09-21', 'open');
        $this->createHeaderWithDeductions($this->periodPending, 'pending_approval', 10000.00, $standardDeductions);

        // Period 4 (Rejected): 2026-09-22 to 2026-09-24
        $this->periodRejected = $this->createPeriod('Sep Cutoff 4 (Rejected)', '2026-09-22', '2026-09-24', 'open');
        $this->createHeaderWithDeductions($this->periodRejected, 'rejected', 5000.00, [$this->sssType->id => 250.00]);

        // Period 5 (Posted): 2026-09-25 to 2026-09-30
        $this->periodPosted = $this->createPeriod('Sep Cutoff 5 (Posted)', '2026-09-25', '2026-09-30', 'completed');
        $this->createHeaderWithDeductions($this->periodPosted, 'posted', 10000.00, $standardDeductions);
    }

    protected function createPeriod(string $name, string $start, string $end, string $status): PayrollPeriod
    {
        return PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => $name,
            'period_start' => $start,
            'period_end' => $end,
            'pay_date' => $end,
            'status' => $status,
        ]);
    }

    protected function createHeaderWithDeductions(PayrollPeriod $period, string $status, float $gross, array $deductions): PayHeader
    {
        $totalDed = (float) array_sum($deductions);
        $ph = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'status' => $status,
            'total_gross_pay' => $gross,
            'total_deductions' => $totalDed,
            'net_pay' => $gross - $totalDed,
        ]);

        PayIn::create(['pay_id' => $ph->id, 'income_type_id' => $this->basicType->id, 'rate' => $gross, 'number_input' => 1]);

        foreach ($deductions as $typeId => $rate) {
            PayOut::create(['pay_id' => $ph->id, 'deduction_type_id' => $typeId, 'rate' => $rate, 'number_input' => 1]);
        }

        return $ph;
    }

    protected function getReportDataViaPdf(string $url, array $params): array
    {
        $capturedData = [];
        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use (&$capturedData, $pdfMock) {
            $capturedData = $data;
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->actingAs($this->adminUser)->post($url, $params);
        $response->assertOk();

        return $capturedData;
    }

    public function test_sss_report_and_deduction_ledger_reconcile_and_exclude_drafts(): void
    {
        $sssExport = new SSSReportExport($this->company->id, $this->monthStr);
        $sssRows = $sssExport->collection();
        $empSss = $sssRows->firstWhere('employee_number', 'EMP-REC-01');

        $this->assertNotNull($empSss);
        $this->assertEquals(1000.00, $empSss['employee_contribution'], 'Statutory SSS must only sum approved & posted EE shares (500 + 500 = 1000).');

        $ledgerData = $this->getReportDataViaPdf('/company/reports/deduction-ledger', [
            'from_period_id'    => $this->periodApproved->id,
            'to_period_id'      => $this->periodPosted->id,
            'deduction_type_id' => 'sss_contribution',
        ]);
        $this->assertEquals(1000.00, $ledgerData['grandTotalLedger']);
        $this->assertCount(2, $ledgerData['reportData'][0]['rows']);
    }

    public function test_philhealth_report_and_deduction_ledger_reconcile(): void
    {
        $phExport = new PhilHealthReportExport($this->company->id, $this->monthStr);
        $empPh = $phExport->collection()->firstWhere('employee_number', 'EMP-REC-01');

        $this->assertEquals(600.00, $empPh['employee_contribution']);

        $ledgerData = $this->getReportDataViaPdf('/company/reports/deduction-ledger', [
            'from_period_id'    => $this->periodApproved->id,
            'to_period_id'      => $this->periodPosted->id,
            'deduction_type_id' => 'philhealth_contribution',
        ]);
        $this->assertEquals(600.00, $ledgerData['grandTotalLedger']);
    }

    public function test_pagibig_report_and_deduction_ledger_reconcile(): void
    {
        $pagibigExport = new PagIBIGReportExport($this->company->id, $this->monthStr);
        $empPagibig = $pagibigExport->collection()->firstWhere('employee_number', 'EMP-REC-01');

        $this->assertEquals(400.00, $empPagibig['employee_contribution']);

        $ledgerData = $this->getReportDataViaPdf('/company/reports/deduction-ledger', [
            'from_period_id'    => $this->periodApproved->id,
            'to_period_id'      => $this->periodPosted->id,
            'deduction_type_id' => 'pagibig_contribution',
        ]);
        $this->assertEquals(400.00, $ledgerData['grandTotalLedger']);
    }

    public function test_withholding_tax_report_and_deduction_ledger_reconcile(): void
    {
        $taxExport = new WithholdingTaxReportExport($this->company->id, $this->monthStr);
        $empTax = $taxExport->collection()->firstWhere('employee_number', 'EMP-REC-01');

        $this->assertEquals(800.00, $empTax['withholding_tax']);

        $ledgerData = $this->getReportDataViaPdf('/company/reports/deduction-ledger', [
            'from_period_id'    => $this->periodApproved->id,
            'to_period_id'      => $this->periodPosted->id,
            'deduction_type_id' => 'withholding_tax',
        ]);
        $this->assertEquals(800.00, $ledgerData['grandTotalLedger']);
    }

    public function test_single_deduction_matrix_reconciles_with_deduction_ledger(): void
    {
        $matrixData = $this->getReportDataViaPdf('/company/reports/single-deduction-matrix', [
            'from_period_id'    => $this->periodApproved->id,
            'to_period_id'      => $this->periodPosted->id,
            'deduction_type_id' => 'sss_contribution',
        ]);
        $this->assertEquals(1000.00, $matrixData['grandTotalMatrix']);

        $row = $matrixData['reportData'][0];
        $this->assertEquals(500.00, $row['amounts'][$this->periodApproved->id]);
        $this->assertEquals(0.00, $row['amounts'][$this->periodDraft->id]);
        $this->assertEquals(0.00, $row['amounts'][$this->periodPending->id]);
        $this->assertEquals(0.00, $row['amounts'][$this->periodRejected->id]);
        $this->assertEquals(500.00, $row['amounts'][$this->periodPosted->id]);
    }

    public function test_custom_numeric_deduction_type_reconciles_in_ledger_and_matrix(): void
    {
        $ledgerData = $this->getReportDataViaPdf('/company/reports/deduction-ledger', [
            'from_period_id'    => $this->periodApproved->id,
            'to_period_id'      => $this->periodPosted->id,
            'deduction_type_id' => $this->customType->id,
        ]);
        $this->assertEquals(200.00, $ledgerData['grandTotalLedger']);

        $matrixData = $this->getReportDataViaPdf('/company/reports/single-deduction-matrix', [
            'from_period_id'    => $this->periodApproved->id,
            'to_period_id'      => $this->periodPosted->id,
            'deduction_type_id' => $this->customType->id,
        ]);
        $this->assertEquals(200.00, $matrixData['grandTotalMatrix']);
    }

    public function test_deduction_history_and_payroll_history_exclude_draft_headers(): void
    {
        $historyData = $this->getReportDataViaPdf('/company/reports/deduction-history', [
            'payroll_period_id' => $this->periodApproved->id,
            'selected_deduction_types' => ['sss_contribution', 'philhealth_contribution'],
        ]);
        $this->assertEquals(500.00, $historyData['columnTotals']['sss_contribution']);
        $this->assertEquals(300.00, $historyData['columnTotals']['philhealth_contribution']);

        $payrollHistoryData = $this->getReportDataViaPdf('/company/reports/deduction-payroll-history', [
            'from_period_id' => $this->periodApproved->id,
            'to_period_id'   => $this->periodPosted->id,
            'selected_deduction_types' => ['sss_contribution', 'philhealth_contribution'],
        ]);
        $this->assertEquals(1000.00, $payrollHistoryData['grandTotals']['sss_contribution']);
        $this->assertEquals(600.00, $payrollHistoryData['grandTotals']['philhealth_contribution']);
    }

    public function test_fallback_behavior_only_triggers_for_headers_with_no_payout_rows(): void
    {
        $periodTest = $this->createPeriod('Fallback Test Cutoff', '2026-09-01', '2026-09-07', 'completed');

        // Emp A: Has PayOut rows (but SSS is omitted or 0)
        $empA = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-FALLBACK-A',
            'first_name' => 'Exempt',
            'last_name' => 'Employee',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);
        $phA = PayHeader::create([
            'payroll_period_id' => $periodTest->id,
            'employee_id' => $empA->id,
            'status' => 'approved',
            'total_gross_pay' => 10000.00,
            'total_deductions' => 0.00,
            'net_pay' => 10000.00,
        ]);
        PayIn::create(['pay_id' => $phA->id, 'income_type_id' => $this->basicType->id, 'rate' => 10000.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $phA->id, 'deduction_type_id' => $this->valeType->id, 'rate' => 0.00, 'number_input' => 1]);

        $resolvedA = \App\Support\Payroll\DeductionReportResolver::resolveAmountForHeader($phA, 'sss_contribution');
        $this->assertEquals(0.00, $resolvedA, 'Header with PayOut rows must strictly resolve to 0.00 and not fallback.');

        // Emp B: Legacy PayHeader with NO PayOut rows at all
        $empB = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-FALLBACK-B',
            'first_name' => 'Legacy',
            'last_name' => 'Employee',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);
        $phB = PayHeader::create([
            'payroll_period_id' => $periodTest->id,
            'employee_id' => $empB->id,
            'status' => 'approved',
            'total_gross_pay' => 10000.00,
            'total_deductions' => 250.00,
            'net_pay' => 9750.00,
        ]);

        $resolvedB = \App\Support\Payroll\DeductionReportResolver::resolveAmountForHeader($phB, 'sss_contribution');
        $this->assertEquals(0.00, $resolvedB);
    }
}
