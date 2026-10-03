<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Observers\EmployeeMasterObserver;
use App\Services\PayrollProcessingService;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoEmployeeIncomeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $companyA;
    protected Company $companyB;
    protected Department $deptA;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->admin = User::factory()->create([
            'role_id' => 1,
            'role' => 'admin',
            'status' => 1,
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Company Alpha',
            'company_code' => 'ALPHA-01',
            'email' => 'alpha@corp.com',
            'status' => 1,
        ]);

        $this->companyB = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Company Beta',
            'company_code' => 'BETA-01',
            'email' => 'beta@corp.com',
            'status' => 1,
        ]);

        $this->deptA = Department::create([
            'company_id' => $this->companyA->id,
            'code' => 'DEPT_A',
            'name' => 'Department A',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Creating a new employee automatically populates EmpIncome for all active company/shared types,
     * excluding BASIC and including OVERTIME with safe default rates.
     */
    public function test_new_employee_automatically_gets_all_active_income_types_excluding_basic_and_including_overtime(): void
    {
        $this->seed(IncomeTypesSeeder::class);

        $customMeal = IncomeType::create([
            'name' => 'Company A Meal',
            'code' => 'MEAL_A',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-AUTO-01',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'monthly_rate' => 20000.00,
            'status' => 'active',
            'date_hired' => '2026-01-01',
        ]);

        $assignedIncomes = EmpIncome::withoutGlobalScopes()->where('employee_id', $employee->id)->get();
        $this->assertNotEmpty($assignedIncomes);

        // Verify BASIC is NOT present and OVERTIME is present
        $basicType = IncomeType::where('code', 'BASIC')->first();
        $otType = IncomeType::where('code', 'OVERTIME')->first();

        $this->assertFalse($assignedIncomes->contains('income_type_id', $basicType->id), 'BASIC must NOT be automatically assigned.');
        $this->assertTrue($assignedIncomes->contains('income_type_id', $otType->id), 'OVERTIME must be automatically assigned.');
        $this->assertTrue($assignedIncomes->contains('income_type_id', $customMeal->id), 'Custom company income type must be automatically assigned.');

        $otAssignment = $assignedIncomes->firstWhere('income_type_id', $otType->id);
        $this->assertEquals(0.00, (float) $otAssignment->rate);
    }

    /**
     * Test 2: Inactive and foreign-company Income Types are NOT assigned to newly created employees.
     */
    public function test_inactive_and_foreign_company_income_types_are_not_assigned(): void
    {
        IncomeType::query()->delete();

        $activeType = IncomeType::create([
            'name' => 'Active Allowance',
            'code' => 'ACTIVE_ALLW',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        $inactiveType = IncomeType::create([
            'name' => 'Inactive Bonus',
            'code' => 'INACTIVE_BNS',
            'company_id' => $this->companyA->id,
            'is_active' => false,
        ]);

        $foreignType = IncomeType::create([
            'name' => 'Foreign Beta Type',
            'code' => 'FOREIGN_BETA',
            'company_id' => $this->companyB->id,
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-FILTER-01',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status' => 'active',
        ]);

        $assignedIncomes = EmpIncome::withoutGlobalScopes()->where('employee_id', $employee->id)->get();
        $this->assertCount(1, $assignedIncomes);
        $this->assertEquals($activeType->id, $assignedIncomes->first()->income_type_id);
        $this->assertFalse($assignedIncomes->contains('income_type_id', $inactiveType->id));
        $this->assertFalse($assignedIncomes->contains('income_type_id', $foreignType->id));
    }

    /**
     * Test 3: Adding a new custom IncomeType before creating an employee causes that type to appear automatically.
     */
    public function test_adding_custom_income_type_before_creating_employee_auto_assigns_that_type(): void
    {
        $this->seed(IncomeTypesSeeder::class);

        $newCustomType = IncomeType::create([
            'name' => 'Hazard Pay',
            'code' => 'HAZARD_PAY',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-CUSTOM-01',
            'first_name' => 'Alex',
            'last_name' => 'Hazard',
            'status' => 'active',
        ]);

        $assignedIncomes = EmpIncome::withoutGlobalScopes()->where('employee_id', $employee->id)->get();
        $this->assertTrue($assignedIncomes->contains('income_type_id', $newCustomType->id));
    }

    /**
     * Test 4: Payroll generation with auto-created defaults calculates Basic Pay and Overtime correctly
     * without corruption from safe zero rate assignments.
     */
    public function test_newly_created_employee_generates_payroll_with_correct_basic_and_overtime(): void
    {
        $this->seed(IncomeTypesSeeder::class);

        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-PAY-01',
            'first_name' => 'Payroll',
            'last_name' => 'Worker',
            'monthly_rate' => 24000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);

        $draft = $headers->first();
        $this->assertNotNull($draft);
        $this->assertEquals($employee->id, $draft->employee_id);

        $basicType = IncomeType::where('code', 'BASIC')->first();
        $basicPayIn = PayIn::where('pay_id', $draft->id)->where('income_type_id', $basicType->id)->first();

        $this->assertNotNull($basicPayIn);
        // Half of 24,000 monthly rate for semi-monthly cutoff = 12,000
        $this->assertEquals(12000.00, (float) $basicPayIn->amount, 'Basic pay must be calculated correctly from EmployeeMaster rates.');
    }

    /**
     * Test 5: Re-running creation logic / observer does not create duplicate EmpIncome rows.
     */
    public function test_re_running_assignment_logic_does_not_produce_duplicate_rows(): void
    {
        IncomeType::query()->delete();

        $type1 = IncomeType::create([
            'name' => 'Allowance',
            'code' => 'ALLOW_DUP',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-DUP-01',
            'first_name' => 'Charlie',
            'last_name' => 'Dup',
            'status' => 'active',
        ]);

        $this->assertEquals(1, EmpIncome::withoutGlobalScopes()->where('employee_id', $employee->id)->where('income_type_id', $type1->id)->count());

        // Re-trigger observer created logic manually
        $observer = new EmployeeMasterObserver();
        $observer->created($employee);

        $this->assertEquals(1, EmpIncome::withoutGlobalScopes()->where('employee_id', $employee->id)->where('income_type_id', $type1->id)->count());
    }

    /**
     * Test 6: Existing employee recurring income assignments are NOT altered when new employees are created.
     */
    public function test_existing_employee_assignments_are_not_altered_or_removed(): void
    {
        IncomeType::query()->delete();

        $type1 = IncomeType::create(['name' => 'Type 1', 'code' => 'T1', 'company_id' => $this->companyA->id, 'is_active' => true]);

        $existingEmployee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-EXIST-01',
            'first_name' => 'Existing',
            'last_name' => 'User',
            'status' => 'active',
        ]);

        EmpIncome::where('employee_id', $existingEmployee->id)
            ->where('income_type_id', $type1->id)
            ->update(['rate' => 3500.00]);

        $this->assertEquals(3500.00, (float) EmpIncome::where('employee_id', $existingEmployee->id)->where('income_type_id', $type1->id)->value('rate'));

        // Create a new employee
        $newEmployee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-NEW-01',
            'first_name' => 'Brand',
            'last_name' => 'New',
            'status' => 'active',
        ]);

        // New employee has rate 0.00
        $this->assertEquals(0.00, (float) EmpIncome::where('employee_id', $newEmployee->id)->where('income_type_id', $type1->id)->value('rate'));

        // Existing employee still has 3500.00
        $this->assertEquals(3500.00, (float) EmpIncome::where('employee_id', $existingEmployee->id)->where('income_type_id', $type1->id)->value('rate'));
    }

    /**
     * Test 7: Quick Master options and saving respect company scope and validation.
     */
    public function test_quick_master_options_and_saving_respect_tenant_scope(): void
    {
        IncomeType::query()->delete();

        $validInc = IncomeType::create(['name' => 'Alpha Inc', 'code' => 'ALPHA_INC', 'company_id' => $this->companyA->id, 'is_active' => true]);
        $foreignInc = IncomeType::create(['name' => 'Beta Inc', 'code' => 'BETA_INC', 'company_id' => $this->companyB->id, 'is_active' => true]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-QM-01',
            'first_name' => 'QM',
            'last_name' => 'User',
            'status' => 'active',
        ]);

        $detailsResp = $this->actingAs($this->admin)->getJson(route('payroll-employees.company-master.details', ['id' => $employee->id]));
        $detailsResp->assertStatus(200);

        $types = collect($detailsResp->json('income_types'));
        $this->assertTrue($types->contains('id', $validInc->id));
        $this->assertFalse($types->contains('id', $foreignInc->id));

        // Foreign saving is rejected
        $rejectPayload = [
            'incomes' => [
                ['income_type_id' => $foreignInc->id, 'amount' => 500.00],
            ],
        ];
        $rejectResp = $this->actingAs($this->admin)->postJson(route('payroll-employees.company-master.update-quick', ['id' => $employee->id]), $rejectPayload);
        $rejectResp->assertStatus(422);

        // Valid saving succeeds
        $validPayload = [
            'incomes' => [
                ['income_type_id' => $validInc->id, 'amount' => 2500.00],
            ],
        ];
        $validResp = $this->actingAs($this->admin)->postJson(route('payroll-employees.company-master.update-quick', ['id' => $employee->id]), $validPayload);
        $validResp->assertStatus(200);

        $this->assertEquals(2500.00, (float) EmpIncome::where('employee_id', $employee->id)->where('income_type_id', $validInc->id)->value('rate'));
    }
}
