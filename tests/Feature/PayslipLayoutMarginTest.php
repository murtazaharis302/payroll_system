<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\DeductionType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayslipLayoutMarginTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected EmployeeMaster $emp1;
    protected EmployeeMaster $emp2;
    protected PayrollPeriod $period;
    protected PayHeader $payHeader1;
    protected PayHeader $payHeader2;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_payslip_test@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::SUPER_ADMIN,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Printable Margin Corp',
            'email' => 'margin_corp@test.com',
            'company_name' => 'Printable Margin Corp',
            'company_address' => '123 Business Avenue, Suite 100',
            'status' => 1,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin_margin_corp@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => $this->company->id,
            'status' => 1,
        ]);

        $this->emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-P1',
            'first_name' => 'Arthur',
            'last_name' => 'Dent',
            'status' => 'active',
            'monthly_rate' => 30000,
        ]);

        $this->emp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-P2',
            'first_name' => 'Ford',
            'last_name' => 'Prefect',
            'status' => 'active',
            'monthly_rate' => 35000,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'September 1-15 Cutoff',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'pay_date' => '2026-09-20',
            'status' => 'completed',
        ]);

        $basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        $sssType = DeductionType::firstOrCreate(['code' => 'SSS'], ['name' => 'SSS Contribution', 'is_active' => true]);

        $this->payHeader1 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->emp1->id,
            'status' => 'approved',
            'total_gross_pay' => 15000.00,
            'total_deductions' => 500.00,
            'net_pay' => 14500.00,
            'days_worked' => 11.0,
        ]);

        PayIn::create(['pay_id' => $this->payHeader1->id, 'income_type_id' => $basicType->id, 'rate' => 15000.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader1->id, 'deduction_type_id' => $sssType->id, 'rate' => 500.00, 'number_input' => 1]);

        $this->payHeader2 = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->emp2->id,
            'status' => 'approved',
            'total_gross_pay' => 17500.00,
            'total_deductions' => 600.00,
            'net_pay' => 16900.00,
            'days_worked' => 11.0,
        ]);

        PayIn::create(['pay_id' => $this->payHeader2->id, 'income_type_id' => $basicType->id, 'rate' => 17500.00, 'number_input' => 1]);
        PayOut::create(['pay_id' => $this->payHeader2->id, 'deduction_type_id' => $sssType->id, 'rate' => 600.00, 'number_input' => 1]);
    }

    /**
     * Test single payslip view includes safe 8mm printable side margins.
     */
    public function test_single_payslip_includes_safe_side_margins(): void
    {
        $view = view('admin.reports.payslip', [
            'payHeader' => $this->payHeader1,
            'company' => $this->company,
        ])->render();

        $this->assertStringContainsString('margin-left: 8mm;', $view);
        $this->assertStringContainsString('margin-right: 8mm;', $view);
        $this->assertStringNotContainsString('margin: 8px 8px;', $view);
        $this->assertStringContainsString('PAYSLIP', $view);
        $this->assertStringContainsString('Arthur', $view);
        $this->assertStringContainsString('Dent', $view);
    }

    /**
     * Test batch payslips view includes safe 8mm side margins.
     */
    public function test_batch_payslips_includes_safe_side_margins(): void
    {
        $payrolls = collect([$this->payHeader1, $this->payHeader2]);

        $view = view('admin.reports.batch-payslips', [
            'payrolls' => $payrolls,
            'company' => $this->company,
            'period' => $this->period,
        ])->render();

        $this->assertStringContainsString('margin: 8mm 8mm;', $view);
        $this->assertStringNotContainsString('margin: 2;', $view);
        $this->assertStringContainsString('Arthur Dent', $view);
        $this->assertStringContainsString('Ford Prefect', $view);
    }

    /**
     * Test batch payslips view renders 4 slots per A4 landscape page with 116mm x 69mm footprint and centered content.
     */
    public function test_batch_payslips_renders_four_slots_per_a4_landscape_page_with_expanded_footprint_and_inner_padding(): void
    {
        // 5 employees = 2 pages (Page 1: 4 slips, Page 2: 1 slip + 3 empty slots)
        $emp3 = EmployeeMaster::create(['company_id' => $this->company->id, 'employee_code' => 'EMP-P3', 'first_name' => 'Tricia', 'last_name' => 'McMillan', 'status' => 'active', 'monthly_rate' => 32000]);
        $emp4 = EmployeeMaster::create(['company_id' => $this->company->id, 'employee_code' => 'EMP-P4', 'first_name' => 'Zaphod', 'last_name' => 'Beeblebrox', 'status' => 'active', 'monthly_rate' => 45000]);
        $emp5 = EmployeeMaster::create(['company_id' => $this->company->id, 'employee_code' => 'EMP-P5', 'first_name' => 'Marvin', 'last_name' => 'Android', 'status' => 'active', 'monthly_rate' => 20000]);

        $h3 = PayHeader::create(['payroll_period_id' => $this->period->id, 'employee_id' => $emp3->id, 'status' => 'approved', 'total_gross_pay' => 16000, 'total_deductions' => 500, 'net_pay' => 15500]);
        $h4 = PayHeader::create(['payroll_period_id' => $this->period->id, 'employee_id' => $emp4->id, 'status' => 'approved', 'total_gross_pay' => 22500, 'total_deductions' => 800, 'net_pay' => 21700]);
        $h5 = PayHeader::create(['payroll_period_id' => $this->period->id, 'employee_id' => $emp5->id, 'status' => 'approved', 'total_gross_pay' => 10000, 'total_deductions' => 300, 'net_pay' => 9700]);

        $payrolls = collect([$this->payHeader1, $this->payHeader2, $h3, $h4, $h5]);

        $view = view('admin.reports.batch-payslips', [
            'payrolls' => $payrolls,
            'company' => $this->company,
            'period' => $this->period,
        ])->render();

        // 1. Page structure and pagination
        $this->assertEquals(2, substr_count($view, 'class="page-wrap"'));
        $this->assertEquals(2, substr_count($view, 'class="row-wrap row-top"')); // 1 top row per page * 2 pages = 2
        $this->assertEquals(2, substr_count($view, 'class="row-wrap row-bottom"')); // 1 bottom row per page * 2 pages = 2
        $this->assertEquals(8, substr_count($view, 'class="slip-cell"')); // 4 slots per page * 2 pages = 8
        $this->assertEquals(5, substr_count($view, 'class="payslip')); // 5 actual payslips rendered inside slots
        $this->assertEquals(1, substr_count($view, 'page-break-before: always;')); // Page break before page 2 only

        // 2. CSS dimensions: centered ~116mm, height: auto footprint (~12.5mm / 0.5in clearance on all sides)
        $this->assertStringContainsString('size: A4 landscape;', $view);
        $this->assertStringContainsString('margin: 8mm 8mm;', $view);
        $this->assertStringContainsString('height: 194mm;', $view);
        $this->assertStringContainsString('height: 95mm;', $view);
        $this->assertStringContainsString('vertical-align: middle;', $view);
        $this->assertStringContainsString('width: 116mm;', $view);
        $this->assertStringContainsString('height: auto;', $view);
        $this->assertStringContainsString('padding: 4mm 4.5mm;', $view);
        $this->assertStringContainsString('.row-top .payslip', $view);
        $this->assertStringContainsString('top: -3mm;', $view);
        $this->assertStringContainsString('margin: 0 auto;', $view);
        $this->assertStringContainsString('border-top: 1px dashed #aaa;', $view);
    }

    /**
     * Test batch payslips Dompdf render produces exact expected canvas page count without overflow.
     */
    public function test_batch_payslips_dompdf_canvas_page_count_matches_grid_geometry(): void
    {
        // 5 employees must render on exactly 2 pages in Dompdf canvas
        $emp3 = EmployeeMaster::create(['company_id' => $this->company->id, 'employee_code' => 'EMP-P3', 'first_name' => 'Tricia', 'last_name' => 'McMillan', 'status' => 'active', 'monthly_rate' => 32000]);
        $emp4 = EmployeeMaster::create(['company_id' => $this->company->id, 'employee_code' => 'EMP-P4', 'first_name' => 'Zaphod', 'last_name' => 'Beeblebrox', 'status' => 'active', 'monthly_rate' => 45000]);
        $emp5 = EmployeeMaster::create(['company_id' => $this->company->id, 'employee_code' => 'EMP-P5', 'first_name' => 'Marvin', 'last_name' => 'Android', 'status' => 'active', 'monthly_rate' => 20000]);

        $h3 = PayHeader::create(['payroll_period_id' => $this->period->id, 'employee_id' => $emp3->id, 'status' => 'approved', 'total_gross_pay' => 16000, 'total_deductions' => 500, 'net_pay' => 15500]);
        $h4 = PayHeader::create(['payroll_period_id' => $this->period->id, 'employee_id' => $emp4->id, 'status' => 'approved', 'total_gross_pay' => 22500, 'total_deductions' => 800, 'net_pay' => 21700]);
        $h5 = PayHeader::create(['payroll_period_id' => $this->period->id, 'employee_id' => $emp5->id, 'status' => 'approved', 'total_gross_pay' => 10000, 'total_deductions' => 300, 'net_pay' => 9700]);

        $payrolls = collect([$this->payHeader1, $this->payHeader2, $h3, $h4, $h5]);

        $pdf = app('dompdf.wrapper')
            ->loadView('admin.reports.batch-payslips', [
                'payrolls' => $payrolls,
                'company' => $this->company,
                'period' => $this->period,
            ])
            ->setPaper('a4', 'landscape');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $pageCount = $dompdf->getCanvas()->get_page_count();

        $this->assertEquals(2, $pageCount, '5 employees in 4-slot grid must produce exactly 2 pages with no spillover blank page.');
    }

    /**
     * Test dense scaling for 13+ items and compact scaling for 11-12 items.
     */
    public function test_batch_payslips_with_many_items_compact_scaling_fits_canvas(): void
    {
        $stressEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-STRESS',
            'first_name' => 'Stress',
            'last_name' => 'Tester',
            'status' => 'active',
            'monthly_rate' => 50000,
        ]);

        $stressHeader = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $stressEmp->id,
            'status' => 'approved',
            'total_gross_pay' => 25000,
            'total_deductions' => 5000,
            'net_pay' => 20000,
            'days_worked' => 11.0,
            'overtime_hours' => 5.0,
        ]);

        // Add 13 earnings rows (13+ items for dense mode)
        for ($i = 1; $i <= 13; $i++) {
            $incType = IncomeType::firstOrCreate(
                ['code' => "INC_STRESS_{$i}"],
                ['name' => "Allowance {$i}", 'type' => 'taxable', 'is_active' => true]
            );
            PayIn::create([
                'pay_id' => $stressHeader->id,
                'income_type_id' => $incType->id,
                'rate' => 500.00,
                'number_input' => 1,
            ]);
        }

        // Add 13 deduction rows
        for ($j = 1; $j <= 13; $j++) {
            $dedType = DeductionType::firstOrCreate(
                ['code' => "DED_STRESS_{$j}"],
                ['name' => "Deduction {$j}", 'is_active' => true]
            );
            PayOut::create([
                'pay_id' => $stressHeader->id,
                'deduction_type_id' => $dedType->id,
                'rate' => 200.00,
                'number_input' => 1,
            ]);
        }

        $stressHeader->load(['payIns.incomeType', 'payOuts.deductionType', 'employee']);

        $view = view('admin.reports.batch-payslips', [
            'payrolls' => collect([$stressHeader]),
            'company' => $this->company,
            'period' => $this->period,
        ])->render();

        // 1. Verify dense typography scaling applied for 13+ items
        $this->assertStringContainsString('font-size: 6.5px !important;', $view);
        $this->assertStringContainsString('font-size: 8.5px !important;', $view);

        // 2. Render in Dompdf to verify exactly 1 page and no clipping or overflow
        $pdf = app('dompdf.wrapper')
            ->loadView('admin.reports.batch-payslips', [
                'payrolls' => collect([$stressHeader]),
                'company' => $this->company,
                'period' => $this->period,
            ])
            ->setPaper('a4', 'landscape');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $pageCount = $dompdf->getCanvas()->get_page_count();

        $this->assertEquals(1, $pageCount, 'Single dense-item payslip must stay within 1 page on Dompdf canvas.');
    }
}
