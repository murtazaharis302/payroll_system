<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\LegacyThirteenthMonthHistoryService;
use App\Services\ThirteenthMonthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyThirteenthMonthHistoryReportTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected EmployeeMaster $emp1;
    protected EmployeeMaster $emp2;
    protected IncomeType $basicIncomeType;
    protected PayrollPeriod $period1;
    protected PayrollPeriod $period2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Company Admin',
            'email'    => 'admin@leg13th.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $this->company = Company::create([
            'name'         => 'Legacy 13th Corp',
            'email'        => 'corp@leg13th.test',
            'user_id'      => $this->admin->id,
            'company_code' => 'LEG13TH',
        ]);

        $this->admin->update(['company_id' => $this->company->id]);

        $dept = Department::create([
            'company_id' => $this->company->id,
            'code'       => 'ENG',
            'name'       => 'Engineering',
        ]);

        $this->emp1 = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'dept_id'       => $dept->id,
            'department'    => 'Engineering',
            'employee_code' => 'EMP-13-01',
            'first_name'    => 'Alice',
            'last_name'     => 'Smith',
            'status'        => 'active',
        ]);

        $this->emp2 = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'dept_id'       => $dept->id,
            'department'    => 'Engineering',
            'employee_code' => 'EMP-13-02',
            'first_name'    => 'Bob',
            'last_name'     => 'Jones',
            'status'        => 'active',
        ]);

        $this->basicIncomeType = IncomeType::withoutGlobalScopes()->where('code', 'BASIC')->first();
        if (!$this->basicIncomeType) {
            $this->basicIncomeType = IncomeType::create([
                'company_id' => $this->company->id,
                'name'       => 'Basic Pay',
                'code'       => 'BASIC',
                'is_active'  => true,
            ]);
        }

        $this->period1 = PayrollPeriod::create([
            'company_id'   => $this->company->id,
            'period_name'  => 'Jan 2026 1st Cutoff',
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-15',
            'pay_date'     => '2026-01-15',
            'status'       => 'open',
        ]);

        $this->period2 = PayrollPeriod::create([
            'company_id'   => $this->company->id,
            'period_name'  => 'Jan 2026 2nd Cutoff',
            'period_start' => '2026-01-16',
            'period_end'   => '2026-01-31',
            'pay_date'     => '2026-01-31',
            'status'       => 'open',
        ]);
    }

    public function test_service_generates_period_keyed_cutoffs_and_calculates_total_divided_by_11_5(): void
    {
        // Period 1: Both Alice and Bob have payrolls
        $ph1 = PayHeader::create([
            'employee_id'       => $this->emp1->id,
            'payroll_period_id' => $this->period1->id,
            'total_gross_pay'   => 15000.00,
            'net_pay'           => 14000.00,
            'status'            => 'approved',
        ]);
        PayIn::create([
            'pay_id'         => $ph1->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate'           => 15000.00,
            'number_input'   => 1.00,
        ]);

        $ph2 = PayHeader::create([
            'employee_id'       => $this->emp2->id,
            'payroll_period_id' => $this->period1->id,
            'total_gross_pay'   => 8000.00,
            'net_pay'           => 7500.00,
            'status'            => 'approved',
        ]);
        PayIn::create([
            'pay_id'         => $ph2->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate'           => 8000.00,
            'number_input'   => 1.00,
        ]);

        // Period 2: Only Alice has a payroll (Bob has no payroll in Period 2)
        $ph3 = PayHeader::create([
            'employee_id'       => $this->emp1->id,
            'payroll_period_id' => $this->period2->id,
            'total_gross_pay'   => 15000.00,
            'net_pay'           => 14000.00,
            'status'            => 'posted',
        ]);
        PayIn::create([
            'pay_id'         => $ph3->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate'           => 15000.00,
            'number_input'   => 1.00,
        ]);

        $service = app(LegacyThirteenthMonthHistoryService::class);
        $data = $service->generateReport($this->company->id, 2026);

        $this->assertCount(2, $data['cutoffs']);
        $this->assertArrayHasKey($this->period1->id, $data['cutoffs']);
        $this->assertArrayHasKey($this->period2->id, $data['cutoffs']);

        $this->assertCount(2, $data['rows']);

        // Alice: Period 1 = 15000, Period 2 = 15000 -> Total = 30000 -> 13th = 30000 / 11.5 = 2608.70
        $aliceRow = collect($data['rows'])->firstWhere('employee_id', $this->emp1->id);
        $this->assertNotNull($aliceRow);
        $this->assertEquals(15000.00, $aliceRow['cutoff_values'][$this->period1->id]);
        $this->assertEquals(15000.00, $aliceRow['cutoff_values'][$this->period2->id]);
        $this->assertEquals(30000.00, $aliceRow['total_basic']);
        $this->assertEquals(2608.70, $aliceRow['thirteenth_month_pay']); // 30000 / 11.5

        // Bob: Period 1 = 8000, Period 2 = 0.00 -> Total = 8000 -> 13th = 8000 / 11.5 = 695.65
        $bobRow = collect($data['rows'])->firstWhere('employee_id', $this->emp2->id);
        $this->assertNotNull($bobRow);
        $this->assertEquals(8000.00, $bobRow['cutoff_values'][$this->period1->id]);
        $this->assertEquals(0.00, $bobRow['cutoff_values'][$this->period2->id]);
        $this->assertEquals(8000.00, $bobRow['total_basic']);
        $this->assertEquals(695.65, $bobRow['thirteenth_month_pay']); // 8000 / 11.5
    }

    public function test_unapproved_draft_pay_headers_are_excluded_from_report(): void
    {
        $phDraft = PayHeader::create([
            'employee_id'       => $this->emp1->id,
            'payroll_period_id' => $this->period1->id,
            'total_gross_pay'   => 15000.00,
            'net_pay'           => 14000.00,
            'status'            => 'draft',
        ]);
        PayIn::create([
            'pay_id'         => $phDraft->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate'           => 15000.00,
            'number_input'   => 1.00,
        ]);

        $service = app(LegacyThirteenthMonthHistoryService::class);
        $data = $service->generateReport($this->company->id, 2026);

        $this->assertCount(0, $data['rows']);
        $this->assertCount(0, $data['cutoffs']);
    }

    public function test_existing_thirteenth_month_service_remains_untouched_and_uses_12(): void
    {
        $ph1 = PayHeader::create([
            'employee_id'       => $this->emp1->id,
            'payroll_period_id' => $this->period1->id,
            'total_gross_pay'   => 12000.00,
            'net_pay'           => 12000.00,
            'status'            => 'approved',
        ]);
        PayIn::create([
            'pay_id'         => $ph1->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate'           => 12000.00,
            'number_input'   => 1.00,
        ]);

        $service12 = app(ThirteenthMonthService::class);
        $result12 = $service12->computeThirteenthMonthPay($this->emp1, 2026);
        $this->assertEquals(1000.00, $result12['thirteenth_month_pay']); // 12000 / 12

        $service115 = app(LegacyThirteenthMonthHistoryService::class);
        $result115 = $service115->generateReport($this->company->id, 2026);
        $aliceRow = collect($result115['rows'])->firstWhere('employee_id', $this->emp1->id);
        $this->assertEquals(1043.48, $aliceRow['thirteenth_month_pay']); // 12000 / 11.5
    }

    public function test_web_pdf_and_excel_endpoints_render_for_company_admin(): void
    {
        $ph1 = PayHeader::create([
            'employee_id'       => $this->emp1->id,
            'payroll_period_id' => $this->period1->id,
            'total_gross_pay'   => 11500.00,
            'net_pay'           => 11500.00,
            'status'            => 'approved',
        ]);
        PayIn::create([
            'pay_id'         => $ph1->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate'           => 11500.00,
            'number_input'   => 1.00,
        ]);

        // Web View
        $responseWeb = $this->actingAs($this->admin)->get(route('reports.thirteenth-month-history', ['year' => 2026]));
        $responseWeb->assertStatus(200);
        $responseWeb->assertSee('Alice Smith');
        $responseWeb->assertSee('1,000.00'); // 11500 / 11.5 = 1000.00
        $responseWeb->assertSee('NAME');
        $responseWeb->assertSee('13TH MONTH PAY');

        // PDF View
        $responsePdf = $this->actingAs($this->admin)->get(route('reports.thirteenth-month-history.pdf', ['year' => 2026]));
        $responsePdf->assertStatus(200);
        $responsePdf->assertSee('Alice Smith');
        $responsePdf->assertSee('01/15/2026');

        // Excel View
        $responseExcel = $this->actingAs($this->admin)->get(route('reports.thirteenth-month-history.excel', ['year' => 2026]));
        $responseExcel->assertStatus(200);
    }

    public function test_post_form_submission_handles_export_formats(): void
    {
        $ph1 = PayHeader::create([
            'employee_id'       => $this->emp1->id,
            'payroll_period_id' => $this->period1->id,
            'total_gross_pay'   => 11500.00,
            'net_pay'           => 11500.00,
            'status'            => 'approved',
        ]);
        PayIn::create([
            'pay_id'         => $ph1->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate'           => 11500.00,
            'number_input'   => 1.00,
        ]);

        // POST with export_format = pdf
        $responsePostPdf = $this->actingAs($this->admin)->post(route('reports.thirteenth-month-history'), [
            'year'          => 2026,
            'export_format' => 'pdf',
        ]);
        $responsePostPdf->assertStatus(200);
        $responsePostPdf->assertSee('13TH MONTH PAYROLL HISTORY REPORT');

        // POST with export_format = xlsx
        $responsePostXlsx = $this->actingAs($this->admin)->post(route('reports.thirteenth-month-history'), [
            'year'          => 2026,
            'export_format' => 'xlsx',
        ]);
        $responsePostXlsx->assertStatus(200);

        // POST with export_format = html
        $responseHtml = $this->actingAs($this->admin)->post(route('reports.thirteenth-month-history'), [
            'year'          => 2026,
            'export_format' => 'html',
        ]);
        $responseHtml->assertStatus(200);
        $responseHtml->assertSee('Alice Smith');
    }

    public function test_strict_basic_code_detection_ignores_non_basic_income_types(): void
    {
        $otherIncomeType = IncomeType::create([
            'company_id' => $this->company->id,
            'name'       => 'Basic Allowance Extra',
            'code'       => 'ALLOWANCE_BASIC',
            'is_active'  => true,
        ]);

        $ph = PayHeader::create([
            'employee_id'       => $this->emp1->id,
            'payroll_period_id' => $this->period1->id,
            'total_gross_pay'   => 20000.00,
            'net_pay'           => 18000.00,
            'status'            => 'approved',
        ]);
        // Strict BASIC pay
        PayIn::create([
            'pay_id'         => $ph->id,
            'income_type_id' => $this->basicIncomeType->id,
            'rate'           => 11500.00,
            'number_input'   => 1.00,
        ]);
        // Non-BASIC allowance with 'basic' in name but different code
        PayIn::create([
            'pay_id'         => $ph->id,
            'income_type_id' => $otherIncomeType->id,
            'rate'           => 5000.00,
            'number_input'   => 1.00,
        ]);

        $service = app(LegacyThirteenthMonthHistoryService::class);
        $data = $service->generateReport($this->company->id, 2026);
        $aliceRow = collect($data['rows'])->firstWhere('employee_id', $this->emp1->id);

        // Should strictly be 11500.00 (ignoring the 5000.00 allowance)
        $this->assertEquals(11500.00, $aliceRow['total_basic']);
        $this->assertEquals(1000.00, $aliceRow['thirteenth_month_pay']);
    }
}
