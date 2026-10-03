<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\BankAdviceExport;
use App\Exports\BankUploadExport;
use App\Models\Company;
use App\Models\CompanyBankSetting;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExcelReportExportAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected Department $departmentA;
    protected EmployeeMaster $employeeA1;
    protected EmployeeMaster $employeeA2;
    protected PayrollPeriod $periodA1;
    protected PayrollPeriod $periodA2;
    protected PayrollPeriod $periodB;
    protected User $adminUser;
    protected User $companyAdminA;
    protected User $hrUserA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenusTableSeeder::class);
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        PayHeader::unguard();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
            'company_id' => null,
            'status' => 1,
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Audit Company A',
            'company_code' => 'AUDITA',
            'email' => 'audita@test.com',
            'status' => '1',
        ]);

        $this->companyB = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Audit Company B',
            'company_code' => 'AUDITB',
            'email' => 'auditb@test.com',
            'status' => '1',
        ]);

        $this->departmentA = Department::create([
            'company_id' => $this->companyA->id,
            'name' => 'Engineering',
            'code' => 'ENG',
            'is_active' => true,
        ]);

        $this->employeeA1 = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->departmentA->id,
            'first_name' => 'Alice',
            'last_name' => 'Audit',
            'employee_code' => 'EMP-AUD-001',
            'monthly_rate' => 30000,
            'daily_rate' => 1153.85,
            'hourly_rate' => 144.23,
            'acct_num' => '1234567890',
            'status' => 'active',
        ]);

        $this->employeeA2 = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->departmentA->id,
            'first_name' => 'Bob',
            'last_name' => 'Audit',
            'employee_code' => 'EMP-AUD-002',
            'monthly_rate' => 35000,
            'daily_rate' => 1346.15,
            'hourly_rate' => 168.27,
            'acct_num' => '0987654321',
            'status' => 'active',
        ]);

        $this->periodA1 = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => '2026-01-A',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'status' => 'open',
        ]);

        $this->periodA2 = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => '2026-01-B',
            'period_start' => '2026-01-16',
            'period_end' => '2026-01-31',
            'pay_date' => '2026-02-05',
            'status' => 'open',
        ]);

        $this->periodB = PayrollPeriod::create([
            'company_id' => $this->companyB->id,
            'period_name' => '2026-01-B-COMPB',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'status' => 'open',
        ]);

        $basicIncome = IncomeType::withoutGlobalScopes()->where('code', 'BASIC')->first()
            ?? IncomeType::withoutGlobalScopes()->first();

        $phA1 = PayHeader::create([
            'payroll_period_id' => $this->periodA1->id,
            'employee_id' => $this->employeeA1->id,
            'days_worked' => 11,
            'total_gross_pay' => 12692.35,
            'total_deductions' => 1000.00,
            'net_pay' => 11692.35,
            'status' => 'approved',
        ]);

        if ($basicIncome) {
            PayIn::create(['pay_id' => $phA1->id, 'income_type_id' => $basicIncome->id, 'rate' => 12692.35, 'number_input' => 1]);
        }

        $phA1Draft = PayHeader::create([
            'payroll_period_id' => $this->periodA1->id,
            'employee_id' => $this->employeeA2->id,
            'days_worked' => 11,
            'total_gross_pay' => 14807.65,
            'total_deductions' => 1200.00,
            'net_pay' => 13607.65,
            'status' => 'draft',
        ]);

        if ($basicIncome) {
            PayIn::create(['pay_id' => $phA1Draft->id, 'income_type_id' => $basicIncome->id, 'rate' => 14807.65, 'number_input' => 1]);
        }

        $phA2 = PayHeader::create([
            'payroll_period_id' => $this->periodA2->id,
            'employee_id' => $this->employeeA1->id,
            'days_worked' => 11,
            'total_gross_pay' => 12692.35,
            'total_deductions' => 1000.00,
            'net_pay' => 11692.35,
            'status' => 'approved',
        ]);

        if ($basicIncome) {
            PayIn::create(['pay_id' => $phA2->id, 'income_type_id' => $basicIncome->id, 'rate' => 12692.35, 'number_input' => 1]);
        }

        $sssDeduction = DeductionType::where('code', 'SSS')->first()
            ?? DeductionType::where('name', 'like', '%SSS%')->first();

        if ($sssDeduction) {
            PayOut::create(['pay_id' => $phA1->id, 'deduction_type_id' => $sssDeduction->id, 'rate' => 500.00, 'number_input' => 1]);
            PayOut::create(['pay_id' => $phA2->id, 'deduction_type_id' => $sssDeduction->id, 'rate' => 500.00, 'number_input' => 1]);
        }

        CompanyBankSetting::create([
            'company_id' => $this->companyA->id,
            'bank_name' => 'BDO Unibank',
            'branch_name' => 'Makati Main',
            'manager_name' => 'John Doe',
            'account_number' => '9988776655',
            'corporate_signee_1' => 'Jane Signee',
            'corporate_signee_1_title' => 'CFO',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->companyAdminA = User::factory()->create([
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => $this->companyA->id,
            'status' => 1,
        ]);

        $this->hrUserA = User::factory()->create([
            'role' => 'hr',
            'role_id' => Role::HR,
            'company_id' => $this->companyA->id,
            'status' => 1,
        ]);
    }

    public function test_permission_matrix_hr_granted_and_revoked_access(): void
    {
        $reportsMenu = Menu::where('module', 'reports')->firstOrFail();

        // 1. Grant HR access in MenuRoleAccess
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $reportsMenu->id],
            ['is_enabled' => 1]
        );

        $res = $this->actingAs($this->hrUserA)->post(route('company.reports.payroll-summary-1'), [
            'payroll_period_id' => $this->periodA1->id,
            'export_format' => 'xlsx',
        ]);
        $res->assertStatus(200);

        // 2. Revoke HR access in MenuRoleAccess
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $reportsMenu->id],
            ['is_enabled' => 0]
        );

        $resRevoked = $this->actingAs($this->hrUserA)->post(route('company.reports.payroll-summary-1'), [
            'payroll_period_id' => $this->periodA1->id,
            'export_format' => 'xlsx',
        ]);
        $resRevoked->assertStatus(403);
    }

    public function test_draft_pay_headers_excluded_from_bank_advice_and_bank_upload(): void
    {
        $res = $this->actingAs($this->companyAdminA)->post(route('company.reports.bank-report'), [
            'from_period_id' => $this->periodA1->id,
            'to_period_id' => $this->periodA1->id,
            'export_format' => 'xlsx',
        ]);
        $res->assertStatus(200);
        $this->assertStringContainsString('.xlsx', $res->headers->get('content-disposition'));

        $directRes = $this->actingAs($this->companyAdminA)->get(route('company.reports.export-bank-advice', [
            'payrollPeriodId' => $this->periodA1->id,
        ]));
        $directRes->assertStatus(200);
        $this->assertStringContainsString('.xlsx', $directRes->headers->get('content-disposition'));

        $service = app(\App\Services\Reports\BankAdviceReportService::class);
        $data = $service->build($this->periodA1, $this->periodA1, $this->companyA, $this->companyA->id);
        $this->assertCount(1, $data['reportData']);
        $this->assertEquals('EMP-AUD-001', $data['reportData'][0]['employee_code']);
        $this->assertEquals(11692.35, $data['totalNetPay']);

        $bankUploadExport = new BankUploadExport($this->periodA1->id, $this->periodA1->id, $this->companyA->id);
        $uploadCollection = $bankUploadExport->collection();
        $this->assertCount(1, $uploadCollection);
        $this->assertEquals('EMP-AUD-001', $uploadCollection->first()['employee']->employee_code);
    }

    public function test_cross_company_period_rejection(): void
    {
        $res1 = $this->actingAs($this->companyAdminA)->get(route('company.reports.export-master-sheet', [
            'payrollPeriodId' => $this->periodB->id,
        ]));
        $res1->assertStatus(404);

        $res2 = $this->actingAs($this->companyAdminA)->get(route('company.reports.export-bank-advice', [
            'payrollPeriodId' => $this->periodB->id,
        ]));
        $res2->assertStatus(404);
    }

    public function test_payroll_summary_1_and_2_excel_exports(): void
    {
        $res1 = $this->actingAs($this->companyAdminA)->post(route('company.reports.payroll-summary-1'), [
            'payroll_period_id' => $this->periodA1->id,
            'department_id' => 'all',
            'export_format' => 'xlsx',
        ]);
        $res1->assertStatus(200);
        $this->assertStringContainsString('Payroll_Summary_1_', $res1->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', $res1->headers->get('content-disposition'));

        $res2 = $this->actingAs($this->companyAdminA)->post(route('company.reports.payroll-summary-2'), [
            'payroll_period_id' => $this->periodA1->id,
            'department_id' => 'all',
            'export_format' => 'xlsx',
        ]);
        $res2->assertStatus(200);
        $this->assertStringContainsString('Payroll_Summary_2_', $res2->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', $res2->headers->get('content-disposition'));
    }

    public function test_statutory_excel_exports_sss_philhealth_pagibig_tax(): void
    {
        $m = '2026-01';

        $sss = $this->actingAs($this->companyAdminA)->post(route('company.reports.sss'), ['month' => $m]);
        $sss->assertStatus(200);
        $this->assertStringContainsString('SSS_Report_2026_01.xlsx', $sss->headers->get('content-disposition'));

        $ph = $this->actingAs($this->companyAdminA)->post(route('company.reports.philhealth'), ['month' => $m]);
        $ph->assertStatus(200);
        $this->assertStringContainsString('PhilHealth_Report_2026_01.xlsx', $ph->headers->get('content-disposition'));

        $pagibig = $this->actingAs($this->companyAdminA)->post(route('company.reports.pagibig'), ['month' => $m]);
        $pagibig->assertStatus(200);
        $this->assertStringContainsString('PagIBIG_Report_2026_01.xlsx', $pagibig->headers->get('content-disposition'));

        $tax = $this->actingAs($this->companyAdminA)->post(route('company.reports.withholding-tax'), ['month' => $m]);
        $tax->assertStatus(200);
        $this->assertStringContainsString('Withholding_Tax_Report_2026_01.xlsx', $tax->headers->get('content-disposition'));
    }

    public function test_thirteenth_month_computation_and_history_excel_exports(): void
    {
        $res = $this->actingAs($this->companyAdminA)->post(route('company.reports.13th-month-computation'), [
            'from_period_id' => $this->periodA1->id,
            'to_period_id' => $this->periodA2->id,
            'export_format' => 'xlsx',
        ]);
        $res->assertStatus(200);
        $this->assertStringContainsString('.xlsx', $res->headers->get('content-disposition'));

        $legacy = $this->actingAs($this->companyAdminA)->get(route('company.reports.thirteenth-month-history.excel', ['year' => 2026]));
        $legacy->assertStatus(200);
        $this->assertStringContainsString('.xlsx', $legacy->headers->get('content-disposition'));
    }

    public function test_history_matrices_and_ledgers_excel_exports(): void
    {
        $routes = [
            'income-payroll-history' => ['from_period_id' => $this->periodA1->id, 'to_period_id' => $this->periodA2->id],
            'deduction-payroll-history' => ['from_period_id' => $this->periodA1->id, 'to_period_id' => $this->periodA2->id],
            'income-history' => ['payroll_period_id' => $this->periodA1->id],
            'deduction-history' => ['payroll_period_id' => $this->periodA1->id],
            'deduction-ledger' => ['from_period_id' => $this->periodA1->id, 'to_period_id' => $this->periodA2->id, 'deduction_type_id' => 'sss_contribution'],
            'single-deduction-matrix' => ['from_period_id' => $this->periodA1->id, 'to_period_id' => $this->periodA2->id, 'deduction_type_id' => 'sss_contribution'],
        ];

        foreach ($routes as $routeSuffix => $payload) {
            $payload['export_format'] = 'xlsx';
            $res = $this->actingAs($this->companyAdminA)->post(route('company.reports.' . $routeSuffix), $payload);
            $res->assertStatus(200);
            $this->assertStringContainsString('.xlsx', $res->headers->get('content-disposition'));
        }
    }

    public function test_exporter_instance_row_index_resets_cleanly(): void
    {
        $mockRow = [
            'employee' => $this->employeeA1,
            'last_name' => 'Audit',
            'first_name' => 'Alice',
            'middle_name' => '',
            'employee_code' => 'EMP-AUD-001',
            'acct_num' => '1234567890',
            'atm_num' => 'ATM123',
            'net_pay' => 11692.35,
        ];

        $export1 = new BankAdviceExport([$mockRow]);
        $this->assertEquals(1, $export1->map($mockRow)[0]);

        $export2 = new BankAdviceExport([$mockRow]);
        $this->assertEquals(1, $export2->map($mockRow)[0]);
    }

    public function test_hr_user_with_reports_granted_cannot_save_bank_settings_via_bank_report(): void
    {
        $reportsMenu = Menu::where('module', 'reports')->firstOrFail();
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $reportsMenu->id],
            ['is_enabled' => 1]
        );

        $initialCount = CompanyBankSetting::count();

        $res = $this->actingAs($this->hrUserA)->post(route('company.reports.bank-report'), [
            'from_period_id' => $this->periodA1->id,
            'to_period_id' => $this->periodA1->id,
            'save_bank_settings' => 1,
            'bank_name' => 'HR Injected Bank Name',
            'company_acct' => '0000000000',
            'export_format' => 'xlsx',
        ]);

        $res->assertStatus(200);
        $this->assertFalse(CompanyBankSetting::where('bank_name', 'HR Injected Bank Name')->exists());
        $this->assertEquals($initialCount, CompanyBankSetting::count());
    }

    public function test_cross_company_bank_setting_id_is_safely_ignored(): void
    {
        $foreignSetting = CompanyBankSetting::create([
            'company_id' => $this->companyB->id,
            'bank_name' => 'Foreign Company B Secret Bank',
            'account_number' => '111222333',
            'is_active' => true,
        ]);

        $service = app(\App\Services\Reports\BankAdviceReportService::class);
        $data = $service->build(
            $this->periodA1,
            $this->periodA1,
            $this->companyA,
            $this->companyA->id,
            null,
            ['bank_setting_id' => $foreignSetting->id]
        );

        // Foreign bank setting should be ignored; default company A bank setting used
        $this->assertNotEquals('Foreign Company B Secret Bank', $data['letterDetails']['bank_name']);
        $this->assertEquals('BDO Unibank', $data['letterDetails']['bank_name']);
    }

    public function test_cross_company_period_rejection_on_bank_report_and_bank_upload(): void
    {
        $res1 = $this->actingAs($this->companyAdminA)->post(route('company.reports.bank-report'), [
            'from_period_id' => $this->periodB->id,
            'to_period_id' => $this->periodB->id,
            'export_format' => 'xlsx',
        ]);
        $res1->assertStatus(404);

        $res2 = $this->actingAs($this->companyAdminA)->post(route('company.reports.export-payroll-bank'), [
            'from_period_id' => $this->periodB->id,
            'to_period_id' => $this->periodB->id,
            'export_format' => 'xlsx',
        ]);
        $res2->assertStatus(404);
    }
}
