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

class RecurringIncomeGenerationCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected IncomeType $overtimeType;
    protected IncomeType $allowanceType;
    protected IncomeType $incentiveType;
    protected IncomeType $riceType;
    protected IncomeType $transType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User Rec',
            'email' => 'admin_rec_test@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Completeness Test Corp',
            'company_code' => 'COMPLETETEST',
            'email' => 'completetest@test.com',
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
        $this->allowanceType = IncomeType::where('code', 'ALLOWANCE')->firstOrFail();

        $this->incentiveType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'INCENTIVE_PAY',
            'name' => 'Incentive Pay',
            'is_active' => true,
        ]);

        $this->riceType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'RICE_SUBSIDY',
            'name' => 'Rice Subsidy',
            'is_active' => true,
        ]);

        $this->transType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'TRANS_ALLOWANCE',
            'name' => 'Transportation Allowance',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: An employee with 6 active EmpIncome assignments (BASIC, OVERTIME, and 4 ordinary types)
     * generates exactly 6 unique PayIn rows in an automated draft with 0 overtime hours.
     * OVERTIME must exist with number_input 0.00 and configured rate.
     */
    public function test_automated_draft_contains_all_six_active_emp_income_assignments_with_zero_overtime(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-01',
            'first_name' => 'John',
            'last_name' => 'Recurring',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        // Clear observer auto-assigned incomes so employee has exactly the 6 target active assignments
        EmpIncome::where('employee_id', $employee->id)->delete();

        // 6 Active EmpIncome assignments
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->basicType->id, 'rate' => 35000.00, 'amount' => 35000.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->overtimeType->id, 'rate' => 250.00, 'amount' => 250.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->allowanceType->id, 'rate' => 4000.00, 'amount' => 4000.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->incentiveType->id, 'rate' => 2000.00, 'amount' => 2000.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->riceType->id, 'rate' => 1500.00, 'amount' => 1500.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->transType->id, 'rate' => 1000.00, 'amount' => 1000.00, 'frequency' => 'monthly', 'is_active' => true]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], ['use_fixed_income_amount' => false]);
        $draft = $headers->first();

        $payIns = PayIn::where('pay_id', $draft->id)->get();

        // Exactly 6 PayIn lines created
        $this->assertCount(6, $payIns);

        // Exactly 6 unique income_type_ids with no duplicates
        $uniqueTypeIds = $payIns->pluck('income_type_id')->unique();
        $this->assertCount(6, $uniqueTypeIds);

        // 1. BASIC line: rate 35000, number_input 0.50 (semi-monthly), amount 17500
        $basicPayIn = $payIns->firstWhere('income_type_id', $this->basicType->id);
        $this->assertNotNull($basicPayIn);
        $this->assertEquals(35000.00, (float) $basicPayIn->rate);
        $this->assertEquals(0.50, (float) $basicPayIn->number_input);
        $this->assertEquals(17500.00, (float) $basicPayIn->amount);

        // 2. OVERTIME line: rate 250, number_input 0.00, amount 0.00
        $otPayIn = $payIns->firstWhere('income_type_id', $this->overtimeType->id);
        $this->assertNotNull($otPayIn);
        $this->assertEquals(250.00, (float) $otPayIn->rate);
        $this->assertEquals(0.00, (float) $otPayIn->number_input);
        $this->assertEquals(0.00, (float) $otPayIn->amount);

        // 3. ALLOWANCE line: rate 4000, number_input 0.50, amount 2000
        $allowancePayIn = $payIns->firstWhere('income_type_id', $this->allowanceType->id);
        $this->assertNotNull($allowancePayIn);
        $this->assertEquals(4000.00, (float) $allowancePayIn->rate);
        $this->assertEquals(0.50, (float) $allowancePayIn->number_input);
        $this->assertEquals(2000.00, (float) $allowancePayIn->amount);

        // 4. INCENTIVE line: rate 2000, number_input 0.50, amount 1000
        $incentivePayIn = $payIns->firstWhere('income_type_id', $this->incentiveType->id);
        $this->assertNotNull($incentivePayIn);
        $this->assertEquals(2000.00, (float) $incentivePayIn->rate);
        $this->assertEquals(0.50, (float) $incentivePayIn->number_input);
        $this->assertEquals(1000.00, (float) $incentivePayIn->amount);

        // 5. RICE line: rate 1500, number_input 0.50, amount 750
        $ricePayIn = $payIns->firstWhere('income_type_id', $this->riceType->id);
        $this->assertNotNull($ricePayIn);
        $this->assertEquals(1500.00, (float) $ricePayIn->rate);
        $this->assertEquals(0.50, (float) $ricePayIn->number_input);
        $this->assertEquals(750.00, (float) $ricePayIn->amount);

        // 6. TRANS line: rate 1000, number_input 0.50, amount 500
        $transPayIn = $payIns->firstWhere('income_type_id', $this->transType->id);
        $this->assertNotNull($transPayIn);
        $this->assertEquals(1000.00, (float) $transPayIn->rate);
        $this->assertEquals(0.50, (float) $transPayIn->number_input);
        $this->assertEquals(500.00, (float) $transPayIn->amount);
    }

    /**
     * Test 2: BASIC and OVERTIME are not duplicated when generated with positive overtime hours.
     */
    public function test_basic_and_overtime_are_not_duplicated_when_overtime_hours_are_positive(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-02',
            'first_name' => 'Jane',
            'last_name' => 'OvertimePos',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->basicType->id, 'rate' => 30000.00, 'amount' => 30000.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->overtimeType->id, 'rate' => 200.00, 'amount' => 200.00, 'frequency' => 'monthly', 'is_active' => true]);

        $service = new PayrollProcessingService();
        $options = [
            'use_fixed_income_amount' => false,
            'payroll_inputs' => [
                $employee->id => [
                    'overtime_hours' => 5.0,
                ],
            ],
        ];

        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $payIns = PayIn::where('pay_id', $draft->id)->get();

        $basicPayIns = $payIns->where('income_type_id', $this->basicType->id);
        $this->assertCount(1, $basicPayIns);

        $otPayIns = $payIns->where('income_type_id', $this->overtimeType->id);
        $this->assertCount(1, $otPayIns);

        $otPayIn = $otPayIns->first();
        $this->assertEquals(200.00, (float) $otPayIn->rate);
        $this->assertEquals(5.00, (float) $otPayIn->number_input);
        $this->assertEquals(1000.00, (float) $otPayIn->amount);
    }

    /**
     * Test 3: Duplicate active EmpIncome records for the same income type produce only one PayIn row.
     */
    public function test_duplicate_active_emp_income_records_produce_single_payin_row(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-03',
            'first_name' => 'Dup',
            'last_name' => 'Tester',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        // Clear auto-assigned incomes to specifically test duplicate allowance insertion
        EmpIncome::where('employee_id', $employee->id)->delete();

        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->allowanceType->id, 'rate' => 1000.00, 'amount' => 1000.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->allowanceType->id, 'rate' => 1000.00, 'amount' => 1000.00, 'frequency' => 'monthly', 'is_active' => true]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], ['use_fixed_income_amount' => false]);
        $draft = $headers->first();

        $allowancePayIns = PayIn::where('pay_id', $draft->id)->where('income_type_id', $this->allowanceType->id)->get();
        $this->assertCount(1, $allowancePayIns);
    }

    /**
     * Test 4: Selective exclusion (display = false) excludes BASIC, OVERTIME, or ordinary recurring incomes even if active EmpIncome exists.
     */
    public function test_selective_display_false_excludes_income_types_even_with_active_emp_income(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-04',
            'first_name' => 'Excluded',
            'last_name' => 'Types',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->basicType->id, 'rate' => 25000.00, 'amount' => 25000.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->overtimeType->id, 'rate' => 200.00, 'amount' => 200.00, 'frequency' => 'monthly', 'is_active' => true]);
        EmpIncome::create(['employee_id' => $employee->id, 'income_type_id' => $this->allowanceType->id, 'rate' => 1500.00, 'amount' => 1500.00, 'frequency' => 'monthly', 'is_active' => true]);

        $service = new PayrollProcessingService();
        $options = [
            'use_fixed_income_amount' => false,
            'incomes' => [
                ['income_type' => 'BASIC', 'display' => 1],
                ['income_type' => 'OVERTIME', 'display' => 0], // Excluded
                ['income_type' => 'ALLOWANCE', 'display' => 0], // Excluded
            ],
        ];

        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $payIns = PayIn::where('pay_id', $draft->id)->get();

        $this->assertTrue($payIns->contains('income_type_id', $this->basicType->id));
        $this->assertFalse($payIns->contains('income_type_id', $this->overtimeType->id));
        $this->assertFalse($payIns->contains('income_type_id', $this->allowanceType->id));
    }

    /**
     * Test 5: Regression - Monthly BASIC + EmpIncome rate override without explicit zero input retains 0.50 cutoff quantity.
     */
    public function test_monthly_basic_with_emp_income_override_retains_half_cutoff_quantity(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-05',
            'first_name' => 'Monthly',
            'last_name' => 'Override',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 40000.00,
            'amount' => 40000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], ['use_fixed_income_amount' => false]);
        $draft = $headers->first();

        $basicPayIn = PayIn::where('pay_id', $draft->id)->where('income_type_id', $this->basicType->id)->firstOrFail();

        $this->assertEquals(40000.00, (float) $basicPayIn->rate);
        $this->assertEquals(0.50, (float) $basicPayIn->number_input);
        $this->assertEquals(20000.00, (float) $basicPayIn->amount);
    }
}
