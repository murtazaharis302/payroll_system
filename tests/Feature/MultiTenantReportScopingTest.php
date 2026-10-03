<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\PayHeader;
use App\Models\GovernmentContribution;
use App\Exports\SSSReportExport;
use App\Exports\PagIBIGReportExport;
use App\Exports\PhilHealthReportExport;
use App\Exports\WithholdingTaxReportExport;
use App\Exports\ThirteenthMonthExport;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiTenantReportScopingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyAlpha;
    protected Company $companyBeta;
    protected User $companyAdminAlpha;
    protected User $superAdmin;
    protected EmployeeMaster $empAlpha;
    protected EmployeeMaster $empBeta;
    protected PayrollPeriod $periodAlpha1;
    protected PayrollPeriod $periodAlpha2;
    protected PayrollPeriod $periodBeta1;
    protected PayHeader $payHeaderAlpha;
    protected PayHeader $payHeaderBeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        // 1. Create Super Admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::SUPER_ADMIN,
            'status' => 1,
        ]);

        // 2. Create Companies
        $this->companyAlpha = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Company Alpha',
            'email' => 'alpha@test.com',
            'status' => 1,
        ]);

        $this->companyBeta = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Company Beta',
            'email' => 'beta@test.com',
            'status' => 1,
        ]);

        // 3. Create Company Admin for Company Alpha
        $this->companyAdminAlpha = User::create([
            'name' => 'Alpha Admin',
            'email' => 'alphaadmin@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => $this->companyAlpha->id,
            'status' => 1,
        ]);

        // 4. Create Employees
        $this->empAlpha = EmployeeMaster::create([
            'company_id' => $this->companyAlpha->id,
            'employee_code' => 'ALPHA-001',
            'first_name' => 'John',
            'last_name' => 'Alpha',
            'date_hired' => '2025-01-01',
            'position' => 'Developer',
            'monthly_rate' => 30000,
            'status' => 'active',
        ]);

        $this->empBeta = EmployeeMaster::create([
            'company_id' => $this->companyBeta->id,
            'employee_code' => 'BETA-001',
            'first_name' => 'Jane',
            'last_name' => 'Beta',
            'date_hired' => '2025-01-01',
            'position' => 'Designer',
            'monthly_rate' => 35000,
            'status' => 'active',
        ]);

        // 5. Create Payroll Periods
        $this->periodAlpha1 = PayrollPeriod::create([
            'company_id' => $this->companyAlpha->id,
            'period_name' => 'Alpha 2026-07-01 to 2026-07-15',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
            'status' => 'completed',
        ]);

        $this->periodAlpha2 = PayrollPeriod::create([
            'company_id' => $this->companyAlpha->id,
            'period_name' => 'Alpha 2026-07-16 to 2026-07-31',
            'period_start' => '2026-07-16',
            'period_end' => '2026-07-31',
            'pay_date' => '2026-08-05',
            'status' => 'completed',
        ]);

        $this->periodBeta1 = PayrollPeriod::create([
            'company_id' => $this->companyBeta->id,
            'period_name' => 'Beta 2026-07-01 to 2026-07-15',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
            'status' => 'completed',
        ]);

        // 6. Create Pay Headers
        $this->payHeaderAlpha = PayHeader::create([
            'payroll_period_id' => $this->periodAlpha1->id,
            'employee_id' => $this->empAlpha->id,
            'basic_pay' => 15000,
            'total_gross_pay' => 15000,
            'gross_income' => 15000,
            'sss_contribution' => 500,
            'philhealth_contribution' => 300,
            'pagibig_contribution' => 100,
            'withholding_tax' => 1000,
            'total_deductions' => 1900,
            'net_pay' => 13100,
            'status' => 'approved',
        ]);

        $this->payHeaderBeta = PayHeader::create([
            'payroll_period_id' => $this->periodBeta1->id,
            'employee_id' => $this->empBeta->id,
            'basic_pay' => 17500,
            'total_gross_pay' => 17500,
            'gross_income' => 17500,
            'sss_contribution' => 600,
            'philhealth_contribution' => 350,
            'pagibig_contribution' => 100,
            'withholding_tax' => 1200,
            'total_deductions' => 2250,
            'net_pay' => 15250,
            'status' => 'approved',
        ]);

        $basicType = \App\Models\IncomeType::where('code', 'BASIC')->first();
        if ($basicType) {
            \App\Models\PayIn::create([
                'pay_id' => $this->payHeaderAlpha->id,
                'income_type_id' => $basicType->id,
                'rate' => 15000,
                'number_input' => 1,
            ]);
            \App\Models\PayIn::create([
                'pay_id' => $this->payHeaderBeta->id,
                'income_type_id' => $basicType->id,
                'rate' => 17500,
                'number_input' => 1,
            ]);
        }

        $sssType = \App\Models\DeductionType::where('code', 'SSS')->first();
        if ($sssType) {
            \App\Models\PayOut::create([
                'pay_id' => $this->payHeaderAlpha->id,
                'deduction_type_id' => $sssType->id,
                'rate' => 500,
                'number_input' => 1,
            ]);
            \App\Models\PayOut::create([
                'pay_id' => $this->payHeaderBeta->id,
                'deduction_type_id' => $sssType->id,
                'rate' => 600,
                'number_input' => 1,
            ]);
        }

        // 7. Create Government Contributions
        GovernmentContribution::create([
            'employee_id' => $this->empAlpha->id,
            'company_id' => $this->companyAlpha->id,
            'payroll_period_id' => $this->periodAlpha1->id,
            'type' => 'sss',
            'employee_share' => 500,
            'employer_share' => 1000,
            'total_contribution' => 1500,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'status' => 'paid',
        ]);

        GovernmentContribution::create([
            'employee_id' => $this->empBeta->id,
            'company_id' => $this->companyBeta->id,
            'payroll_period_id' => $this->periodBeta1->id,
            'type' => 'sss',
            'employee_share' => 600,
            'employer_share' => 1200,
            'total_contribution' => 1800,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'status' => 'paid',
        ]);
    }

    /**
     * Test Company Admin for Company Alpha cannot retrieve records belonging to Company Beta in Income Payroll History.
     */
    public function test_company_admin_cannot_bleed_data_in_income_payroll_history(): void
    {
        $this->actingAs($this->companyAdminAlpha);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $reportData = $data['reportData'] ?? [];
            $employeeIds = collect($reportData)->map(fn($row) => $row['employee']->id ?? 0)->toArray();

            $this->assertContains($this->empAlpha->id, $employeeIds);
            $this->assertNotContains($this->empBeta->id, $employeeIds, 'Company Beta employee must NOT be present in Company Alpha income history report');

            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/income-payroll-history', [
            'from_period_id' => $this->periodAlpha1->id,
            'to_period_id'   => $this->periodAlpha2->id,
            'include_zero'   => 1,
        ]);

        $response->assertOk();
    }

    /**
     * Test Company Admin attempting to override company_id query parameter is forced to their own company.
     */
    public function test_company_admin_cannot_override_company_id_parameter(): void
    {
        $this->actingAs($this->companyAdminAlpha);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $reportData = $data['reportData'] ?? [];
            $employeeIds = collect($reportData)->map(fn($row) => $row['employee']->id ?? 0)->toArray();

            $this->assertContains($this->empAlpha->id, $employeeIds);
            $this->assertNotContains($this->empBeta->id, $employeeIds, 'Company Beta employee must NOT be present when overriding company_id parameter');

            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        // Company Admin passes company_id of Company Beta
        $response = $this->post('/company/reports/income-payroll-history', [
            'from_period_id' => $this->periodAlpha1->id,
            'to_period_id'   => $this->periodAlpha2->id,
            'company_id'     => $this->companyBeta->id,
            'include_zero'   => 1,
        ]);

        $response->assertOk();
    }

    /**
     * Test Company Admin cannot see Beta employee in Deduction Payroll History report.
     */
    public function test_company_admin_cannot_bleed_data_in_deduction_payroll_history(): void
    {
        $this->actingAs($this->companyAdminAlpha);

        $pdfMock = \Mockery::mock();
        $pdfMock->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock) {
            $reportData = $data['reportData'] ?? [];
            $employeeIds = collect($reportData)->map(fn($row) => $row['employee']->id ?? 0)->toArray();

            $this->assertContains($this->empAlpha->id, $employeeIds);
            $this->assertNotContains($this->empBeta->id, $employeeIds);

            return $pdfMock;
        });
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('PDF_STREAM_OK'));

        $this->app->instance('dompdf.wrapper', $pdfMock);

        $response = $this->post('/company/reports/deduction-payroll-history', [
            'from_period_id' => $this->periodAlpha1->id,
            'to_period_id'   => $this->periodAlpha2->id,
            'include_zero'   => 1,
        ]);

        $response->assertOk();
    }

    /**
     * Test SSS Report Export strictly scopes contributions and gross pay by company.
     */
    public function test_sss_export_scopes_to_tenant_company(): void
    {
        $this->actingAs($this->companyAdminAlpha);

        $export = new SSSReportExport($this->companyAlpha->id, '2026-07');
        $collection = $export->collection();

        $employeeNumbers = $collection->pluck('employee_number')->toArray();

        $this->assertContains('ALPHA-001', $employeeNumbers);
        $this->assertNotContains('BETA-001', $employeeNumbers);
    }

    /**
     * Test 13th Month Export for Company Alpha excludes Company Beta employees.
     */
    public function test_thirteenth_month_export_scopes_by_company(): void
    {
        $this->actingAs($this->companyAdminAlpha);

        $export = ThirteenthMonthExport::forCompany($this->companyAlpha->id, 2026);
        $rows = $export->collection()->toArray();
        $employeeCodes = array_column($rows, 'employee_code');

        $this->assertContains('ALPHA-001', $employeeCodes);
        $this->assertNotContains('BETA-001', $employeeCodes);
    }

    /**
     * Test Super Admin can query Company Beta explicitly.
     */
    public function test_super_admin_can_query_specific_company(): void
    {
        $this->actingAs($this->superAdmin);

        $export = new SSSReportExport($this->companyBeta->id, '2026-07');
        $collection = $export->collection();

        $employeeNumbers = $collection->pluck('employee_number')->toArray();

        $this->assertContains('BETA-001', $employeeNumbers);
        $this->assertNotContains('ALPHA-001', $employeeNumbers);
    }
}
