<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use App\Services\Reports\PayrollSummaryReportService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollLineAndSummarySyncRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $adminUser;
    protected User $hrUser;
    protected PayrollPolicy $policy;

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
            'name' => 'Admin Regression',
            'email' => 'admin_reg@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Regression Sync Corp',
            'company_code' => 'REGSYNC',
            'email' => 'reg@test.com',
            'status' => '1',
        ]);

        $this->hrUser = User::create([
            'name' => 'HR Regression',
            'email' => 'hr_reg@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company->id,
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'ENG',
            'name' => 'Engineering',
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
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        session(['active_company_id' => $this->company->id]);
    }

    protected function setModuleAccess(string $module, bool $hasAccess): void
    {
        $menu = Menu::firstOrCreate(['module' => $module], ['name' => ucfirst(str_replace('_', ' ', $module)), 'slug' => $module, 'is_active' => true]);
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => 3, 'menu_id' => $menu->id],
            ['is_enabled' => $hasAccess]
        );
    }

    /**
     * Test: HR with payroll_deduction_inputs OFF can still update days_absent attendance without altering deductions.
     */
    public function test_hr_can_update_days_absent_without_deduction_permission(): void
    {
        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_deduction_inputs', false);
        $this->setModuleAccess('payroll_income_inputs', true);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-HR',
            'first_name' => 'Abs',
            'last_name' => 'Hr',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        // Submit days_absent update as HR (no pay_outs payload)
        $response = $this->actingAs($this->hrUser)->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 11.0,
            'days_absent' => 2.0,
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $draft->refresh();
        $this->assertEquals(2.0, (float) $draft->days_absent);

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();
        $absPayOut = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $absType->id)
            ->first();

        $this->assertNull($absPayOut, 'Days absent update must not auto-generate ABSENCES deduction');
    }

    /**
     * Test: Editing PayIns and PayOuts updates PayHeader totals and Payroll Summary Report identically.
     */
    public function test_editing_payins_and_payouts_syncs_with_report_and_totals(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-MUTATE',
            'first_name' => 'Mutate',
            'last_name' => 'Sync',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $allowanceType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'SPECIAL_ALLOWANCE',
            'name' => 'Special Allowance',
            'is_active' => true,
        ]);
        $uniformDedType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'UNIFORM',
            'name' => 'Uniform Deduction',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $allowancePayIn = PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $allowanceType->id,
            'number_input' => 1.00,
            'rate' => 300.00,
        ]);

        $uniformPayOut = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $uniformDedType->id,
            'number_input' => 1.00,
            'rate' => 150.00,
        ]);

        // Edit via update route
        $response = $this->actingAs($this->adminUser)->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 13.0,
            'pay_ins' => [
                ['id' => $allowancePayIn->id, 'number_input' => 3.00, 'rate' => 300.00],
            ],
            'pay_outs' => [
                ['id' => $uniformPayOut->id, 'rate' => 250.00],
            ],
        ]);
        $response->assertRedirect();

        $draft->refresh();
        $allowancePayIn->refresh();
        $uniformPayOut->refresh();

        $this->assertEquals(3.00, (float) $allowancePayIn->number_input);
        $this->assertEquals(900.00, (float) ($allowancePayIn->rate * $allowancePayIn->number_input));
        $this->assertEquals(250.00, (float) ($uniformPayOut->rate * $uniformPayOut->number_input));

        $reportService = app(PayrollSummaryReportService::class);
        $reportData = $reportService->build($this->period, $this->company, $this->company->id);
        $row = $reportData['payrollRows']->firstWhere('employee.id', $employee->id);

        $this->assertEquals((float) $draft->total_gross_pay, (float) $row['total_gross_pay']);
        $this->assertEquals((float) $draft->total_deductions, (float) $row['total_deductions']);
        $expectedTotalDeductions = (float) PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->sum(\DB::raw('rate * number_input'));
        $this->assertEquals($expectedTotalDeductions, (float) $row['total_deductions']);
    }

    /**
     * Test: Active recurring EmpDeduction for ABSENCES calculates using EmpDeduction rate without duplicate lines.
     */
    public function test_recurring_emp_deduction_for_absences_uses_daily_rate_and_days_absent(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-ABS',
            'first_name' => 'Rec',
            'last_name' => 'Abs',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();

        // Assign recurring deduction for ABSENCES with rate 500.00
        \App\Models\EmpDeduction::create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $absType->id,
            'rate' => 500.00,
            'frequency' => 'per_payroll',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'days_absent' => 3.0,
        ]);
        $draft = $headers->first();

        $absPayOuts = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $absType->id)
            ->get();

        $this->assertCount(1, $absPayOuts, 'Must have exactly one ABSENCES payout line.');
        $absPayOut = $absPayOuts->first();
        $this->assertEquals(1.0, (float) $absPayOut->number_input);
        $this->assertEquals(500.00, (float) $absPayOut->rate);
        $this->assertEquals(500.00, (float) ($absPayOut->rate * $absPayOut->number_input));
    }

    /**
     * Test: Zero days absent on generation results in 0.00 absence deduction amount (never 1.00 day deduction).
     */
    public function test_generation_with_zero_days_absent_does_not_deduct_one_day(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ZERO-ABS',
            'first_name' => 'Zero',
            'last_name' => 'Abs',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();

        // Generate with 0 days absent and no manual deduction
        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'days_absent' => 0.0,
        ]);
        $draft = $headers->first();

        $absPayOuts = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $absType->id)
            ->get();

        $this->assertTrue($absPayOuts->isEmpty());
    }

    /**
     * Test: Manual add of ABSENCES line normalizes number_input to 1.00 and preserves rate.
     */
    public function test_manual_add_absences_with_zero_quantity_creates_line_with_zero_amount_and_preserved_rate(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ADD-ZERO',
            'first_name' => 'Add',
            'last_name' => 'Zero',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();

        $response = $this->actingAs($this->adminUser)->post(route('admin.payroll-drafts.add-deduction', $draft->id), [
            'deduction_type_id' => $absType->id,
            'number_input' => 0,
            'rate' => 1250.00,
        ]);
        $response->assertSessionHasNoErrors();

        $addedLine = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $absType->id)
            ->first();

        $this->assertNotNull($addedLine);
        $this->assertEquals(1.00, (float)$addedLine->number_input);
        $this->assertEquals(1250.00, (float)$addedLine->rate);
        $this->assertEquals(1250.00, (float)($addedLine->number_input * $addedLine->rate));
    }
}
