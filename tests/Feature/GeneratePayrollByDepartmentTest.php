<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GeneratePayrollByDepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $hrDept;
    protected Department $engDept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User DeptTest',
            'email' => 'admin_dept@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Dept Test Corp',
            'company_code' => 'DEPTTEST',
            'email' => 'dept@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->hrDept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'HR',
            'name' => 'Human Resources',
            'is_active' => true,
        ]);

        $this->engDept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Selecting a specific department generates payroll draft headers ONLY for employees in that department.
     */
    public function test_payroll_draft_generation_with_specific_department_includes_only_department_employees(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'cut_off_start' => '2026-08-01',
            'cut_off_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $hrEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-01',
            'first_name' => 'Helen',
            'last_name' => 'HR',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $engEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->engDept->id,
            'employee_code' => 'EMP-ENG-01',
            'first_name' => 'Edward',
            'last_name' => 'Engineer',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        $payload = [
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'dept_id' => $this->hrDept->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), $payload);

        $response->assertRedirect(route('payroll-drafts.index', ['payroll_period_id' => $period->id]));

        $this->assertDatabaseHas('pay_headers', [
            'payroll_period_id' => $period->id,
            'employee_id' => $hrEmp->id,
        ]);

        $this->assertDatabaseMissing('pay_headers', [
            'payroll_period_id' => $period->id,
            'employee_id' => $engEmp->id,
        ]);
    }

    /**
     * Test 2: Selecting All Departments (empty dept_id) includes all active employees across departments.
     */
    public function test_payroll_draft_generation_with_all_departments_includes_all_active_employees(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 2nd Cutoff',
            'period_start' => '2026-08-16',
            'period_end' => '2026-08-31',
            'cut_off_start' => '2026-08-16',
            'cut_off_end' => '2026-08-31',
            'pay_date' => '2026-09-05',
            'period_number' => 2,
            'status' => 'open',
        ]);

        $hrEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-02',
            'first_name' => 'Hannah',
            'last_name' => 'HR',
            'monthly_rate' => 32000.00,
            'status' => 'active',
        ]);

        $engEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->engDept->id,
            'employee_code' => 'EMP-ENG-02',
            'first_name' => 'Eric',
            'last_name' => 'Engineer',
            'monthly_rate' => 42000.00,
            'status' => 'active',
        ]);

        $payload = [
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'dept_id' => '',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), $payload);

        $response->assertRedirect(route('payroll-drafts.index', ['payroll_period_id' => $period->id]));

        $this->assertDatabaseHas('pay_headers', [
            'payroll_period_id' => $period->id,
            'employee_id' => $hrEmp->id,
        ]);

        $this->assertDatabaseHas('pay_headers', [
            'payroll_period_id' => $period->id,
            'employee_id' => $engEmp->id,
        ]);
    }

    /**
     * Test 3: Reject request if selected department is invalid or inactive for company.
     */
    public function test_payroll_draft_generation_rejects_inactive_or_foreign_department(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'September 2026 Cutoff',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'cut_off_start' => '2026-09-01',
            'cut_off_end' => '2026-09-15',
            'pay_date' => '2026-09-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $otherCompany = Company::create([
            'name' => 'Other Corp',
            'company_code' => 'OTHER',
            'email' => 'other@test.com',
            'status' => '1',
            'user_id' => $this->adminUser->id,
        ]);

        $foreignDept = Department::create([
            'company_id' => $otherCompany->id,
            'code' => 'FOREIGN',
            'name' => 'Foreign Dept',
            'is_active' => true,
        ]);

        $payload = [
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'dept_id' => $foreignDept->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), $payload);

        $response->assertSessionHas('statusDanger', 'The selected department is invalid or inactive for this company.');
    }

    /**
     * Test 4: Selecting a department with zero active employees stops generation and asserts zero PayHeaders are generated.
     */
    public function test_payroll_draft_generation_with_empty_department_stops_and_generates_zero_headers(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'October 2026 Cutoff',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'cut_off_start' => '2026-10-01',
            'cut_off_end' => '2026-10-15',
            'pay_date' => '2026-10-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $emptyDept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'EMPTY',
            'name' => 'Empty Department',
            'is_active' => true,
        ]);

        EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-99',
            'first_name' => 'Other',
            'last_name' => 'Emp',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $payload = [
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'dept_id' => $emptyDept->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), $payload);

        $response->assertSessionHas('statusDanger', 'No active employees found in the selected department.');

        $this->assertDatabaseMissing('pay_headers', [
            'payroll_period_id' => $period->id,
        ]);
    }

    /**
     * Test 5: Incremental generation when Department A exists -> generates Department B without touching Department A.
     */
    public function test_incremental_generation_department_a_exists_generates_department_b_without_changing_a(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Nov 2026 Cutoff',
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-15',
            'cut_off_start' => '2026-11-01',
            'cut_off_end' => '2026-11-15',
            'pay_date' => '2026-11-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $hrEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-INC',
            'first_name' => 'HR',
            'last_name' => 'Inc',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $engEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->engDept->id,
            'employee_code' => 'EMP-ENG-INC',
            'first_name' => 'ENG',
            'last_name' => 'Inc',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        // Generate Dept A first
        $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), [
                'company_id' => $this->company->id,
                'payroll_period_id' => $period->id,
                'dept_id' => $this->hrDept->id,
            ]);

        $hrHeader = PayHeader::where('payroll_period_id', $period->id)
            ->where('employee_id', $hrEmp->id)
            ->firstOrFail();

        $hrHeaderOriginalId = $hrHeader->id;
        $hrHeaderOriginalNet = $hrHeader->net_pay;

        // Generate Dept B incrementally
        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), [
                'company_id' => $this->company->id,
                'payroll_period_id' => $period->id,
                'dept_id' => $this->engDept->id,
            ]);

        $response->assertRedirect(route('payroll-drafts.index', ['payroll_period_id' => $period->id]));

        // Assert Dept B header created
        $this->assertDatabaseHas('pay_headers', [
            'payroll_period_id' => $period->id,
            'employee_id' => $engEmp->id,
        ]);

        // Assert Dept A header remained untouched
        $hrHeaderFresh = $hrHeader->fresh();
        $this->assertEquals($hrHeaderOriginalId, $hrHeaderFresh->id);
        $this->assertEquals($hrHeaderOriginalNet, $hrHeaderFresh->net_pay);
    }

    /**
     * Test 6: Incremental generation for partially generated department creates only missing employees.
     */
    public function test_incremental_generation_partially_generated_department_creates_only_missing_employees(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Dec 2026 Cutoff',
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-15',
            'cut_off_start' => '2026-12-01',
            'cut_off_end' => '2026-12-15',
            'pay_date' => '2026-12-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $hrEmp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-P1',
            'first_name' => 'HR',
            'last_name' => 'One',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $hrEmp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-P2',
            'first_name' => 'HR',
            'last_name' => 'Two',
            'monthly_rate' => 35000.00,
            'status' => 'active',
        ]);

        // Create draft only for hrEmp1 initially
        $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), [
                'company_id' => $this->company->id,
                'payroll_period_id' => $period->id,
                'dept_id' => $this->hrDept->id,
                'employee_ids' => [$hrEmp1->id],
            ]);

        $hrEmp1Header = PayHeader::where('payroll_period_id', $period->id)
            ->where('employee_id', $hrEmp1->id)
            ->firstOrFail();

        $hrEmp1OriginalId = $hrEmp1Header->id;

        // Generate for entire HR department incrementally
        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), [
                'company_id' => $this->company->id,
                'payroll_period_id' => $period->id,
                'dept_id' => $this->hrDept->id,
            ]);

        $response->assertRedirect(route('payroll-drafts.index', ['payroll_period_id' => $period->id]));

        // Assert hrEmp2 header was generated
        $this->assertDatabaseHas('pay_headers', [
            'payroll_period_id' => $period->id,
            'employee_id' => $hrEmp2->id,
        ]);

        // Assert hrEmp1 header ID remains untouched
        $this->assertEquals($hrEmp1OriginalId, $hrEmp1Header->fresh()->id);
    }

    /**
     * Test 7: Incremental generation when all eligible employees in scope already have drafts returns informative status.
     */
    public function test_incremental_generation_all_eligible_employees_already_have_drafts_returns_status(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Jan 2027 Cutoff',
            'period_start' => '2027-01-01',
            'period_end' => '2027-01-15',
            'cut_off_start' => '2027-01-01',
            'cut_off_end' => '2027-01-15',
            'pay_date' => '2027-01-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $hrEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-FULL',
            'first_name' => 'HR',
            'last_name' => 'Full',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        // Generate draft for all employees
        $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), [
                'company_id' => $this->company->id,
                'payroll_period_id' => $period->id,
                'dept_id' => '',
            ]);

        $initialHeaderCount = PayHeader::where('payroll_period_id', $period->id)->count();

        // Attempt generation again for same period
        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.generate'), [
                'company_id' => $this->company->id,
                'payroll_period_id' => $period->id,
                'dept_id' => '',
            ]);

        $response->assertSessionHas('status', 'Payroll drafts already exist for all eligible employees in the selected scope.');

        $this->assertEquals($initialHeaderCount, PayHeader::where('payroll_period_id', $period->id)->count());
    }
}
