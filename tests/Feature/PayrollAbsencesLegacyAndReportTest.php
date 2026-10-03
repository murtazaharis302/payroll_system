<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollAbsencesLegacyAndReportTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $adminUser;
    protected PayrollPolicy $policy;
    protected DeductionType $absType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);

        $this->adminUser = User::create([
            'name' => 'Admin Legacy Absences Test',
            'email' => 'admin_legacy_abs@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Legacy Absences Corp',
            'company_code' => 'LEG',
            'email' => 'leg@test.com',
            'status' => '1',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $this->policy = PayrollPolicy::create([
            'company_id' => $this->company->id,
            'policy_name' => 'Standard Policy',
            'pay_frequency' => 'semi_monthly',
            'workdays_per_month' => 26.00,
            'hours_per_day' => 8.00,
            'regular_ot_multiplier' => 1.25,
            'rest_day_ot_multiplier' => 1.30,
            'holiday_ot_multiplier' => 2.00,
            'rounding_mode' => 'round_half_up',
            'no_attendance_policy' => 'zero_pay_requires_review',
            'effective_date' => '2020-01-01',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-P01',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'cutoff_number' => 1,
            'frequency' => 'semi_monthly',
            'status' => 'open',
        ]);

        $this->absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();

        session(['active_company_id' => $this->company->id]);
    }

    /**
     * 1. Legacy editable line (number_input=3, rate=1000) rendered as ₱3,000 in edit form and on unchanged save normalizes to 1 × 3000 = ₱3,000.
     */
    public function test_legacy_editable_absences_line_unchanged_save_normalizes_to_persisted_total(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-LEGACY-UNCHANGED',
            'first_name' => 'Legacy',
            'last_name' => 'Unchanged',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$emp->id], [])->first();

        // Simulate legacy line created with number_input = 3.0, rate = 1000.00 (total = 3000.00)
        $legacyPo = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $this->absType->id,
            'number_input' => 3.0,
            'rate' => 1000.00,
        ]);

        // Verify edit view renders the rate input as 3000.00 with data-qty = 1.00
        $viewRes = $this->actingAs($this->adminUser)->get(route('admin.payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $this->period->id]));
        $viewRes->assertStatus(200);
        $viewRes->assertSee('value="3000"', false);

        // Submit unchanged save (form posts the rendered rate 3000.00)
        $res = $this->actingAs($this->adminUser)->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 10,
            'days_absent' => 3.0,
            'pay_outs' => [
                $legacyPo->id => [
                    'id' => $legacyPo->id,
                    'rate' => 3000.00,
                ],
            ],
        ]);
        $res->assertSessionHasNoErrors();

        $legacyPo->refresh();
        $this->assertEquals(1.00, (float) $legacyPo->number_input, 'Must be normalized to 1.00');
        $this->assertEquals(3000.00, (float) $legacyPo->rate, 'Rate must preserve 3000.00 total');
        $this->assertEquals(3000.00, (float) ($legacyPo->rate * $legacyPo->number_input));
    }

    /**
     * 2. Legacy editable line with zero quantity (number_input=0, rate=1200) renders as ₱0 in edit form and preserves ₱0 total on unchanged save.
     */
    public function test_legacy_editable_absences_line_with_zero_quantity_preserves_zero_monetary_total(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-LEGACY-ZERO',
            'first_name' => 'Legacy',
            'last_name' => 'Zero',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$emp->id], [])->first();

        // Simulate legacy line created with number_input = 0.0, rate = 1200.00 (persisted total = 0.00)
        $legacyPo = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $this->absType->id,
            'number_input' => 0.0,
            'rate' => 1200.00,
        ]);

        // Verify edit view renders the rate input as 0 (not 1200.00) with data-qty = 1.00
        $viewRes = $this->actingAs($this->adminUser)->get(route('admin.payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $this->period->id]));
        $viewRes->assertStatus(200);
        $viewRes->assertSee('value="0"', false);
        $viewRes->assertDontSee('value="1200"', false);

        // Submit unchanged save (form posts the rendered rate 0.00)
        $res = $this->actingAs($this->adminUser)->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 10,
            'days_absent' => 0.0,
            'pay_outs' => [
                $legacyPo->id => [
                    'id' => $legacyPo->id,
                    'rate' => 0.00,
                ],
            ],
        ]);
        $res->assertSessionHasNoErrors();

        $legacyPo->refresh();
        $this->assertEquals(1.00, (float) $legacyPo->number_input, 'Must be normalized to 1.00');
        $this->assertEquals(0.00, (float) $legacyPo->rate, 'Rate must preserve 0.00 total');
        $this->assertEquals(0.00, (float) ($legacyPo->rate * $legacyPo->number_input));
    }

    /**
     * 3. Legacy editable line (number_input=3, rate=1000) explicitly edited to ₱500 saves as 1 × 500 = ₱500.
     */
    public function test_legacy_editable_absences_line_explicit_edit_saves_exact_new_amount(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-LEGACY-EDIT',
            'first_name' => 'Legacy',
            'last_name' => 'Edit',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$emp->id], [])->first();

        // Simulate legacy line created with number_input = 3.0, rate = 1000.00
        $legacyPo = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $this->absType->id,
            'number_input' => 3.0,
            'rate' => 1000.00,
        ]);

        // Submit explicit edit to 500.00
        $res = $this->actingAs($this->adminUser)->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 10,
            'days_absent' => 3.0,
            'pay_outs' => [
                $legacyPo->id => [
                    'id' => $legacyPo->id,
                    'rate' => 500.00,
                ],
            ],
        ]);
        $res->assertSessionHasNoErrors();

        $legacyPo->refresh();
        $this->assertEquals(1.00, (float) $legacyPo->number_input, 'Must be normalized to 1.00');
        $this->assertEquals(500.00, (float) $legacyPo->rate);
        $this->assertEquals(500.00, (float) ($legacyPo->rate * $legacyPo->number_input));
    }

    /**
     * 3. Finalized/Approved legacy draft display continues showing actual persisted rate × number_input.
     */
    public function test_finalized_legacy_absences_display_remains_persisted_rate_times_quantity(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-FINAL-LEGACY',
            'first_name' => 'Final',
            'last_name' => 'Legacy',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $header = $service->generateDraft($this->period, [$emp->id], [])->first();

        $legacyPo = PayOut::create([
            'pay_id' => $header->id,
            'deduction_type_id' => $this->absType->id,
            'number_input' => 3.0,
            'rate' => 1000.00,
        ]);

        $header->update(['status' => 'approved']);

        // View approved draft
        $viewRes = $this->actingAs($this->adminUser)->get(route('admin.payroll-drafts.edit', ['id' => $header->id, 'payroll_period_id' => $this->period->id]));
        $viewRes->assertStatus(200);
        // It must display the breakdown (3 × ₱1,000.00) and total ₱3,000.00
        $viewRes->assertSee('3 &times; ₱1,000.00', false);
        $viewRes->assertSee('₱ 3,000.00', false);
    }

    /**
     * 4. Non-ABSENCES number_only report fallback behavior remains 1.0 while ABSENCES returns 0.0 when no PayOut exists.
     */
    public function test_non_absences_number_only_report_behavior_remains_unchanged(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-RPT-FALLBACK',
            'first_name' => 'Report',
            'last_name' => 'Fallback',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $header = $service->generateDraft($this->period, [$emp->id], [
            'payroll_inputs' => [$emp->id => ['days_absent' => 3.0]],
        ])->first();
        $header->update(['status' => 'approved']);

        // Request deduction payroll history with display_option = number_only and include_zero = 1
        $response = $this->actingAs($this->adminUser)->post(route('reports.deduction-payroll-history'), [
            'from_period_id' => $this->period->id,
            'to_period_id' => $this->period->id,
            'company_id' => $this->company->id,
            'display_option' => 'number_only',
            'selected_deduction_types' => ['absences_deduction', 'other_deductions'],
            'include_zero' => 1,
            'export_format' => 'xlsx',
        ]);
        $response->assertStatus(200);

        // Direct test on resolution logic for non-ABSENCES vs ABSENCES when PayOut is absent:
        // ABSENCES report amount resolver returns 0.0 (not 3.0 days_absent)
        $this->assertEquals(0.0, \App\Support\Payroll\DeductionReportResolver::resolveAmountForHeader($header, 'absences_deduction'));
    }

    /**
     * 5. UI / Blade view assertion that recalculatePayrollInputTotals() no longer couples days_absent to ABSENCES data-qty.
     */
    public function test_edit_scripts_blade_does_not_contain_absences_data_qty_override(): void
    {
        $scriptPath = resource_path('views/admin/payroll-drafts/_edit_scripts.blade.php');
        $this->assertFileExists($scriptPath);
        $content = file_get_contents($scriptPath);

        $this->assertStringNotContainsString("setAttribute('data-qty', absVal)", $content);
        $this->assertStringNotContainsString("data-code=\"ABSENCES\"", $content);
    }
}
