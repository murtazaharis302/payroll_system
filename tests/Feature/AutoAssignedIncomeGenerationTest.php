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

class AutoAssignedIncomeGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period1;
    protected PayrollPeriod $period2;
    protected IncomeType $allowanceType;
    protected IncomeType $customType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_auto_inc@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Auto Income Corp',
            'company_code' => 'AUTOINC',
            'email' => 'autoinc@test.com',
            'status' => '1',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $this->period1 = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->period2 = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 2nd Cutoff',
            'period_start' => '2026-08-16',
            'period_end' => '2026-08-31',
            'pay_date' => '2026-09-01',
            'status' => 'open',
        ]);

        $this->allowanceType = IncomeType::where('code', 'ALLOWANCE')->firstOrFail();

        $this->customType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'PROJECT_BONUS',
            'name' => 'Project Incentive Bonus',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Changing an employee's recurring-income rate in Employee Record Management
     * changes the rate used by subsequently generated payroll drafts in Automatic Mode.
     */
    public function test_updating_emp_income_rate_updates_rate_in_subsequent_automatic_drafts(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-AUTO-01',
            'first_name' => 'Alice',
            'last_name' => 'AutoRate',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        // 1. Initial configuration in Employee Record Management: Allowance rate = 100.00
        $empInc = EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 100.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();

        // Draft 1 (Cutoff 1) generated in Automatic mode
        $headers1 = $service->generateDraft($this->period1, [$employee->id], ['use_fixed_income_amount' => false]);
        $draft1 = $headers1->first();

        $payIn1 = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft1->id)
            ->where('income_type_id', $this->allowanceType->id)
            ->firstOrFail();

        $this->assertEquals(100.00, (float) $payIn1->rate);
        $this->assertEquals(0.50, (float) $payIn1->number_input); // Semi-monthly monthly factor
        $this->assertEquals(50.00, (float) $payIn1->amount);

        // 2. Update rate in Employee Record Management to 250.00
        $empInc->update(['rate' => 250.00, 'amount' => 250.00]);

        // Draft 2 (Cutoff 2) generated in Automatic mode
        $headers2 = $service->generateDraft($this->period2, [$employee->id], ['use_fixed_income_amount' => false]);
        $draft2 = $headers2->first();

        $payIn2 = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft2->id)
            ->where('income_type_id', $this->allowanceType->id)
            ->firstOrFail();

        // Verifies the rate pulled is 250.00 (from active EmpIncome.rate)
        $this->assertEquals(250.00, (float) $payIn2->rate);
        $this->assertEquals(0.50, (float) $payIn2->number_input);
        $this->assertEquals(125.00, (float) $payIn2->amount);
    }

    /**
     * Test 2: Manual / Fixed mode also uses the updated EmpIncome.rate as single source of truth for Rate.
     */
    public function test_updating_emp_income_rate_updates_rate_in_subsequent_manual_drafts(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-AUTO-02',
            'first_name' => 'Charlie',
            'last_name' => 'ManualRate',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $empInc = EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->customType->id,
        ], [
            'rate' => 200.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();

        // 1. Initial rate 200.00, pre-draft quantity input = 3
        $options1 = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'PROJECT_BONUS', 'display' => 1, 'amount' => 3],
            ],
        ];

        $headers1 = $service->generateDraft($this->period1, [$employee->id], $options1);
        $draft1 = $headers1->first();

        $payIn1 = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft1->id)
            ->where('income_type_id', $this->customType->id)
            ->firstOrFail();

        $this->assertEquals(200.00, (float) $payIn1->rate);
        $this->assertEquals(3.00, (float) $payIn1->number_input);
        $this->assertEquals(600.00, (float) $payIn1->amount);

        // 2. Update EmpIncome.rate in Employee Record Management to 350.00
        $empInc->update(['rate' => 350.00, 'amount' => 350.00]);

        $options2 = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'PROJECT_BONUS', 'display' => 1, 'amount' => 4],
            ],
        ];

        $headers2 = $service->generateDraft($this->period2, [$employee->id], $options2);
        $draft2 = $headers2->first();

        $payIn2 = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft2->id)
            ->where('income_type_id', $this->customType->id)
            ->firstOrFail();

        $this->assertEquals(350.00, (float) $payIn2->rate);
        $this->assertEquals(4.00, (float) $payIn2->number_input);
        $this->assertEquals(1400.00, (float) $payIn2->amount);
    }

    /**
     * Test 3: Unrelated base salary rate is NOT substituted for custom/additional incomes.
     */
    public function test_unrelated_base_salary_rate_is_not_substituted_for_additional_income(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-AUTO-03',
            'first_name' => 'David',
            'last_name' => 'SalaryNoSub',
            'monthly_rate' => 50000.00,
            'daily_rate' => 2000.00,
            'hourly_rate' => 250.00,
            'status' => 'active',
        ]);

        // Additional income assigned with rate 80.00
        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 80.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period1, [$employee->id], ['use_fixed_income_amount' => false]);
        $draft = $headers->first();

        $payIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->allowanceType->id)
            ->firstOrFail();

        // Rate MUST be 80.00, NOT monthly_rate (50000), daily_rate (2000), or hourly_rate (250)
        $this->assertEquals(80.00, (float) $payIn->rate);
        $this->assertNotEquals(50000.00, (float) $payIn->rate);
        $this->assertNotEquals(2000.00, (float) $payIn->rate);
        $this->assertNotEquals(250.00, (float) $payIn->rate);
    }

    /**
     * Test 4: Setting EmpIncome.rate to 0 or inactive flags review and skips line in Automatic Mode.
     */
    public function test_zero_rate_emp_income_flags_review_in_automatic_mode(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-AUTO-04',
            'first_name' => 'Eve',
            'last_name' => 'ZeroRate',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        // Assign EmpIncome with rate 0.00
        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->customType->id,
        ], [
            'rate' => 0.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period1, [$employee->id], ['use_fixed_income_amount' => false]);
        $draft = $headers->first();

        $payIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->customType->id)
            ->first();

        // Zero-rate line is skipped
        $this->assertNull($payIn);

        // Surfaced configuration review flag
        $this->assertTrue((bool) $draft->requires_review);
        $this->assertStringContainsString('Missing active income configuration for Project Incentive Bonus', (string) $draft->review_reason);
    }
}
