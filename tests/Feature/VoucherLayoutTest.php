<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected IncomeType $otherType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Voucher Layout Test Company',
            'email' => 'voucher_test@test.com',
            'company_code' => 'COMP-VOUCHER',
            'company_name' => 'Voucher Layout Test Company',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-VOUCHER',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $this->basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        $this->otherType = IncomeType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other Income', 'type' => 'taxable', 'is_active' => true]);
    }

    protected function createEmployeeWithPayroll(string $code, string $firstName, string $lastName, float $basic = 1728.00, float $other = 1500.00): PayHeader
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'monthly_rate' => $basic * 2,
            'status' => 'active',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'basic_pay' => $basic,
            'other_income' => $other,
            'total_gross_pay' => $basic + $other,
            'total_deductions' => 0.00,
            'net_pay' => $basic + $other,
        ]);

        PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $this->basicType->id,
            'number_input' => 1.00,
            'rate' => $basic,
        ]);

        if ($other > 0) {
            PayIn::create([
                'pay_id' => $draft->id,
                'income_type_id' => $this->otherType->id,
                'number_input' => 1.00,
                'rate' => $other,
            ]);
        }

        return $draft;
    }

    /**
     * Test 1: Two employees without breakdown render a single voucher-page wrapper with 0 page breaks.
     */
    public function test_two_employees_without_breakdown_render_single_page_wrapper(): void
    {
        $this->createEmployeeWithPayroll('EMP-V01', 'John', 'Doe');
        $this->createEmployeeWithPayroll('EMP-V02', 'Jane', 'Smith');

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'false',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertEquals(1, substr_count($content, 'class="voucher-page"'));
        $this->assertEquals(2, substr_count($content, '<section class="voucher">'));
        $this->assertEquals(0, substr_count($content, 'class="page-break"'));
        $this->assertStringNotContainsString('class="breakdown-box"', $content);
    }

    /**
     * Test 2: Two employees with breakdown render breakdown inside particulars container.
     */
    public function test_two_employees_with_breakdown_render_breakdown_inside_particulars(): void
    {
        $this->createEmployeeWithPayroll('EMP-V01', 'John', 'Doe');
        $this->createEmployeeWithPayroll('EMP-V02', 'Jane', 'Smith');

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertEquals(1, substr_count($content, 'class="voucher-page"'));
        $this->assertEquals(2, substr_count($content, 'class="breakdown-box"'));
        $this->assertStringContainsString('Basic Pay - <strong>P</strong> 1,728.00', $content);
        $this->assertStringContainsString('Other Income - <strong>P</strong> 1,500.00', $content);
    }

    /**
     * Test 3: Three employees render two voucher-page wrappers with exactly 1 page break.
     */
    public function test_three_employees_render_two_page_wrappers_with_one_page_break(): void
    {
        $this->createEmployeeWithPayroll('EMP-V01', 'Emp', 'One');
        $this->createEmployeeWithPayroll('EMP-V02', 'Emp', 'Two');
        $this->createEmployeeWithPayroll('EMP-V03', 'Emp', 'Three');

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertEquals(2, substr_count($content, 'class="voucher-page"'));
        $this->assertEquals(3, substr_count($content, '<section class="voucher">'));
        $this->assertEquals(1, substr_count($content, 'class="page-break"'));
    }

    /**
     * Test 4: Long employee name is handled safely.
     */
    public function test_long_employee_name_is_handled_safely(): void
    {
        $this->createEmployeeWithPayroll('EMP-VLONG', 'Supercalifragilisticexpialidocious', 'Longname-Extraordinarius-MultiWord-LastName');

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
            ]);

        $response->assertStatus(200);
        $response->assertSee('SUPERCALIFRAGILISTICEXPIALIDOCIOUS');
    }

    /**
     * Test 5: Many income lines fit inside particulars container.
     */
    public function test_many_income_lines_fit_inside_particulars_container(): void
    {
        $draft = $this->createEmployeeWithPayroll('EMP-MANY', 'Many', 'Incomes', 1000.00, 200.00);

        for ($i = 3; $i <= 6; $i++) {
            $type = IncomeType::create(['code' => "INC_{$i}", 'name' => "Income Type {$i}", 'type' => 'taxable', 'is_active' => true]);
            PayIn::create([
                'pay_id' => $draft->id,
                'income_type_id' => $type->id,
                'number_input' => 1.00,
                'rate' => 100.00 * $i,
            ]);
        }

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Income Type 3 - <strong>P</strong> 300.00', $content);
        $this->assertStringContainsString('Income Type 6 - <strong>P</strong> 600.00', $content);
    }

    /**
     * Test 6: Odd employee count produces zero trailing page-break element.
     */
    public function test_odd_employee_count_produces_zero_trailing_page_break(): void
    {
        $this->createEmployeeWithPayroll('EMP-V01', 'Single', 'Voucher');

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertEquals(1, substr_count($content, 'class="voucher-page"'));
        $this->assertEquals(1, substr_count($content, '<section class="voucher">'));
        $this->assertEquals(0, substr_count($content, 'class="page-break"'));
    }

    /**
     * Test 7: Include zero option toggles zero-amount income line rendering.
     */
    public function test_include_zero_option_toggles_zero_amount_income_line_rendering(): void
    {
        $draft = $this->createEmployeeWithPayroll('EMP-ZERO', 'Zero', 'Test', 1000.00, 0.00);

        $zeroType = IncomeType::create(['code' => 'ZERO_INC', 'name' => 'Zero Income Line', 'type' => 'taxable', 'is_active' => true]);
        PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $zeroType->id,
            'number_input' => 1.00,
            'rate' => 0.00,
        ]);

        // When include_zero is false (default)
        $responseUnchecked = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
                'include_zero' => '0',
            ]);

        $responseUnchecked->assertStatus(200);
        $this->assertStringNotContainsString('Zero Income Line', $responseUnchecked->getContent());

        // When include_zero is true
        $responseChecked = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
                'include_zero' => '1',
            ]);

        $responseChecked->assertStatus(200);
        $this->assertStringContainsString('Zero Income Line - <strong>P</strong> 0.00', $responseChecked->getContent());
    }

    /**
     * Test 8: Selected income types without PayIn records appear as 0.00 lines when include_zero is true.
     */
    public function test_selected_income_types_without_payin_records_appear_as_zero_when_include_zero_is_enabled(): void
    {
        $draft = $this->createEmployeeWithPayroll('EMP-NO-ROW', 'NoRow', 'Test', 14400.00, 0.00);

        $bonusType = IncomeType::create(['code' => 'BONUS_NO_ROW', 'name' => 'Bonus', 'type' => 'taxable', 'is_active' => true]);
        $allowanceType = IncomeType::create(['code' => 'ALLOW_NO_ROW', 'name' => 'Allowance', 'type' => 'taxable', 'is_active' => true]);

        // When include_zero is true and specific income types are selected
        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
                'include_zero' => '1',
                'income_types' => [$this->basicType->id, $bonusType->id, $allowanceType->id],
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Basic Pay - <strong>P</strong> 14,400.00', $content);
        $this->assertStringContainsString('Bonus - <strong>P</strong> 0.00', $content);
        $this->assertStringContainsString('Allowance - <strong>P</strong> 0.00', $content);
        $this->assertStringContainsString('14,400.00', $content);
    }

    /**
     * Test 9: 13th month pay is included as a 0.00 line when include_zero is true.
     */
    public function test_thirteenth_month_pay_is_included_as_zero_line_when_include_zero_is_enabled(): void
    {
        $draft = $this->createEmployeeWithPayroll('EMP-13TH', 'Thirteenth', 'Month', 1000.00, 0.00);

        $thirteenthType = IncomeType::firstOrCreate(['code' => '13TH_MONTH'], ['name' => '13th Month Pay', 'type' => 'taxable', 'is_active' => true]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => 'true',
                'include_zero' => '1',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('13th Month Pay - <strong>P</strong> 0.00', $content);
    }

    /**
     * Test 10: Display breakdown toggle with hidden field values ("0" vs "1").
     */
    public function test_display_breakdown_toggle_with_hidden_field_values_zero_and_one(): void
    {
        $this->createEmployeeWithPayroll('EMP-TOGGLE', 'Toggle', 'User');

        // When display_breakdown is '0' (unchecked checkbox with hidden field)
        $resUnchecked = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => '0',
            ]);

        $resUnchecked->assertStatus(200);
        $this->assertStringNotContainsString('class="breakdown-box"', $resUnchecked->getContent());

        // When display_breakdown is '1' (checked checkbox)
        $resChecked = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
                'display_breakdown' => '1',
            ]);

        $resChecked->assertStatus(200);
        $this->assertStringContainsString('class="breakdown-box"', $resChecked->getContent());
    }

    /**
     * Test 11: Voucher CSS layout includes safe printable height, 4.85in equal halves, and deliberate vertical separation.
     */
    public function test_voucher_css_top_margin_and_two_up_dimensions(): void
    {
        $this->createEmployeeWithPayroll('EMP-CSS1', 'Margin1', 'Test1');
        $this->createEmployeeWithPayroll('EMP-CSS2', 'Margin2', 'Test2');

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), [
                'payroll_period_id' => $this->period->id,
                'export_format' => 'html',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        // 2 vouchers inside 1 voucher-page wrapper without page breaks between them
        $this->assertEquals(1, substr_count($content, 'class="voucher-page"'));
        $this->assertEquals(2, substr_count($content, 'class="voucher-half'));
        $this->assertEquals(0, substr_count($content, 'class="page-break"'));

        // CSS printable area and balanced height constraints
        $this->assertStringContainsString('margin: 0.45in 0.25in 0.25in 0.25in;', $content);
        $this->assertStringContainsString('height: 9.90in;', $content);
        $this->assertStringContainsString('height: 4.85in;', $content);
        $this->assertStringContainsString('box-sizing: border-box;', $content);
        $this->assertStringContainsString('page-break-inside: avoid;', $content);
        $this->assertStringNotContainsString('flex-grow: 1;', $content);
        $this->assertStringContainsString('class="voucher-header-area"', $content);
        $this->assertStringContainsString('height: 140px;', $content);
        $this->assertStringContainsString('margin-top: 22px;', $content);
        $this->assertStringContainsString('margin-bottom: 14px;', $content);
    }
}
