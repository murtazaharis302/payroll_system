<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DefaultIncomeRateDraftTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected IncomeType $overtimeType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User RateTest',
            'email' => 'admin_ratetest@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Rate Test Corp',
            'company_code' => 'RATETEST',
            'email' => 'ratetest@test.com',
            'status' => '1',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->basicType = IncomeType::where('code', 'BASIC')->firstOrFail();
        $this->overtimeType = IncomeType::where('code', 'OVERTIME')->firstOrFail();
    }

    /**
     * Test 1: Configured positive rate in EmpIncome for Basic Pay and Overtime Pay
     * is copied to generated PayIn rate and resulting amount is rate x number_input.
     */
    public function test_positive_configured_emp_income_rate_used_for_basic_and_overtime_pay(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-RATE-01',
            'first_name' => 'Alice',
            'last_name' => 'PositiveRate',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        // Configure Default Income / Recurring Income assignment for BASIC = 50000.00
        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
        ], [
            'rate' => 50000.00,
            'amount' => 50000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // Configure Default Income / Recurring Income assignment for OVERTIME = 350.00
        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->overtimeType->id,
        ], [
            'rate' => 350.00,
            'amount' => 350.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $options = [
            'payroll_inputs' => [
                $employee->id => [
                    'overtime_hours' => 10.0,
                ],
            ],
        ];

        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        // Basic Pay line: rate must be 50000.00, number_input = 0.50 (semi-monthly), amount = 25000.00
        $basicPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->basicType->id)
            ->firstOrFail();

        $this->assertEquals(50000.00, (float) $basicPayIn->rate);
        $this->assertEquals(0.50, (float) $basicPayIn->number_input);
        $this->assertEquals(25000.00, (float) $basicPayIn->amount);

        // Overtime Pay line: rate must be 350.00, number_input = 10.00, amount = 3500.00
        $otPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->overtimeType->id)
            ->firstOrFail();

        $this->assertEquals(350.00, (float) $otPayIn->rate);
        $this->assertEquals(10.00, (float) $otPayIn->number_input);
        $this->assertEquals(3500.00, (float) $otPayIn->amount);
    }

    /**
     * Test 2: Configured 0.00 rate in EmpIncome for Basic Pay and Overtime Pay
     * is copied as 0.00 rate and 0.00 amount without falling back to EmployeeMaster or policy.
     */
    public function test_configured_zero_emp_income_rate_used_without_falling_back_to_employee_master(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-RATE-02',
            'first_name' => 'Bob',
            'last_name' => 'ZeroRate',
            'monthly_rate' => 40000.00,
            'hourly_rate' => 200.00,
            'status' => 'active',
        ]);

        // Configure Default Income / Recurring Income assignment for BASIC = 0.00
        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
        ], [
            'rate' => 0.00,
            'amount' => 0.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // Configure Default Income / Recurring Income assignment for OVERTIME = 0.00
        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->overtimeType->id,
        ], [
            'rate' => 0.00,
            'amount' => 0.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $options = [
            'payroll_inputs' => [
                $employee->id => [
                    'overtime_hours' => 5.0,
                ],
            ],
        ];

        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        // Basic Pay line: rate MUST be 0.00 (NOT falling back to monthly_rate 40000.00)
        $basicPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->basicType->id)
            ->firstOrFail();

        $this->assertEquals(0.00, (float) $basicPayIn->rate);
        $this->assertEquals(0.00, (float) $basicPayIn->amount);

        // Overtime Pay line: rate MUST be 0.00 (NOT falling back to policy OT rate)
        $otPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->overtimeType->id)
            ->firstOrFail();

        $this->assertEquals(0.00, (float) $otPayIn->rate);
        $this->assertEquals(0.00, (float) $otPayIn->amount);
    }

    /**
     * Test 3: When no active EmpIncome assignment exists, draft generation
     * falls back to EmployeeMaster rates and policy overtime rate.
     */
    public function test_fallback_to_employee_master_when_no_emp_income_assignment_exists(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-RATE-03',
            'first_name' => 'Charlie',
            'last_name' => 'NoAssignment',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        // Ensure no EmpIncome assignments exist for BASIC or OVERTIME
        EmpIncome::where('employee_id', $employee->id)->delete();

        $service = new PayrollProcessingService();
        $options = [
            'payroll_inputs' => [
                $employee->id => [
                    'overtime_hours' => 4.0,
                ],
            ],
        ];

        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        // Basic Pay line: rate falls back to EmployeeMaster monthly_rate = 30000.00
        $basicPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->basicType->id)
            ->firstOrFail();

        $this->assertEquals(30000.00, (float) $basicPayIn->rate);
        $this->assertEquals(0.50, (float) $basicPayIn->number_input);
        $this->assertEquals(15000.00, (float) $basicPayIn->amount);

        // Overtime Pay line: rate falls back to policy OT rate
        $otPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->overtimeType->id)
            ->firstOrFail();

        $this->assertGreaterThan(0.00, (float) $otPayIn->rate);
        $this->assertEquals(4.00, (float) $otPayIn->number_input);
        $this->assertEquals(round(4.00 * (float) $otPayIn->rate, 2), (float) $otPayIn->amount);
    }
}
