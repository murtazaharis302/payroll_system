<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeparateDaysWorkedAndIncomeNumberInputTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected IncomeType $allowanceType;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $this->admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $this->company = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Separation Test Corp',
            'company_code' => 'SEP-01',
            'email' => 'sep@corp.com',
            'status' => 1,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->department = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'code' => 'DEP_SEP',
            'name' => 'Separation Dept',
            'is_active' => true,
        ]);

        $this->basicType = IncomeType::where('code', 'BASIC')->firstOrFail();
        $this->allowanceType = IncomeType::where('code', 'ALLOWANCE')->firstOrFail();
    }

    /**
     * Test 1: Attendance exists -> Days Worked is automatically populated from attendance,
     * HR can override it, and Basic Pay recalculates using the overridden value.
     */
    public function test_attendance_exists_days_worked_populated_and_can_be_overridden(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-DAILY-500',
            'first_name' => 'Daily',
            'last_name' => 'Worker',
            'daily_rate' => 500.00,
            'status' => 'active',
        ]);

        // Create 5 days of attendance logs
        for ($i = 1; $i <= 5; $i++) {
            $date = sprintf('2026-08-%02d', $i);
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'biometric_id' => 'BIO-' . $employee->id,
                'log_date' => $date,
                'log_time' => '08:00:00',
                'log_datetime' => $date . ' 08:00:00',
                'log_type' => 'in',
            ]);
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'biometric_id' => 'BIO-' . $employee->id,
                'log_date' => $date,
                'log_time' => '17:00:00',
                'log_datetime' => $date . ' 17:00:00',
                'log_type' => 'out',
            ]);
        }

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        // 1. Automatically populated from attendance (5 days)
        $this->assertEquals(5.00, (float) $draft->days_worked);
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->basicType->id)->first();
        $this->assertEquals(5.00, (float) $basicPayIn->number_input);
        $this->assertEquals(2500.00, (float) $basicPayIn->amount);

        // 2. HR override via update request to 8 days worked
        $this->actingAs($this->admin);
        $response = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 8,
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 8],
            ],
        ]);

        $response->assertRedirect();
        $draft->refresh();
        $basicPayIn->refresh();

        $this->assertEquals(8.00, (float) $draft->days_worked);
        $this->assertEquals(8.00, (float) $basicPayIn->number_input);
        $this->assertEquals(4000.00, (float) $basicPayIn->amount);
    }

    /**
     * Test 2: No attendance exists -> HR can manually enter Days Worked; Basic Pay uses it;
     * Income Number Input is never used as fallback for Days Worked.
     */
    public function test_no_attendance_manual_days_worked_used_and_number_input_not_fallback(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-DAILY-600',
            'first_name' => 'NoAtt',
            'last_name' => 'Worker',
            'daily_rate' => 600.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 10,
                ],
            ],
        ]);
        $draft = $headers->first();

        $this->assertEquals(10.00, (float) $draft->days_worked);
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->basicType->id)->first();
        $this->assertEquals(10.00, (float) $basicPayIn->number_input);
        $this->assertEquals(6000.00, (float) $basicPayIn->amount);

        // Verify PayHeader accessor does not fall back to PayIn number_input if days_worked is null in DB
        $testHeader = new PayHeader();
        $testHeader->setRawAttributes(['days_worked' => null]);
        $this->assertEquals(0.00, (float) $testHeader->days_worked);
    }

    /**
     * Test 3: Income Number Input differs from Days Worked (e.g., Days Worked = 10, Allowance Number Input = 2).
     * Saving/recalculating preserves both values independently.
     */
    public function test_income_number_input_differs_from_days_worked_and_both_preserved(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-IND-100',
            'first_name' => 'Independent',
            'last_name' => 'Inputs',
            'daily_rate' => 400.00,
            'status' => 'active',
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 150.00,
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 10,
                ],
            ],
        ]);
        $draft = $headers->first();

        $allowancePayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->allowanceType->id)->first();
        $this->assertNotNull($allowancePayIn);

        // Edit Allowance number_input to 2 via draft update while Days Worked = 10
        $this->actingAs($this->admin);
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->basicType->id)->first();

        $response = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 10,
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 10],
                ['id' => $allowancePayIn->id, 'number_input' => 2],
            ],
        ]);
        $response->assertRedirect();

        $draft->refresh();
        $allowancePayIn->refresh();

        $this->assertEquals(10.00, (float) $draft->days_worked);
        $this->assertEquals(2.00, (float) $allowancePayIn->number_input);
        $this->assertEquals(300.00, (float) $allowancePayIn->amount);
    }

    /**
     * Test 4: Recurring income initialization does not copy Days Worked (even for daily frequency recurring income).
     */
    public function test_recurring_income_initialization_does_not_copy_days_worked(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-REC-01',
            'first_name' => 'Recurring',
            'last_name' => 'IncomeTest',
            'daily_rate' => 500.00,
            'status' => 'active',
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 200.00,
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 12,
                ],
            ],
        ]);
        $draft = $headers->first();

        $allowancePayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->allowanceType->id)->first();
        $this->assertNotNull($allowancePayIn);
        // Recurring income number_input initializes independently to 1.00, NOT copying days_worked = 12
        $this->assertEquals(1.00, (float) $allowancePayIn->number_input);
        $this->assertEquals(200.00, (float) $allowancePayIn->amount);
    }

    /**
     * Test 5: Changing an income Number Input does not alter Days Worked in pay_headers.
     */
    public function test_changing_income_number_input_does_not_alter_days_worked(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-ALT-01',
            'first_name' => 'Alter',
            'last_name' => 'Test',
            'daily_rate' => 450.00,
            'status' => 'active',
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 100.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 11,
                ],
            ],
        ]);
        $draft = $headers->first();

        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->basicType->id)->first();
        $allowancePayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->allowanceType->id)->first();

        $this->actingAs($this->admin);
        $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 11,
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 11],
                ['id' => $allowancePayIn->id, 'number_input' => 5], // changed allowance number input to 5
            ],
        ]);

        $draft->refresh();
        $allowancePayIn->refresh();

        $this->assertEquals(11.00, (float) $draft->days_worked);
        $this->assertEquals(5.00, (float) $allowancePayIn->number_input);
        $this->assertEquals(500.00, (float) $allowancePayIn->amount);
    }

    /**
     * Test 6: Changing Days Worked does not rewrite unrelated income Number Inputs in pay_ins.
     */
    public function test_changing_days_worked_does_not_rewrite_unrelated_income_number_inputs(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-ALT-02',
            'first_name' => 'DaysChange',
            'last_name' => 'Test',
            'daily_rate' => 450.00,
            'status' => 'active',
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 100.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 10,
                ],
            ],
        ]);
        $draft = $headers->first();

        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->basicType->id)->first();
        $allowancePayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->allowanceType->id)->first();

        // Update Days Worked from 10 to 14, keeping allowance number_input as 3
        $this->actingAs($this->admin);
        $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 14,
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 14],
                ['id' => $allowancePayIn->id, 'number_input' => 3],
            ],
        ]);

        $draft->refresh();
        $basicPayIn->refresh();
        $allowancePayIn->refresh();

        $this->assertEquals(14.00, (float) $draft->days_worked);
        $this->assertEquals(14.00, (float) $basicPayIn->number_input);
        $this->assertEquals(3.00, (float) $allowancePayIn->number_input);
    }

    /**
     * Test 7: Monthly/hourly employee calculations function without regression.
     */
    public function test_monthly_and_hourly_employee_payroll_generation(): void
    {
        $monthlyEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-MONTHLY-20K',
            'first_name' => 'Monthly',
            'last_name' => 'Worker',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $hourlyEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-HOURLY-100',
            'first_name' => 'Hourly',
            'last_name' => 'Worker',
            'hourly_rate' => 100.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$monthlyEmp->id, $hourlyEmp->id]);

        $monthlyDraft = $headers->firstWhere('employee_id', $monthlyEmp->id);
        $hourlyDraft = $headers->firstWhere('employee_id', $hourlyEmp->id);

        $this->assertNotNull($monthlyDraft);
        $this->assertNotNull($hourlyDraft);

        $monthlyBasic = PayIn::withoutGlobalScopes()->where('pay_id', $monthlyDraft->id)->where('income_type_id', $this->basicType->id)->first();
        $this->assertEquals(10000.00, (float) $monthlyBasic->amount); // semi-monthly factor 2.0 = 10,000

        $hourlyBasic = PayIn::withoutGlobalScopes()->where('pay_id', $hourlyDraft->id)->where('income_type_id', $this->basicType->id)->first();
        $this->assertEquals(0.00, (float) $hourlyBasic->amount); // 0 hours worked without logs
    }

    /**
     * Test 8: Negative net payroll can save as draft, but blocks submission for approval.
     */
    public function test_negative_net_payroll_can_save_as_draft_but_blocks_submit_for_approval(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-NEG-01',
            'first_name' => 'Negative',
            'last_name' => 'NetUser',
            'daily_rate' => 300.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 2,
                ],
            ],
        ]);
        $draft = $headers->first();
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $this->basicType->id)->first();

        $dedType = DeductionType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other', 'type' => 'deduction', 'is_active' => true]);
        \App\Models\PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $dedType->id, 'number_input' => 1.00, 'rate' => 2000.00]);

        $this->actingAs($this->admin);

        // 1. Saving as draft (submit_after_save not set or 0) MUST succeed
        $responseSave = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 2,
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 2],
            ],
        ]);
        $responseSave->assertRedirect();
        $draft->refresh();
        $this->assertLessThan(0, (float) $draft->net_pay);
        $this->assertEquals('draft', $draft->status);

        // 2. Submitting for approval MUST be blocked and return error message
        $responseSubmit = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 2,
            'submit_after_save' => '1',
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 2],
            ],
        ]);
        $responseSubmit->assertSessionHas('statusDanger');
        $draft->refresh();
        $this->assertEquals('draft', $draft->status);
    }

    /**
     * Test 9: Basic Pay number_input selector (number_input = 1 => Monthly Pay, number_input > 1 => Daily Pay daily_rate * number_input).
     */
    public function test_basic_pay_number_input_selector_monthly_and_daily_modes(): void
    {
        $alice = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-ALICE-SEL',
            'first_name' => 'Alice',
            'last_name' => 'Selector',
            'monthly_rate' => 20000.00,
            'daily_rate' => 1200.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        // 1. Submit basic_number_input = 1 with days_worked = 14: treats as Monthly Pay (cutoff salary ₱10,000)
        $headersMonthly = $service->generateDraft($this->period, [$alice->id], [
            'payroll_inputs' => [
                $alice->id => [
                    'days_worked' => 14,
                    'basic_number_input' => 1,
                ],
            ],
        ]);
        $draftMonthly = $headersMonthly->first();
        $this->assertEquals(14.00, (float) $draftMonthly->days_worked);
        $basicMonthly = PayIn::withoutGlobalScopes()->where('pay_id', $draftMonthly->id)->where('income_type_id', $this->basicType->id)->first();
        $this->assertEquals(10000.00, (float) $basicMonthly->amount);

        // 2. Submit basic_number_input = 21 with days_worked = 14: treats as Daily Pay (21 x ₱1,200 = ₱25,200)
        $headersDaily = $service->generateDraft($this->period, [$alice->id], [
            'payroll_inputs' => [
                $alice->id => [
                    'days_worked' => 14,
                    'basic_number_input' => 21,
                ],
            ],
        ]);
        $draftDaily = $headersDaily->first();
        $this->assertEquals(14.00, (float) $draftDaily->days_worked);
        $basicDaily = PayIn::withoutGlobalScopes()->where('pay_id', $draftDaily->id)->where('income_type_id', $this->basicType->id)->first();
        $this->assertEquals(21.00, (float) $basicDaily->number_input);
        $this->assertEquals(1200.00, (float) $basicDaily->rate);
        $this->assertEquals(25200.00, (float) $basicDaily->amount);
    }
}
