<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\PayrollMasterSheetExport;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\PayrollSummaryReportService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollSummaryDeductionBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected EmployeeMaster $emp1;
    protected EmployeeMaster $emp2;
    protected Department $dept1;
    protected Department $dept2;
    protected PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_ded_test@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::SUPER_ADMIN,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Deduction Reconciliation Corp',
            'email' => 'ded_corp@test.com',
            'status' => 1,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin_ded_corp@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => $this->company->id,
            'status' => 1,
        ]);

        $this->dept1 = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'is_active' => true,
        ]);

        $this->dept2 = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Finance',
            'code' => 'FIN',
            'is_active' => true,
        ]);

        $this->emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-101',
            'first_name' => 'Charles',
            'last_name' => 'Darwin',
            'dept_id' => $this->dept1->id,
            'status' => 'active',
            'monthly_rate' => 40000,
            'date_hired' => '2025-01-01',
        ]);

        $this->emp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-102',
            'first_name' => 'Diana',
            'last_name' => 'Prince',
            'dept_id' => $this->dept2->id,
            'status' => 'active',
            'monthly_rate' => 45000,
            'date_hired' => '2025-01-01',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 1-15 Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'completed',
        ]);
    }

    /**
     * Helper to setup basic pay and overtime for pay headers.
     */
    protected function setupBasePay(PayHeader $ph, float $basicAmt, float $otAmt): void
    {
        $basicType = IncomeType::where('code', 'BASIC')->first();
        $otType = IncomeType::where('code', 'OVERTIME')->first();

        if ($basicType) {
            PayIn::create(['pay_id' => $ph->id, 'income_type_id' => $basicType->id, 'rate' => $basicAmt, 'number_input' => 1]);
        }
        if ($otType && $otAmt > 0) {
            PayIn::create(['pay_id' => $ph->id, 'income_type_id' => $otType->id, 'rate' => $otAmt, 'number_input' => 1]);
        }
    }

    /**
     * Test deduction reconciliation when 12 deduction types force column overflow into hiddenDeductions.
     */
    public function test_deduction_reconciliation_with_overflow(): void
    {
        // 1. Create 12 distinct active deduction types
        $dedTypes = [];
        for ($i = 1; $i <= 12; $i++) {
            $dedTypes[] = DeductionType::create([
                'company_id' => $this->company->id,
                'name' => sprintf('Deduction Type %02d', $i),
                'code' => sprintf('DED_%02d', $i),
                'is_active' => true,
            ]);
        }

        // Emp 1 PayHeader (Total deductions = 12 * 100 = 1200)
        $ph1 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->emp1->id,
            'status' => 'approved',
            'total_gross_pay' => 20000.00,
            'total_deductions' => 1200.00,
            'net_pay' => 18800.00,
        ]);
        $this->setupBasePay($ph1, 20000.00, 0.00);

        foreach ($dedTypes as $dt) {
            PayOut::create([
                'pay_id' => $ph1->id,
                'deduction_type_id' => $dt->id,
                'rate' => 100.00,
                'number_input' => 1,
            ]);
        }

        // Emp 2 PayHeader (Total deductions = 12 * 150 = 1800)
        $ph2 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->emp2->id,
            'status' => 'approved',
            'total_gross_pay' => 22500.00,
            'total_deductions' => 1800.00,
            'net_pay' => 20700.00,
        ]);
        $this->setupBasePay($ph2, 22500.00, 0.00);

        foreach ($dedTypes as $dt) {
            PayOut::create([
                'pay_id' => $ph2->id,
                'deduction_type_id' => $dt->id,
                'rate' => 150.00,
                'number_input' => 1,
            ]);
        }

        // 2. Build report data using PayrollSummaryReportService with 'letter' size (which has tighter column limits)
        $service = new PayrollSummaryReportService();
        $data = $service->build($this->period, $this->company, $this->company->id, 'all', 'letter');

        $this->assertTrue($data['hiddenDeductions']->isNotEmpty(), 'Expected hiddenDeductions to be non-empty with 12 deduction types.');

        $hiddenDedIds = $data['hiddenDeductions']->pluck('id')->toArray();

        // 3. Verify every employee row reconciles exactly: sum(displayed) + others == total_deductions
        foreach ($data['payrollRows'] as $row) {
            $sumDisplayed = array_sum($row['displayed_deductions']);
            $othersAmt = $row['others_deductions_amount'];
            $totalDed = $row['total_deductions'];

            $this->assertEquals($totalDed, $sumDisplayed + $othersAmt, 'Row total deductions must equal visible + others.');

            // Verify others_deductions_amount matches sum of hidden PayOut records directly
            $expectedOthers = PayOut::where('pay_id', $row['pay_header']->id)
                ->whereIn('deduction_type_id', $hiddenDedIds)
                ->get()
                ->sum(fn ($po) => (float) ($po->rate * ($po->number_input ?? 1.00)));

            $this->assertEquals($expectedOthers, $othersAmt, 'Others deduction amount must equal sum of hidden PayOuts.');
        }

        // 4. Verify grand totals reconcile
        $totals = $data['totals'];
        $sumGrandDisplayed = array_sum($totals['displayed_deductions']);
        $grandOthers = $totals['others_deductions_amount'];
        $grandTotalDed = $totals['total_deductions'];

        $this->assertEquals($grandTotalDed, $sumGrandDisplayed + $grandOthers, 'Grand total deductions must equal sum of displayed + others.');
    }

    /**
     * Test deduction reconciliation when all deduction types fit without overflow.
     */
    public function test_deduction_reconciliation_without_overflow(): void
    {
        // 1. Create only 2 deduction types
        $dt1 = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'SSS Contribution',
            'code' => 'SSS_TEST',
            'is_active' => true,
        ]);
        $dt2 = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'PhilHealth Contribution',
            'code' => 'PH_TEST',
            'is_active' => true,
        ]);

        $ph1 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->emp1->id,
            'status' => 'approved',
            'total_gross_pay' => 20000.00,
            'total_deductions' => 700.00,
            'net_pay' => 19300.00,
        ]);
        $this->setupBasePay($ph1, 20000.00, 0.00);

        PayOut::create(['pay_id' => $ph1->id, 'deduction_type_id' => $dt1->id, 'rate' => 500.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $ph1->id, 'deduction_type_id' => $dt2->id, 'rate' => 200.00, 'number_input' => 1]);

        $service = new PayrollSummaryReportService();
        $data = $service->build($this->period, $this->company, $this->company->id, 'all', 'legal');

        $this->assertTrue($data['hiddenDeductions']->isEmpty(), 'Expected hiddenDeductions to be empty when no overflow.');

        foreach ($data['payrollRows'] as $row) {
            $sumDisplayed = array_sum($row['displayed_deductions']);
            $this->assertEquals(0.00, $row['others_deductions_amount']);
            $this->assertEquals($row['total_deductions'], $sumDisplayed, 'Visible deductions alone must equal Total Ded when no overflow.');
        }

        $totals = $data['totals'];
        $this->assertEquals(0.00, $totals['others_deductions_amount']);
        $this->assertEquals($totals['total_deductions'], array_sum($totals['displayed_deductions']));
    }

    /**
     * Test Summary 1 and Summary 2 PDF report endpoints with deduction overflow.
     */
    public function test_summary_reports_pdf_rendering_with_overflow(): void
    {
        $this->actingAs($this->companyAdmin);

        for ($i = 1; $i <= 10; $i++) {
            DeductionType::create([
                'company_id' => $this->company->id,
                'name' => sprintf('Extra Ded %d', $i),
                'code' => sprintf('EXT_DED_%d', $i),
                'is_active' => true,
            ]);
        }

        $ph = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->emp1->id,
            'status' => 'approved',
            'total_gross_pay' => 20000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 19000.00,
        ]);
        $this->setupBasePay($ph, 20000.00, 0.00);

        // Test Summary 1
        $pdfMock1 = \Mockery::mock();
        $pdfMock1->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock1) {
            $this->assertEquals('admin.reports.payroll-summary-1', $view);
            $this->assertNotEmpty($data['payrollRows']);
            return $pdfMock1;
        });
        $pdfMock1->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock1->shouldReceive('stream')->andReturn(response('PDF_OK_1'));

        $this->app->instance('dompdf.wrapper', $pdfMock1);

        $res1 = $this->post('/company/reports/payroll-summary-1', [
            'payroll_period_id' => $this->period->id,
            'paper_size' => 'letter',
        ]);
        $res1->assertOk();

        // Test Summary 2
        $pdfMock2 = \Mockery::mock();
        $pdfMock2->shouldReceive('loadView')->andReturnUsing(function ($view, $data) use ($pdfMock2) {
            $this->assertEquals('admin.reports.payroll-summary-2', $view);
            $this->assertNotEmpty($data['payrolls']);
            return $pdfMock2;
        });
        $pdfMock2->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock2->shouldReceive('stream')->andReturn(response('PDF_OK_2'));

        $this->app->instance('dompdf.wrapper', $pdfMock2);

        $res2 = $this->post('/company/reports/payroll-summary-2', [
            'payroll_period_id' => $this->period->id,
            'paper_size' => 'letter',
        ]);
        $res2->assertOk();
    }
}
