<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\IncomeType;
use App\Models\DeductionType;
use App\Exports\PayrollMasterSheetExport;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\DeductionTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DynamicPayrollSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected EmployeeMaster $emp1;
    protected EmployeeMaster $emp2;
    protected Department $dept1;
    protected Department $dept2;
    protected PayrollPeriod $period;
    protected PayHeader $payHeader1;
    protected PayHeader $payHeader2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        // Create Super Admin & Company
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::SUPER_ADMIN,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Test Company',
            'email' => 'company@test.com',
            'status' => 1,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'companyadmin@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => $this->company->id,
            'status' => 1,
        ]);

        // Create Departments
        $this->dept1 = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Engineering',
            'code' => 'ENG',
            'is_active' => true,
        ]);

        $this->dept2 = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Marketing',
            'code' => 'MKT',
            'is_active' => true,
        ]);

        // Create Employees
        $this->emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-001',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'dept_id' => $this->dept1->id,
            'status' => 'active',
            'monthly_rate' => 30000,
            'date_hired' => '2025-01-01',
        ]);

        $this->emp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-002',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'dept_id' => $this->dept2->id,
            'status' => 'active',
            'monthly_rate' => 25000,
            'date_hired' => '2025-01-01',
        ]);

        // Create Period
        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'July 1-15 Cutoff',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
            'status' => 'completed',
        ]);

        // Create PayHeaders
        $this->payHeader1 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->emp1->id,
            'status' => 'approved',
            'total_gross_pay' => 17000,
            'total_deductions' => 2000,
            'net_pay' => 15000,
        ]);

        $this->payHeader2 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->emp2->id,
            'status' => 'approved',
            'total_gross_pay' => 14000,
            'total_deductions' => 1500,
            'net_pay' => 12500,
        ]);

        // Setup Incomes
        $basicType = IncomeType::where('code', 'BASIC')->first();
        $otType = IncomeType::where('code', 'OVERTIME')->first();
        $allowanceType = IncomeType::where('code', 'ALLOWANCE')->first();
        $bonusType = IncomeType::where('code', 'BONUS')->first();

        // Alice (Emp 1) Incomes: Basic, OT, Allowance, Bonus
        PayIn::create(['pay_id' => $this->payHeader1->id, 'income_type_id' => $basicType->id, 'rate' => 15000, 'number_input' => 1]);
        PayIn::create(['pay_id' => $this->payHeader1->id, 'income_type_id' => $otType->id, 'rate' => 1000, 'number_input' => 1]);
        PayIn::create(['pay_id' => $this->payHeader1->id, 'income_type_id' => $allowanceType->id, 'rate' => 500, 'number_input' => 1]);
        PayIn::create(['pay_id' => $this->payHeader1->id, 'income_type_id' => $bonusType->id, 'rate' => 500, 'number_input' => 1]);

        // Bob (Emp 2) Incomes: Basic, Allowance
        PayIn::create(['pay_id' => $this->payHeader2->id, 'income_type_id' => $basicType->id, 'rate' => 13000, 'number_input' => 1]);
        PayIn::create(['pay_id' => $this->payHeader2->id, 'income_type_id' => $allowanceType->id, 'rate' => 1000, 'number_input' => 1]);

        // Setup Deductions (Add multiple active deductions to test dynamic overflow aggregation)
        $sssType = DeductionType::where('code', 'SSS')->first();
        $philhealthType = DeductionType::where('code', 'PHILHEALTH')->first();
        $pagibigType = DeductionType::where('code', 'PAGIBIG')->first();
        $taxType = DeductionType::where('code', 'TAX')->first();
        $sssLoanType = DeductionType::where('code', 'SSS_LOAN')->first();
        $pagibigLoanType = DeductionType::where('code', 'PAGIBIG_LOAN')->first();

        PayOut::create(['pay_id' => $this->payHeader1->id, 'deduction_type_id' => $sssType->id, 'rate' => 500, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader1->id, 'deduction_type_id' => $philhealthType->id, 'rate' => 300, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader1->id, 'deduction_type_id' => $pagibigType->id, 'rate' => 200, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader1->id, 'deduction_type_id' => $taxType->id, 'rate' => 500, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader1->id, 'deduction_type_id' => $sssLoanType->id, 'rate' => 500, 'number_input' => 1]);

        PayOut::create(['pay_id' => $this->payHeader2->id, 'deduction_type_id' => $sssType->id, 'rate' => 400, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader2->id, 'deduction_type_id' => $philhealthType->id, 'rate' => 200, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader2->id, 'deduction_type_id' => $pagibigType->id, 'rate' => 100, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader2->id, 'deduction_type_id' => $taxType->id, 'rate' => 300, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader2->id, 'deduction_type_id' => $pagibigLoanType->id, 'rate' => 500, 'number_input' => 1]);
    }

    /**
     * Test generating Payroll Summary 1 PDF with Legal paper size.
     */
    public function test_payroll_summary_1_pdf_generation_legal(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.payroll-summary-1', $view);
            $displayedDeductions = $data['displayedDeductions'] ?? collect();
            $hiddenDeductions = $data['hiddenDeductions'] ?? collect();

            $this->assertCount(6, $displayedDeductions);
            $this->assertCount(0, $hiddenDeductions);

            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/payroll-summary-1', [
            'payroll_period_id' => $this->period->id,
            'paper_size' => 'legal',
        ]);

        $response->assertOk();
    }

    /**
     * Test generating Payroll Summary 1 PDF with Letter paper size.
     */
    public function test_payroll_summary_1_pdf_generation_letter(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.payroll-summary-1', $view);
            $displayedDeductions = $data['displayedDeductions'] ?? collect();
            $hiddenDeductions = $data['hiddenDeductions'] ?? collect();

            $this->assertCount(1, $displayedDeductions);
            $this->assertCount(5, $hiddenDeductions);

            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('letter', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/payroll-summary-1', [
            'payroll_period_id' => $this->period->id,
            'paper_size' => 'letter',
        ]);

        $response->assertOk();
    }

    /**
     * Test generating Payroll Summary 2 PDF with Letter paper size and department filter.
     */
    public function test_payroll_summary_2_pdf_generation_department_filtering(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.payroll-summary-2', $view);
            $payrolls = $data['payrolls'];

            // Since we filtered by Engineering (dept1), only Alice Smith should be returned
            $this->assertCount(1, $payrolls);
            $this->assertEquals('Alice Smith', preg_replace('/\s+/', ' ', (string) $payrolls->first()->employee->full_name));

            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/payroll-summary-2', [
            'payroll_period_id' => $this->period->id,
            'department_id' => $this->dept1->id,
            'paper_size' => 'letter',
        ]);

        $response->assertOk();
    }

    /**
     * Test default filtering when no department is specified.
     */
    public function test_payroll_summary_defaults_to_all_departments(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $payrolls = $data['payrolls'];

            // Without department filter, both Alice and Bob are returned
            $this->assertCount(2, $payrolls);

            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/payroll-summary-2', [
            'payroll_period_id' => $this->period->id,
        ]);

        $response->assertOk();
    }

    /**
     * Test Excel Master Sheet Export class dynamic columns and mapping.
     */
    public function test_payroll_master_sheet_export_columns_and_filtering(): void
    {
        $this->actingAs($this->companyAdmin);

        // 1. Test without department filter (ALL employees)
        $exportAll = new PayrollMasterSheetExport($this->period->id, $this->company->id);
        $collectionAll = $exportAll->collection();

        $this->assertCount(2, $collectionAll);

        $headings = $exportAll->headings();
        // Base (8) + 2 extra incomes x 2 (4) + Total Gross (1) + 6 deductions (6) + Total Ded & Net (2) = 21 headings
        $this->assertCount(21, $headings);
        $this->assertStringContainsString('Qty', $headings[8]);
        $this->assertStringContainsString('Amount', $headings[9]);

        // Test mapping for Alice Smith (emp1)
        $row1 = $collectionAll->firstWhere('employee.id', $this->emp1->id);
        $mapped1 = $exportAll->map($row1);

        // Basic Amount = 15000.00
        $this->assertEquals('15000.00', $mapped1[5]);
        // OT Amount = 1000.00
        $this->assertEquals('1000.00', $mapped1[7]);

        // 2. Test with department filter
        $exportDept = new PayrollMasterSheetExport($this->period->id, $this->company->id, $this->dept2->id);
        $collectionDept = $exportDept->collection();

        $this->assertCount(1, $collectionDept);
        $this->assertEquals('Bob Jones', preg_replace('/\s+/', ' ', (string) $collectionDept->first()['employee']->full_name));
    }

    /**
     * Test payroll summary 1 with department_id='all' parameter
     */
    public function test_payroll_summary_1_department_id_all(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $payrolls = $data['payrolls'];
            $this->assertCount(2, $payrolls);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/payroll-summary-1', [
            'payroll_period_id' => $this->period->id,
            'department_id' => 'all',
        ]);

        $response->assertOk();
    }

    /**
     * Test payroll summary 1 Excel and CSV exports
     */
    public function test_payroll_summary_1_export_xlsx_and_csv(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/payroll-summary-1', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Payroll_Summary_1_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/payroll-summary-1', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Payroll_Summary_1_July_1-15_Cutoff.csv');
    }

    /**
     * Test payroll summary 2 Excel and CSV exports
     */
    public function test_payroll_summary_2_export_xlsx_and_csv(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/payroll-summary-2', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Payroll_Summary_2_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/payroll-summary-2', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Payroll_Summary_2_July_1-15_Cutoff.csv');
    }

    /**
     * Test NumberToWordsHelper Pesos conversion logic.
     */
    public function test_number_to_words_helper_pesos_conversion(): void
    {
        $this->assertEquals(
            'PESOS: FIFTEEN THOUSAND FOUR HUNDRED FIFTY PESOS AND 50/100 (P 15,450.50)',
            \App\Services\NumberToWordsHelper::formatPesos(15450.50)
        );

        $this->assertEquals(
            'PESOS: SEVENTEEN THOUSAND PESOS ONLY (P 17,000.00)',
            \App\Services\NumberToWordsHelper::formatPesos(17000.00)
        );
    }

    /**
     * Test Cash Voucher PDF generation with pre-generation modal parameters.
     */
    public function test_cash_voucher_pdf_generation_with_modal_filters(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.voucher', $view);
            $payrolls = $data['payrolls'];
            $this->assertCount(2, $payrolls);
            
            // Check that first employee (Alice) has gross applicable earnings calculated without deductions
            $first = $payrolls->first();
            $this->assertGreaterThan(0, $first->voucher_total_amount);
            $this->assertStringContainsString('PESOS:', $first->voucher_amount_words);
            
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('letter', 'portrait')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/voucher', [
            'payroll_period_id' => $this->period->id,
            'department_id' => 'all',
            'voucher_type' => 'Salaries & Wages',
            'voucher_date' => '2026-07-20',
            'description' => 'To payment for salaries and wages',
            'display_breakdown' => '1',
            'include_zero' => '0',
        ]);

        $response->assertOk();
    }

    /**
     * Test Income Payroll History multi-period matrix PDF generation.
     */
    public function test_income_payroll_history_multi_period_matrix_generation(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.income-payroll-history', $view);
            $this->assertCount(2, $data['reportData']);
            $this->assertEquals('rate_and_number', $data['displayOption']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/income-payroll-history', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'department_id' => 'all',
            'display_mode' => 'rate_and_number',
            'include_zero_amount' => '1',
            'export_format' => 'pdf',
            'selected_income_types' => ['basic_pay', 'overtime_pay'],
        ]);

        $response->assertOk();
    }

    /**
     * Test Income Payroll History Excel and CSV exports.
     */
    public function test_income_payroll_history_excel_and_csv_exports(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/income-payroll-history', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Income_Payroll_History_July_1-15_Cutoff_to_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/income-payroll-history', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Income_Payroll_History_July_1-15_Cutoff_to_July_1-15_Cutoff.csv');
    }

    /**
     * Test Deduction Payroll History multi-period matrix PDF generation.
     */
    public function test_deduction_payroll_history_multi_period_matrix_generation(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.deduction-payroll-history', $view);
            $this->assertCount(2, $data['reportData']);
            $this->assertEquals('rate_and_number', $data['displayOption']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/deduction-payroll-history', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'department_id' => 'all',
            'display_mode' => 'rate_and_number',
            'include_zero_amount' => '1',
            'export_format' => 'pdf',
            'selected_deduction_types' => ['sss_contribution', 'philhealth_contribution'],
        ]);

        $response->assertOk();
    }

    /**
     * Test Deduction Payroll History Excel and CSV exports.
     */
    public function test_deduction_payroll_history_excel_and_csv_exports(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/deduction-payroll-history', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Deduction_Payroll_History_July_1-15_Cutoff_to_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/deduction-payroll-history', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Deduction_Payroll_History_July_1-15_Cutoff_to_July_1-15_Cutoff.csv');
    }

    /**
     * Test 13th Month Pay Computation PDF generation with period range and department filter.
     */
    public function test_thirteenth_month_computation_matrix_generation(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.13th-month-computation', $view);
            $this->assertCount(2, $data['reportData']);
            $this->assertGreaterThan(0, $data['grandTotal']['thirteenth_month']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/13th-month-computation', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'department_id' => 'all',
            'export_format' => 'pdf',
        ]);

        $response->assertOk();
    }

    /**
     * Test 13th Month Pay Excel and CSV exports.
     */
    public function test_thirteenth_month_excel_and_csv_exports(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/13th-month-computation', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('13th_Month_Pay_July_1-15_Cutoff_to_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/13th-month-computation', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('13th_Month_Pay_July_1-15_Cutoff_to_July_1-15_Cutoff.csv');
    }

    /**
     * Test Bank Advice Report PDF generation with dynamic bank settings and Pesos number-to-words.
     */
    public function test_bank_advice_report_pdf_generation(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.bank-report', $view);
            $this->assertCount(2, $data['reportData']);
            $this->assertStringContainsString('PESOS:', $data['letterDetails']['amount_in_words']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('letter', 'portrait')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/bank-report', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'department_id' => 'all',
            'bank_name' => 'BDO Unibank, Inc.',
            'company_acct' => '123456789012',
            'signatory_name' => 'John Doe',
            'export_format' => 'pdf',
        ]);

        $response->assertOk();
    }

    /**
     * Test Bank Advice Excel and CSV exports.
     */
    public function test_bank_advice_excel_and_csv_exports(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/bank-report', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Bank_Advice_Partner_Bank_July_1-15_Cutoff_to_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/bank-report', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Bank_Advice_Partner_Bank_July_1-15_Cutoff_to_July_1-15_Cutoff.csv');
    }

    /**
     * Test BankUploadExport 5-column structure alignment and multi-period aggregation.
     */
    public function test_bank_upload_export_multi_period_alignment(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $response = $this->post('/company/reports/export-payroll-bank', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);

        $response->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Bank_Upload_Payroll_July_1-15_Cutoff_to_July_1-15_Cutoff.xlsx');

        // Test export class structure headings and map
        $export = new \App\Exports\BankUploadExport($this->period->id, $this->period->id);
        $this->assertEquals([
            'Last Name',
            'First Name',
            'Middle Name',
            'Employee Account Number',
            'Amount',
        ], $export->headings());

        $collection = $export->collection();
        $this->assertCount(2, $collection);
        $firstMapped = $export->map($collection->first());
        $this->assertCount(5, $firstMapped);
    }

    /**
     * Test Single-Period Deduction History PDF generation with itemized columns and zero pruning.
     */
    public function test_single_period_deduction_history_pdf_generation(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.deduction-history', $view);
            $this->assertCount(2, $data['reportData']);
            $this->assertArrayHasKey('sss_contribution', $data['columnTotals']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/deduction-history', [
            'payroll_period_id' => $this->period->id,
            'department_id' => 'all',
            'dont_include_zero' => '1',
            'selected_deduction_types' => ['sss_contribution', 'philhealth_contribution', 'pagibig_contribution'],
            'export_format' => 'pdf',
        ]);

        $response->assertOk();
    }

    /**
     * Test Single-Period Deduction History Excel and CSV exports.
     */
    public function test_single_period_deduction_history_excel_and_csv_exports(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/deduction-history', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Deduction_History_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/deduction-history', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Deduction_History_July_1-15_Cutoff.csv');
    }

    /**
     * Test Single-Period Income History PDF generation with itemized columns and zero pruning.
     */
    public function test_single_period_income_history_pdf_generation(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.income-history', $view);
            $this->assertCount(2, $data['reportData']);
            $this->assertArrayHasKey('basic_salary', $data['columnTotals']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/income-history', [
            'payroll_period_id' => $this->period->id,
            'department_id' => 'all',
            'dont_include_zero' => '1',
            'selected_income_types' => ['basic_salary', 'overtime_pay', 'allowances'],
            'export_format' => 'pdf',
        ]);

        $response->assertOk();
    }

    /**
     * Test Single-Period Income History Excel and CSV exports.
     */
    public function test_single_period_income_history_excel_and_csv_exports(): void
    {
        $this->actingAs($this->companyAdmin);
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/income-history', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Income_History_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/income-history', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Income_History_July_1-15_Cutoff.csv');
    }

    /**
     * Test Single-Period Income History with selective options (e.g., only basic_salary).
     */
    public function test_single_period_income_history_selective_income_types(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.income-history', $view);
            $this->assertCount(2, $data['reportData']);
            $this->assertEquals(['basic_salary'], $data['selectedIncomes']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/income-history', [
            'payroll_period_id' => $this->period->id,
            'selected_income_types' => ['basic_salary'],
            'export_format' => 'pdf',
        ]);

        $response->assertOk();
    }

    /**
     * Test Single-Period Income History defaults to all columns when no income types selected.
     */
    public function test_single_period_income_history_default_all_columns_when_empty(): void
    {
        $this->actingAs($this->companyAdmin);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.income-history', $view);
            $this->assertCount(2, $data['reportData']);
            $this->assertContains('basic_salary', $data['selectedIncomes']);
            $this->assertContains('overtime_pay', $data['selectedIncomes']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/income-history', [
            'payroll_period_id' => $this->period->id,
            'selected_income_types' => [],
            'export_format' => 'pdf',
        ]);

        $response->assertOk();
    }

    /**
     * Test Deduction Ledger PDF generation and Excel/CSV exports.
     */
    public function test_deduction_ledger_pdf_generation_and_exports(): void
    {
        $this->actingAs($this->companyAdmin);

        // Test PDF generation
        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.deduction-ledger', $view);
            $this->assertNotEmpty($data['reportData']);
            $this->assertEquals('SSS Loan', $data['deductionLabel']);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $responsePdf = $this->post('/company/reports/deduction-ledger', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'department_id' => 'all',
            'deduction_type_id' => 'sss_loan',
            'export_format' => 'pdf',
        ]);
        $responsePdf->assertOk();

        // Test Excel & CSV exports
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/deduction-ledger', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'deduction_type_id' => 'sss_loan',
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Deduction_Ledger_SSS_Loan_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/deduction-ledger', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'deduction_type_id' => 'sss_loan',
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Deduction_Ledger_SSS_Loan_July_1-15_Cutoff.csv');
    }

    /**
     * Test Single-Deduction Multi-Period Matrix PDF generation, zero pruning, and Excel/CSV exports.
     */
    public function test_single_deduction_multi_period_matrix_pdf_generation_and_exports(): void
    {
        $this->actingAs($this->companyAdmin);

        // Test PDF generation
        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $this->assertEquals('admin.reports.single-deduction-matrix', $view);
            $this->assertNotEmpty($data['reportData']);
            $this->assertEquals('SSS Loan', $data['deductionLabel']);
            $this->assertArrayHasKey('columnTotals', $data);
            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->with('legal', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $responsePdf = $this->post('/company/reports/single-deduction-matrix', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'department_id' => 'all',
            'deduction_type_id' => 'sss_loan',
            'export_format' => 'pdf',
        ]);
        $responsePdf->assertOk();

        // Test Excel & CSV exports
        \Maatwebsite\Excel\Facades\Excel::fake();

        $responseXlsx = $this->post('/company/reports/single-deduction-matrix', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'deduction_type_id' => 'sss_loan',
            'export_format' => 'xlsx',
        ]);
        $responseXlsx->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Single_Deduction_Matrix_SSS_Loan_July_1-15_Cutoff.xlsx');

        $responseCsv = $this->post('/company/reports/single-deduction-matrix', [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'deduction_type_id' => 'sss_loan',
            'export_format' => 'csv',
        ]);
        $responseCsv->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Single_Deduction_Matrix_SSS_Loan_July_1-15_Cutoff.csv');
    }

    /**
     * Test Employee AJAX Search endpoint with real-time search by ID, name, department, and pagination.
     */
    public function test_employee_ajax_search_endpoint(): void
    {
        $this->actingAs($this->companyAdmin);

        $response = $this->getJson('/company/employees/search?q=' . urlencode($this->emp1->first_name));

        $response->assertOk();
        $response->assertJsonStructure([
            'results' => [
                '*' => ['id', 'text', 'employee_code', 'full_name', 'department']
            ],
            'pagination' => ['more']
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['results']);
        $first = $data['results'][0];
        $this->assertStringContainsString($this->emp1->employee_code, $first['text']);
    }
}


