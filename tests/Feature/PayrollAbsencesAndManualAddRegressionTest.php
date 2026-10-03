<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\LoanType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
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

class PayrollAbsencesAndManualAddRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $adminUser;
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
            'email' => 'admin_abs_reg@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Absence Regression Corp',
            'company_code' => 'ABSREG',
            'email' => 'absreg@test.com',
            'status' => '1',
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

    /**
     * Test: Recurring ABSENCES assignment uses EmpDeduction rate.
     */
    public function test_recurring_absences_assignment_with_arbitrary_rate_does_not_override_policy_daily_rate(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-ARBRATE',
            'first_name' => 'Arb',
            'last_name' => 'Rate',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();

        \App\Models\EmpDeduction::create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $absType->id,
            'rate' => 500.00,
            'frequency' => 'per_payroll',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'days_absent' => 2.0,
        ]);
        $draft = $headers->first();

        $absPayOut = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $absType->id)
            ->firstOrFail();

        $this->assertEquals(500.00, (float) $absPayOut->rate);
        $this->assertEquals(1.0, (float) $absPayOut->number_input);
        $this->assertEquals(500.00, (float) ($absPayOut->rate * $absPayOut->number_input));
    }

    /**
     * Test: Manually adding a normal custom deduction without number_input defaults quantity to 1.00.
     */
    public function test_manual_add_custom_deduction_without_number_input_defaults_to_one(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ADD-CUSTOM',
            'first_name' => 'Custom',
            'last_name' => 'Ded',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $customDed = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'CHARITY',
            'name' => 'Charity Contribution',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.payroll-drafts.add-deduction', $draft->id), [
            'deduction_type_id' => $customDed->id,
            'rate' => 250.00,
        ]);
        $response->assertSessionHasNoErrors();

        $addedLine = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $customDed->id)
            ->firstOrFail();

        $this->assertEquals(1.00, (float) $addedLine->number_input);
        $this->assertEquals(250.00, (float) $addedLine->rate);
    }

    /**
     * Test: Manually adding a loan-backed deduction without number_input defaults quantity to 1.00.
     */
    public function test_manual_add_loan_deduction_without_number_input_defaults_to_one(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ADD-LOAN',
            'first_name' => 'Loan',
            'last_name' => 'Ded',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $loanDedType = DeductionType::withoutGlobalScopes()->where('code', 'VALE')->firstOrFail();
        $loanType = LoanType::create([
            'company_id' => $this->company->id,
            'name' => 'Company Cash Advance',
            'deduction_type_id' => $loanDedType->id,
            'is_active' => true,
        ]);

        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 4000.00,
            'current_balance' => 4000.00,
            'monthly_deduction' => 400.00,
            'per_cutoff_amount' => 200.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.payroll-drafts.add-deduction', $draft->id), [
            'deduction_type_id' => $loanDedType->id,
            'loan_id' => $loan->id,
        ]);
        $response->assertSessionHasNoErrors();

        $addedLine = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('loan_id', $loan->id)
            ->firstOrFail();

        $this->assertEquals(1.00, (float) $addedLine->number_input);
        $this->assertEquals(200.00, (float) $addedLine->rate);
    }

    /**
     * Requirement: Custom fixed rate persists with exact amount.
     */
    public function test_custom_absences_rate_with_zero_days_persists_with_amount_zero(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-ZERO-CUSTOM',
            'first_name' => 'Custom',
            'last_name' => 'RateZero',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'use_fixed_amount' => true,
            'days_absent' => 0.00,
            'deductions' => [
                ['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 1200.00],
            ],
        ]);

        $this->assertCount(1, $headers);
        $draft = $headers->first();

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();
        $absPayOut = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $absType->id)->first();

        $this->assertNotNull($absPayOut);
        $this->assertEquals(1.00, (float)$absPayOut->number_input);
        $this->assertEquals(1200.00, (float)$absPayOut->rate);
        $this->assertEquals(1200.00, (float)($absPayOut->number_input * $absPayOut->rate));
    }

    /**
     * Requirement: Later changing days does not alter existing ABSENCES line.
     */
    public function test_changing_days_absent_to_two_days_produces_two_times_custom_rate(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-EDIT-TWO',
            'first_name' => 'Edit',
            'last_name' => 'TwoDays',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$employee->id], [
            'use_fixed_amount' => true,
            'days_absent' => 0.00,
            'deductions' => [
                ['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 1500.00],
            ],
        ])->first();

        $response = $this->actingAs($this->adminUser)->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 10,
            'days_absent' => 2.0,
            'late_hours' => 0,
            'undertime_hours' => 0,
            'undertime_deduction' => 0,
            'overtime_hours' => 0,
        ]);
        $response->assertSessionHasNoErrors();

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();
        $absPayOut = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $absType->id)->first();

        $this->assertNotNull($absPayOut);
        $this->assertEquals(1.00, (float)$absPayOut->number_input);
        $this->assertEquals(1500.00, (float)$absPayOut->rate);
        $this->assertEquals(1500.00, (float)($absPayOut->number_input * $absPayOut->rate));
    }

    /**
     * Requirement: Days absent alone does not auto generate ABSENCES line.
     */
    public function test_auto_absences_with_two_days_and_no_manual_rate_uses_daily_rate(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-POLICY-RATE',
            'first_name' => 'Policy',
            'last_name' => 'RateTest',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$employee->id], [
            'days_absent' => 2.00,
        ])->first();

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();
        $absPayOut = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $absType->id)->first();

        $this->assertNull($absPayOut);
    }

    /**
     * Requirement: Changing days does not overwrite existing custom rate.
     */
    public function test_changing_days_does_not_overwrite_custom_rate(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-PRESERVE-RATE',
            'first_name' => 'Preserve',
            'last_name' => 'Rate',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$employee->id], [
            'use_fixed_amount' => true,
            'days_absent' => 1.00,
            'deductions' => [
                ['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 1800.00],
            ],
        ])->first();

        $response = $this->actingAs($this->adminUser)->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 9,
            'days_absent' => 3.5,
            'late_hours' => 0,
            'undertime_hours' => 0,
            'undertime_deduction' => 0,
            'overtime_hours' => 0,
        ]);
        $response->assertSessionHasNoErrors();

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();
        $absPayOut = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $absType->id)->first();

        $this->assertNotNull($absPayOut);
        $this->assertEquals(1.0, (float)$absPayOut->number_input);
        $this->assertEquals(1800.00, (float)$absPayOut->rate);
        $this->assertEquals(1800.00, (float)($absPayOut->number_input * $absPayOut->rate));
    }

    /**
     * Requirement: No manual rate + 0 days creates no unnecessary ABSENCES line.
     */
    public function test_no_manual_rate_and_zero_days_creates_no_absences_line(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-NO-LINE',
            'first_name' => 'NoLine',
            'last_name' => 'Absence',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$employee->id], [
            'days_absent' => 0.00,
        ])->first();

        $absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();
        $absPayOut = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $absType->id)->first();

        $this->assertNull($absPayOut);
    }
}
