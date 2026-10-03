<?php

namespace Tests\Feature;

use App\Exceptions\PayrollGenerationSetupException;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpDeduction;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\LoanType;
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

class PayrollDeductionPreflightValidationTest extends TestCase
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
            'name' => 'Admin Preflight Test',
            'email' => 'admin_preflight@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Preflight Test Corp',
            'company_code' => 'PREFLIGHT',
            'email' => 'preflight@test.com',
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
            'period_name' => 'January 2026 1st Cutoff',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'cutoff_number' => 1,
            'frequency' => 'semi_monthly',
            'status' => 'open',
        ]);

        session(['active_company_id' => $this->company->id]);
    }

    protected function createEmployee(string $code, string $first, string $last, float $rate = 30000.00): EmployeeMaster
    {
        return EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => $code,
            'first_name' => $first,
            'last_name' => $last,
            'monthly_rate' => $rate,
            'status' => 'active',
        ]);
    }

    /**
     * Requirement: Multiple employees with missing recurring deductions are reported together
     * in a single PayrollGenerationSetupException with employee identities and deduction names.
     */
    public function test_multiple_employee_deduction_setup_failures_reported_together(): void
    {
        $emp1 = $this->createEmployee('EMP001', 'Alice', 'Johnson', 30000.00);
        $emp2 = $this->createEmployee('EMP002', 'Bob', 'Smith', 32000.00);

        DeductionType::create(['company_id' => $this->company->id, 'code' => 'UNIFORM', 'name' => 'Uniform Deduction', 'is_active' => true]);
        DeductionType::create(['company_id' => $this->company->id, 'code' => 'ID_BADGE', 'name' => 'ID Badge Fee', 'is_active' => true]);

        $service = new PayrollProcessingService();

        try {
            $service->generateDraft($this->period, [$emp1->id, $emp2->id], [
                'use_fixed_amount' => true,
                'deductions' => [
                    ['deduction_type' => 'UNIFORM', 'display' => true],
                    ['deduction_type' => 'ID_BADGE', 'display' => true],
                ],
            ]);
            $this->fail('Expected PayrollGenerationSetupException was not thrown.');
        } catch (PayrollGenerationSetupException $e) {
            $errors = $e->getErrors();
            $this->assertCount(4, $errors);

            $this->assertTrue(collect($errors)->contains(fn($err) => str_contains($err, 'EMP001 Alice Johnson') && str_contains($err, 'UNIFORM: no active Recurring Deduction assignment/rate')));
            $this->assertTrue(collect($errors)->contains(fn($err) => str_contains($err, 'EMP002 Bob Smith') && str_contains($err, 'UNIFORM: no active Recurring Deduction assignment/rate')));
            $this->assertTrue(collect($errors)->contains(fn($err) => str_contains($err, 'EMP001 Alice Johnson') && str_contains($err, 'ID_BADGE: no active Recurring Deduction assignment/rate')));
            $this->assertTrue(collect($errors)->contains(fn($err) => str_contains($err, 'EMP002 Bob Smith') && str_contains($err, 'ID_BADGE: no active Recurring Deduction assignment/rate')));

            $this->assertStringContainsString('Payroll generation blocked: 4 setup issues found.', $e->getMessage());
        }
    }

    /**
     * Requirement: If there are no active loans for a user in the batch being generated,
     * they are simply skipped; no error is shown for a missing loan.
     */
    public function test_loan_deductions_selected_are_skipped_without_error_for_employees_without_active_loans(): void
    {
        $emp = $this->createEmployee('EMP-NO-LOAN', 'NoLoan', 'User', 25000.00);
        $service = new PayrollProcessingService();

        $headers = $service->generateDraft($this->period, [$emp->id], [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'SSS_LOAN', 'display' => true],
                ['deduction_type' => 'VALE', 'display' => true],
                ['deduction_type' => 'PAGIBIG_LOAN', 'display' => true],
            ],
        ]);

        $this->assertCount(1, $headers);
        $loanPayOuts = $headers->first()->payOuts()->whereHas('deductionType', function ($q) {
            $q->whereIn('code', ['SSS_LOAN', 'VALE', 'PAGIBIG_LOAN']);
        })->get();
        $this->assertCount(0, $loanPayOuts);
    }

    /**
     * Requirement: Statutory deductions (SSS, PhilHealth, Pag-IBIG, Tax)
     * do not require EmpDeduction assignments and do not trigger false preflight errors.
     */
    public function test_statutory_and_absences_deductions_do_not_trigger_false_errors(): void
    {
        $emp = $this->createEmployee('EMP-STAT-TEST', 'Charlie', 'Brown', 26000.00);
        $service = new PayrollProcessingService();

        $headers = $service->generateDraft($this->period, [$emp->id], [
            'use_fixed_amount' => true,
            'days_absent' => 1.0,
            'deductions' => [
                ['deduction_type' => 'SSS', 'display' => true],
                ['deduction_type' => 'PHILHEALTH', 'display' => true],
                ['deduction_type' => 'PAGIBIG', 'display' => true],
                ['deduction_type' => 'TAX', 'display' => true],
            ],
        ]);

        $this->assertCount(1, $headers);
        $this->assertEquals(26000.00, (float)$headers->first()->employee->monthly_rate);
    }

    /**
     * Requirement: Deductions with display=false are ignored and do not trigger preflight errors.
     */
    public function test_unselected_or_hidden_deductions_are_ignored(): void
    {
        $emp = $this->createEmployee('EMP-HIDDEN-TEST', 'David', 'Clark', 28000.00);
        DeductionType::create(['company_id' => $this->company->id, 'code' => 'GYM_MEMBERSHIP', 'name' => 'Gym Membership', 'is_active' => true]);

        $service = new PayrollProcessingService();

        $headers = $service->generateDraft($this->period, [$emp->id], [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'GYM_MEMBERSHIP', 'display' => false],
                ['deduction_type' => 'SSS_LOAN', 'display' => false],
            ],
        ]);

        $this->assertCount(1, $headers);
    }

    /**
     * Requirement: Valid active recurring deductions and valid active loans pass preflight validation,
     * and generation succeeds once setup problems are corrected.
     */
    public function test_valid_recurring_deduction_and_active_loan_pass_preflight(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-VALID-ALL',
            'first_name' => 'Emma',
            'last_name' => 'Watson',
            'monthly_rate' => 35000.00,
            'status' => 'active',
        ]);

        $customDed = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'LOCKER_FEE',
            'name' => 'Locker Fee',
            'is_active' => true,
        ]);

        EmpDeduction::create([
            'employee_id' => $emp->id,
            'deduction_type_id' => $customDed->id,
            'rate' => 150.00,
            'frequency' => 'per_payroll',
            'is_active' => true,
        ]);

        $valeDedType = DeductionType::withoutGlobalScopes()->where('code', 'VALE')->firstOrFail();
        $loanType = LoanType::create([
            'company_id' => $this->company->id,
            'name' => 'Emergency Cash Advance',
            'deduction_type_id' => $valeDedType->id,
            'is_active' => true,
        ]);

        EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 3000.00,
            'monthly_deduction' => 1000.00,
            'per_cutoff_amount' => 500.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        $headers = $service->generateDraft($this->period, [$emp->id], [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'LOCKER_FEE', 'display' => true],
                ['deduction_type' => 'VALE', 'display' => true],
            ],
        ]);

        $this->assertCount(1, $headers);
        $draft = $headers->first();

        // Check PayOut lines were created properly
        $lockerPayOut = $draft->payOuts()->where('deduction_type_id', $customDed->id)->first();
        $this->assertNotNull($lockerPayOut);
        $this->assertEquals(150.00, (float)$lockerPayOut->rate);

        $valePayOut = $draft->payOuts()->where('deduction_type_id', $valeDedType->id)->first();
        $this->assertNotNull($valePayOut);
        $this->assertEquals(500.00, (float)$valePayOut->rate);
    }

    /**
     * Requirement: Controller endpoint handles PayrollGenerationSetupException and redirects back
     * with statusDanger and generation_errors session variables.
     */
    public function test_controller_generation_endpoint_catches_setup_exception_and_flashes_errors(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-HTTP-ERR',
            'first_name' => 'Frank',
            'last_name' => 'Sinatra',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        $customDed = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'PARKING',
            'name' => 'Parking Fee',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$emp->id],
            'use_fixed_amount' => 1,
            'deductions' => [
                ['deduction_type' => 'PARKING', 'display' => 1],
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('statusDanger');
        $response->assertSessionHas('generation_errors');

        $errors = session('generation_errors');
        $this->assertIsArray($errors);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('EMP-HTTP-ERR Frank Sinatra — PARKING: no active Recurring Deduction assignment/rate', $errors[0]);
    }

    /**
     * Requirement: If deduction options configure an explicit fixed amount > 0,
     * preflight validation passes even if the employee has no EmpDeduction assignment.
     */
    public function test_fixed_amount_in_deduction_options_passes_preflight_without_emp_deduction(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-OPT-AMT',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $customDed = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'COOP_SAVINGS',
            'name' => 'Coop Savings',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();

        $headers = $service->generateDraft($this->period, [$emp->id], [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'COOP_SAVINGS', 'display' => true, 'amount' => 500.00],
            ],
        ]);

        $this->assertCount(1, $headers);
        $payOut = $headers->first()->payOuts()->where('deduction_type_id', $customDed->id)->first();
        $this->assertNotNull($payOut);
        $this->assertEquals(500.00, (float)$payOut->rate);
    }

    /**
     * Requirement: Active loan with unmapped LoanType is reported in preflight validation with employee identity.
     */
    public function test_unmapped_active_loan_type_reported_in_preflight(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-UNMAPPED',
            'first_name' => 'Henry',
            'last_name' => 'Ford',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $unmappedLoanType = LoanType::withoutEvents(function () {
            return LoanType::create([
                'company_id' => $this->company->id,
                'name' => 'Custom Unmapped Loan',
                'deduction_type_id' => null,
                'is_active' => true,
            ]);
        });

        EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'loan_type_id' => $unmappedLoanType->id,
            'principal_amount' => 1000.00,
            'current_balance' => 1000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        try {
            $service->generateDraft($this->period, [$emp->id], [
                'use_fixed_amount' => false,
            ]);
            $this->fail('Expected PayrollGenerationSetupException was not thrown.');
        } catch (PayrollGenerationSetupException $e) {
            $errors = $e->getErrors();
            $this->assertTrue(collect($errors)->contains(fn($err) => str_contains($err, 'EMP-UNMAPPED Henry Ford') && str_contains($err, "Loan 'Custom Unmapped Loan': not mapped to a Deduction Type")));
        }
    }
}
