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

class PayrollDeductionInputTotalSyncTest extends TestCase
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
            'name' => 'Admin User',
            'email' => 'admin_sync@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Sync Test Corp',
            'company_code' => 'SYNCTEST',
            'email' => 'sync@test.com',
            'status' => '1',
        ]);

        $this->hrUser = User::create([
            'name' => 'HR User',
            'email' => 'hr_sync@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company->id,
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'IT',
            'name' => 'Information Technology',
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
     * Test 1: Editing pay_out or manual deduction payload is rejected with 403 when payroll_deduction_inputs is OFF.
     */
    public function test_payout_editing_is_denied_when_payroll_deduction_inputs_is_off(): void
    {
        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_deduction_inputs', false);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DED-OFF',
            'first_name' => 'Ded',
            'last_name' => 'Off',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $customDt = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'CANTEEN',
            'name' => 'Canteen Expense',
            'category' => 'other',
            'is_active' => true,
        ]);

        $payOut = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $customDt->id,
            'number_input' => 1.00,
            'rate' => 150.00,
        ]);

        // Attempt pay_outs payload update as HR without deduction permission
        $response = $this->actingAs($this->hrUser)->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 13,
            'pay_outs' => [
                ['id' => $payOut->id, 'rate' => 300.00],
            ],
        ]);
        $response->assertStatus(403);

        // Attempt legacy deduction payload update as HR without deduction permission
        $responseLegacy = $this->actingAs($this->hrUser)->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 13,
            'other_deductions' => 500.00,
        ]);
        $responseLegacy->assertStatus(403);
    }

    /**
     * Test 2: Allowed edit updates PayOut and recalculates PayHeader total_deductions and net_pay correctly.
     */
    public function test_allowed_deduction_edit_updates_payout_and_header_totals(): void
    {
        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_deduction_inputs', true);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DED-ON',
            'first_name' => 'Ded',
            'last_name' => 'On',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $customDt = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'TOOL_FEE',
            'name' => 'Tool Fee',
            'category' => 'other',
            'is_active' => true,
        ]);

        $payOut = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $customDt->id,
            'number_input' => 1.00,
            'rate' => 200.00,
        ]);

        $draft->recalculateTotals();
        $initialDeductions = (float) $draft->total_deductions;

        // Edit Tool Fee from 200 to 450
        $response = $this->actingAs($this->hrUser)->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 13,
            'pay_outs' => [
                ['id' => $payOut->id, 'rate' => 450.00],
            ],
        ]);
        $response->assertRedirect();

        $draft->refresh();
        $payOut->refresh();

        $this->assertEquals(450.00, (float) $payOut->rate);
        $this->assertEquals($initialDeductions + 250.00, (float) $draft->total_deductions);
        $this->assertEquals((float) $draft->total_gross_pay - (float) $draft->total_deductions, (float) $draft->net_pay);
    }

    /**
     * Test 3: Live-rendered Blade form respects deduction permission by disabling inputs when OFF.
     */
    public function test_live_rendered_form_disables_deduction_inputs_when_permission_is_off(): void
    {
        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_deduction_inputs', false);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-BLADE-DED',
            'first_name' => 'Blade',
            'last_name' => 'Ded',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $response = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', [
            'id' => $draft->id,
            'payroll_period_id' => $this->period->id,
        ]));

        $response->assertOk();
        $response->assertSee('(View-only)');
        $response->assertSee('disabled', false);
    }

    /**
     * Test 4: ABSENCES line with fixed amount stores number_input 1.00 and does not change on days_absent update.
     */
    public function test_absences_stores_valid_nonzero_daily_rate_and_correct_number_input(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-SRC',
            'first_name' => 'Absent',
            'last_name' => 'Worker',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        // 4a. Generation with fixed ABSENCES = 2000.00 and days_absent = 2.0
        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 2000.00],
            ],
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 11.0,
                    'days_absent' => 2.0,
                ],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $absDeductionType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();
        $absPayOuts = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $absDeductionType->id)
            ->get();

        $this->assertCount(1, $absPayOuts, 'Must have exactly one ABSENCES payout line.');
        $absPayOut = $absPayOuts->first();
        $this->assertEquals(1.0, (float) $absPayOut->number_input);
        $this->assertEquals(2000.00, (float) $absPayOut->rate);
        $this->assertEquals(2000.00, (float) ($absPayOut->rate * $absPayOut->number_input));

        // 4b. Update days_absent from 2.0 to 3.5 on draft edit
        $response = $this->actingAs($this->adminUser)->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 9.5,
            'days_absent' => 3.5,
        ]);
        $response->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertEquals(3.5, (float) $draft->days_absent);

        $updatedAbsPayOuts = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $absDeductionType->id)
            ->get();

        $this->assertCount(1, $updatedAbsPayOuts, 'Must not duplicate ABSENCES line on update.');
        $updatedAbsPayOut = $updatedAbsPayOuts->first();
        $this->assertEquals(1.0, (float) $updatedAbsPayOut->number_input);
        $this->assertEquals(2000.00, (float) $updatedAbsPayOut->rate);
        $this->assertEquals(2000.00, (float) ($updatedAbsPayOut->rate * $updatedAbsPayOut->number_input));
    }

    /**
     * Test 5: Invariant - PayrollSummaryReportService dynamically reads rate * number_input identically across all income and deduction types.
     */
    public function test_all_incomes_and_deductions_sync_identically_to_header_totals_and_payroll_summary_report(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ALL-SYNC',
            'first_name' => 'Multi',
            'last_name' => 'Type',
            'monthly_rate' => 26000.00, // Daily rate = 1000.00
            'status' => 'active',
        ]);

        $allowanceType = IncomeType::withoutGlobalScopes()->where('code', 'ALLOWANCE')->firstOrFail();
        $customIncType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'PROJECT_BONUS',
            'name' => 'Project Bonus',
            'is_active' => true,
        ]);

        $customDedType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'GYM_MEMBERSHIP',
            'name' => 'Gym Membership',
            'is_active' => true,
        ]);

        $sssLoanDedType = DeductionType::withoutGlobalScopes()->where('code', 'SSS_LOAN')->firstOrFail();
        $loanType = LoanType::create([
            'company_id' => $this->company->id,
            'name' => 'SSS Calamity Loan',
            'deduction_type_id' => $sssLoanDedType->id,
            'is_active' => true,
        ]);
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'monthly_deduction' => 500.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'days_absent' => 1.0, // 1 day absent = 1000.00
        ]);
        $draft = $headers->first();

        // 1. Add normal income (ALLOWANCE: 2 x 500 = 1000)
        $allowancePayIn = PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $allowanceType->id,
            'number_input' => 2.00,
            'rate' => 500.00,
        ]);

        // 2. Add custom income (PROJECT_BONUS: 1 x 1500 = 1500)
        $customPayIn = PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $customIncType->id,
            'number_input' => 1.00,
            'rate' => 1500.00,
        ]);

        // 3. Add custom deduction (GYM_MEMBERSHIP: 1 x 300 = 300)
        $customPayOut = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $customDedType->id,
            'number_input' => 1.00,
            'rate' => 300.00,
        ]);

        // 4. Update loan deduction (SSS_LOAN: 1 x 500 = 500)
        $loanPayOut = PayOut::where('pay_id', $draft->id)->where('loan_id', $loan->id)->first();
        if ($loanPayOut) {
            $loanPayOut->update(['rate' => 500.00, 'number_input' => 1.00]);
        } else {
            $loanPayOut = PayOut::create([
                'pay_id' => $draft->id,
                'deduction_type_id' => $sssLoanDedType->id,
                'loan_id' => $loan->id,
                'number_input' => 1.00,
                'rate' => 500.00,
            ]);
        }

        // Recalculate totals
        $draft->recalculateTotals();
        $draft->refresh();

        // Verify PayrollTotalsCalculator sums
        $expectedGross = (float) PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->sum(\DB::raw('rate * number_input'));
        $expectedDeductions = (float) PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->sum(\DB::raw('rate * number_input'));
        $expectedNet = $expectedGross - $expectedDeductions;

        $this->assertEquals($expectedGross, (float) $draft->total_gross_pay);
        $this->assertEquals($expectedDeductions, (float) $draft->total_deductions);
        $this->assertEquals($expectedNet, (float) $draft->net_pay);

        // Verify PayrollSummaryReportService matches PayHeader totals and line calculations exactly
        $reportService = app(PayrollSummaryReportService::class);
        $reportData = $reportService->build($this->period, $this->company, $this->company->id);

        $row = $reportData['payrollRows']->firstWhere('employee.id', $employee->id);
        $this->assertNotNull($row);
        $this->assertEquals((float) $draft->total_gross_pay, (float) $row['total_gross_pay']);
        $this->assertEquals((float) $draft->total_deductions, (float) $row['total_deductions']);
        $this->assertEquals((float) $draft->net_pay, (float) $row['net_pay']);

        // Check ABSENCES deduction amount in report is rate * number_input = 1000.00
        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();
        if (isset($row['displayed_deductions'][$absType->id])) {
            $this->assertEquals(1000.00, (float) $row['displayed_deductions'][$absType->id]);
        }
    }
}
