<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CorrectPayrollTotalsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected IncomeType $otherType;
    protected DeductionType $taxType;
    protected PayHeader $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->adminUser = User::create([
            'name' => 'Admin C08',
            'email' => 'admin_c08@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'C08 Company',
            'email' => 'c08@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'code' => 'DEPT-C08',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-C08-001',
            'first_name' => 'Client',
            'last_name' => 'Case',
            'monthly_rate' => 3456.00,
            'status' => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-C08',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $this->basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        $this->otherType = IncomeType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other Income', 'type' => 'taxable', 'is_active' => true]);
        $this->taxType = DeductionType::firstOrCreate(['code' => 'TAX'], ['name' => 'Tax', 'type' => 'statutory', 'is_active' => true]);

        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->otherType->id,
            'rate' => 1500.00,
            'frequency' => 'per_occurrence',
            'is_active' => true,
        ]);

        // Create initial draft matching client reported case (Basic 1728 + Other 1500 = 3228)
        $this->draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'draft',
            'total_gross_pay' => 3228.00,
            'total_deductions' => 0.00,
            'net_pay' => 3228.00,
        ]);

        PayIn::create([
            'pay_id' => $this->draft->id,
            'income_type_id' => $this->basicType->id,
            'number_input' => 1.00,
            'rate' => 1728.00,
        ]);

        PayIn::create([
            'pay_id' => $this->draft->id,
            'income_type_id' => $this->otherType->id,
            'number_input' => 1.00,
            'rate' => 1500.00,
        ]);
    }

    /**
     * Test 1: Draft generation stores exact rates and number_inputs for client scenario (3228 total).
     */
    public function test_draft_generation_stores_exact_rates_and_number_inputs_for_client_scenario(): void
    {
        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($this->period, [$this->employee->id], ['regenerate' => true]);
        $generated = $drafts->first();

        $this->assertEquals(3228.00, (float) $generated->total_gross_pay);
        $basicPayIn = $generated->payIns()->where('income_type_id', $this->basicType->id)->first();
        $this->assertEquals(1728.00, (float) ($basicPayIn->rate * $basicPayIn->number_input));
        $otherPayIn = $generated->payIns()->where('income_type_id', $this->otherType->id)->first();
        $this->assertEquals(1500.00, (float) ($otherPayIn->rate * $otherPayIn->number_input));
    }

    /**
     * Test 2: Payroll Input UI immediately displays client scenario total of 3228.
     */
    public function test_payroll_input_ui_immediately_displays_client_scenario_total_3228(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draft->id, 'payroll_period_id' => $this->period->id]));

        $response->assertStatus(200);
        $response->assertSee('1,728.00');
        $response->assertSee('1,500.00');
        $response->assertSee('3,228.00');
    }

    /**
     * Test 3: Saving draft maintains client scenario total of 3228.
     */
    public function test_saving_draft_maintains_client_scenario_total_3228(): void
    {
        $basicPayIn = $this->draft->payIns()->where('income_type_id', $this->basicType->id)->first();
        $otherPayIn = $this->draft->payIns()->where('income_type_id', $this->otherType->id)->first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draft->id), [
                'days_worked' => 1.0,
                'pay_ins' => [
                    ['id' => $basicPayIn->id, 'number_input' => 1.00],
                    ['id' => $otherPayIn->id, 'number_input' => 1.00],
                ],
            ]);

        $response->assertRedirect();
        $this->draft->refresh();
        $this->assertEquals(3228.00, (float) $this->draft->total_gross_pay);
    }

    /**
     * Test 4: Reloading draft preserves client scenario total of 3228.
     */
    public function test_reloading_draft_preserves_client_scenario_total_3228(): void
    {
        $freshDraft = PayHeader::with('payIns')->find($this->draft->id);
        $this->assertEquals(3228.00, (float) $freshDraft->total_gross_pay);
    }

    /**
     * Test 5: Submitting draft preserves client scenario total of 3228.
     */
    public function test_submitting_draft_preserves_client_scenario_total_3228(): void
    {
        $this->draft->update(['status' => 'pending_approval']);
        $this->assertEquals(3228.00, (float) $this->draft->fresh()->total_gross_pay);
    }

    /**
     * Test 6: Approval recalculates and locks client scenario total of 3228.
     */
    public function test_approval_recalculates_and_locks_client_scenario_total_3228(): void
    {
        $this->draft->update(['status' => 'pending_approval']);
        $service = new PayrollProcessingService();
        $approved = $service->approvePayroll($this->draft, $this->adminUser);

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals(3228.00, (float) $approved->total_gross_pay);
    }

    /**
     * Test 7: Payroll summary reports display client scenario total of 3228.
     */
    public function test_payroll_summary_reports_display_client_scenario_total_3228(): void
    {
        $this->draft->update(['status' => 'approved']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.payroll-summary-1'), ['payroll_period_id' => $this->period->id, 'export_format' => 'html']);

        $response->assertStatus(200);
        $response->assertSee('3,228.00');
    }

    /**
     * Test 8: Voucher report displays client scenario total of 3228.
     */
    public function test_voucher_report_displays_client_scenario_total_3228(): void
    {
        $this->draft->update(['status' => 'approved']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.voucher'), ['payroll_period_id' => $this->period->id, 'export_format' => 'html']);

        $response->assertStatus(200);
        $response->assertSee('3,228.00');
    }

    /**
     * Test 9: Payroll register report displays client scenario total of 3228.
     */
    public function test_payroll_register_report_displays_client_scenario_total_3228(): void
    {
        $this->draft->update(['status' => 'approved']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.payroll-register'), ['payroll_period_id' => $this->period->id, 'export_format' => 'html']);

        $response->assertStatus(200);
        $response->assertSee('3,228.00');
    }

    /**
     * Test 10: Historical income reports display client scenario total of 3228.
     */
    public function test_historical_income_reports_display_client_scenario_total_3228(): void
    {
        $this->draft->update(['status' => 'approved']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.income-history'), ['payroll_period_id' => $this->period->id, 'export_format' => 'html']);

        $response->assertStatus(200);
        $response->assertSee('3,228.00');
    }

    /**
     * Test 11: Reports ignore subsequent changes to EmpIncome defaults.
     */
    public function test_reports_ignore_subsequent_changes_to_emp_income_defaults(): void
    {
        $this->draft->update(['status' => 'approved']);

        // Change default EmpIncome amount in employee setup
        EmpIncome::where('employee_id', $this->employee->id)->update(['rate' => 9999.00]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.income-history'), ['payroll_period_id' => $this->period->id, 'export_format' => 'html']);

        $response->assertStatus(200);
        $response->assertSee('3,228.00');
        $response->assertDontSee('9,999.00');
    }

    /**
     * Test 12: Two income lines of the same type are summed correctly.
     */
    public function test_two_income_lines_of_same_type_are_summed_correctly(): void
    {
        PayIn::create([
            'pay_id' => $this->draft->id,
            'income_type_id' => $this->otherType->id,
            'number_input' => 1.00,
            'rate' => 500.00,
        ]);

        $totals = $this->draft->recalculateTotals();
        $this->assertEquals(3728.00, $totals['total_gross_pay']);
    }

    /**
     * Test 13: Decimal number_input such as 0.5 is preserved.
     */
    public function test_decimal_number_input_such_as_half_day_is_preserved(): void
    {
        $basicPayIn = $this->draft->payIns()->where('income_type_id', $this->basicType->id)->first();
        $basicPayIn->update(['number_input' => 0.50, 'rate' => 2000.00]);

        $totals = $this->draft->recalculateTotals();
        $this->assertEquals(2500.00, $totals['total_gross_pay']); // (0.5 * 2000) + 1500 = 2500
    }

    /**
     * Test 14: Reports do not divide monthly values again.
     */
    public function test_reports_do_not_divide_monthly_values_again(): void
    {
        $this->draft->update(['status' => 'approved']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.income-history'), ['payroll_period_id' => $this->period->id, 'export_format' => 'html']);

        $response->assertStatus(200);
        $response->assertSee('3,228.00');
        $response->assertDontSee('1,614.00'); // 3228 / 2 must not appear
    }

    /**
     * Test 15: Approved historical payroll remains 3228 even if employee current rate changes later.
     */
    public function test_approved_historical_payroll_remains_3228_even_if_employee_current_rate_later_changes(): void
    {
        $this->draft->update(['status' => 'approved']);

        // Employee rate is updated in Employee Master
        $this->employee->update(['monthly_rate' => 50000.00]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.income-history'), ['payroll_period_id' => $this->period->id, 'export_format' => 'html']);

        $response->assertStatus(200);
        $response->assertSee('3,228.00');
        $response->assertDontSee('50,000.00');
    }
}
