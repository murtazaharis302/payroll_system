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

class ManualIncomeRateNumberInputMappingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
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
            'email' => 'admin_mapping@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Mapping Test Corp',
            'company_code' => 'MAPCORP',
            'email' => 'mapcorp@test.com',
            'status' => '1',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'DEV',
            'name' => 'Development',
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

        $this->allowanceType = IncomeType::where('code', 'ALLOWANCE')->firstOrFail();

        $this->customType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'HAZARD_PAY',
            'name' => 'Hazard Pay',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Manual value 3 + employee allowance rate 30 => number_input=3, rate=30, total 90.
     */
    public function test_manual_value_3_and_allowance_rate_30_produces_number_input_3_rate_30_total_90(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-MAP-01',
            'first_name' => 'John',
            'last_name' => 'AllowanceWorker',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 30.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $options = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'ALLOWANCE', 'display' => 1, 'amount' => 3],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->allowanceType->id)
            ->firstOrFail();

        $this->assertEquals(3.00, (float) $payIn->number_input);
        $this->assertEquals(30.00, (float) $payIn->rate);
        $this->assertEquals(90.00, (float) $payIn->amount);
    }

    /**
     * Test 2: Custom income behaves identically (manual value 3 + custom rate 50 => number_input=3, rate=50, total 150).
     */
    public function test_custom_income_behaves_identically(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-MAP-02',
            'first_name' => 'Jane',
            'last_name' => 'CustomWorker',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->customType->id,
        ], [
            'rate' => 50.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $options = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'HAZARD_PAY', 'display' => 1, 'amount' => 3],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->customType->id)
            ->firstOrFail();

        $this->assertEquals(3.00, (float) $payIn->number_input);
        $this->assertEquals(50.00, (float) $payIn->rate);
        $this->assertEquals(150.00, (float) $payIn->amount);
    }

    /**
     * Test 3: Pre-draft input can never change or overwrite the configured employee rate.
     */
    public function test_pre_draft_input_can_never_change_configured_employee_rate(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-MAP-03',
            'first_name' => 'Bob',
            'last_name' => 'ProtectedRate',
            'monthly_rate' => 18000.00,
            'status' => 'active',
        ]);

        $empIncome = EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 30.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // Submit pre-draft input value of 5
        $options = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'ALLOWANCE', 'display' => 1, 'amount' => 5],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        // Verify EmpIncome record in database was untouched
        $empIncome->refresh();
        $this->assertEquals(30.00, (float) $empIncome->rate);

        // Verify PayIn line has rate 30.00 and number_input 5.00
        $draft = $headers->first();
        $payIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->allowanceType->id)
            ->firstOrFail();

        $this->assertEquals(30.00, (float) $payIn->rate);
        $this->assertEquals(5.00, (float) $payIn->number_input);
        $this->assertEquals(150.00, (float) $payIn->amount);
    }

    /**
     * Test 4: Selected additional income without an active EmpIncome record does not invent a rate
     * and surfaces missing configuration appropriately.
     */
    public function test_missing_emp_income_does_not_invent_rate_and_surfaces_configuration_issue(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-MAP-04',
            'first_name' => 'NoConfig',
            'last_name' => 'Worker',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        // Ensure no active EmpIncome exists for customType
        EmpIncome::where('employee_id', $employee->id)
            ->where('income_type_id', $this->customType->id)
            ->delete();

        $options = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'HAZARD_PAY', 'display' => 1, 'amount' => 5],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->customType->id)
            ->first();

        // Verifies no PayIn was created with an invented rate
        $this->assertNull($payIn);

        // Verifies missing configuration is surfaced on the draft
        $this->assertTrue((bool) $draft->requires_review);
        $this->assertStringContainsString('Missing active income configuration for Hazard Pay', (string) $draft->review_reason);
    }
}
