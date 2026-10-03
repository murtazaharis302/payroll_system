<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RequireDepartmentForPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $companyA;
    protected Company $companyB;
    protected Department $deptA;
    protected Department $deptB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_dept@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company Alpha',
            'company_code' => 'ALPHA',
            'email' => 'alpha@test.com',
            'status' => '1',
        ]);

        $this->companyB = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company Beta',
            'company_code' => 'BETA',
            'email' => 'beta@test.com',
            'status' => '1',
        ]);

        $this->deptA = Department::create([
            'company_id' => $this->companyA->id,
            'code' => 'ENG_A',
            'name' => 'Engineering A',
            'is_active' => true,
        ]);

        $this->deptB = Department::create([
            'company_id' => $this->companyB->id,
            'code' => 'ENG_B',
            'name' => 'Engineering B',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Employee creation without Department or with cross-company Department fails validation.
     */
    public function test_employee_creation_without_department_or_cross_company_department_fails_validation(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Missing dept_id fails
        $responseNoDept = $this->post(route('payroll-employees.store'), [
            'company_id' => $this->companyA->id,
            'first_name' => 'No',
            'last_name' => 'Dept',
            'monthly_rate' => 20000.00,
        ]);
        $responseNoDept->assertSessionHasErrors('dept_id');

        // 2. Cross-company dept_id fails
        $responseCrossDept = $this->post(route('payroll-employees.store'), [
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptB->id, // belongs to Company B
            'first_name' => 'Cross',
            'last_name' => 'Dept',
            'monthly_rate' => 20000.00,
        ]);
        $responseCrossDept->assertSessionHasErrors('dept_id');
    }

    /**
     * Test 2: Employee edit cannot leave required Department blank or assign cross-company Department.
     */
    public function test_employee_edit_cannot_clear_department_or_assign_cross_company_department(): void
    {
        $this->actingAs($this->adminUser);

        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-EDIT-01',
            'first_name' => 'Valid',
            'last_name' => 'Employee',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        // Attempt clearing department
        $responseClear = $this->put(route('payroll-employees.update', $employee->id), [
            'company_id' => $this->companyA->id,
            'dept_id' => null,
            'first_name' => 'Valid',
            'last_name' => 'Employee',
            'status' => 'active',
        ]);
        $responseClear->assertSessionHasErrors('dept_id');

        // Attempt assigning Company B department
        $responseCross = $this->put(route('payroll-employees.update', $employee->id), [
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptB->id,
            'first_name' => 'Valid',
            'last_name' => 'Employee',
            'status' => 'active',
        ]);
        $responseCross->assertSessionHasErrors('dept_id');
    }

    /**
     * Test 6: AJAX employee creation and quick record update endpoints enforce Department validation.
     */
    public function test_ajax_employee_creation_and_quick_record_update_enforces_department_validation(): void
    {
        $this->actingAs($this->adminUser);

        // 1. AJAX employee store missing dept_id
        $ajaxStoreNoDept = $this->postJson(route('payroll-employees.store'), [
            'company_id' => $this->companyA->id,
            'first_name' => 'AjaxNo',
            'last_name' => 'Dept',
            'monthly_rate' => 20000.00,
        ]);
        $ajaxStoreNoDept->assertStatus(422)->assertJsonValidationErrors('dept_id');

        // 2. AJAX employee store cross-company dept_id
        $ajaxStoreCrossDept = $this->postJson(route('payroll-employees.store'), [
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptB->id,
            'first_name' => 'AjaxCross',
            'last_name' => 'Dept',
            'monthly_rate' => 20000.00,
        ]);
        $ajaxStoreCrossDept->assertStatus(422)->assertJsonValidationErrors('dept_id');

        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-AJAX-01',
            'first_name' => 'Ajax',
            'last_name' => 'Worker',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        // 3. AJAX quick record update missing dept_id
        $ajaxUpdateNoDept = $this->postJson(route('payroll-employees.update-record', $employee->id), [
            'first_name' => 'Ajax',
            'last_name' => 'Worker',
            'status' => 'active',
            'dept_id' => null,
        ]);
        $ajaxUpdateNoDept->assertStatus(422)->assertJsonValidationErrors('dept_id');

        // 4. AJAX quick record update cross-company dept_id
        $ajaxUpdateCrossDept = $this->postJson(route('payroll-employees.update-record', $employee->id), [
            'first_name' => 'Ajax',
            'last_name' => 'Worker',
            'status' => 'active',
            'dept_id' => $this->deptB->id,
        ]);
        $ajaxUpdateCrossDept->assertStatus(422)->assertJsonValidationErrors('dept_id');
    }

    /**
     * Test 3: Employee with valid company Department can be generated for payroll.
     */
    public function test_employee_with_valid_department_can_be_generated_for_payroll(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-VALID-01',
            'first_name' => 'Valid',
            'last_name' => 'Worker',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'Period 2026-08-A',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);

        $this->assertCount(1, $headers);
        $this->assertEquals($employee->id, $headers->first()->employee_id);
    }

    /**
     * Test 4: Explicitly targeted employee with missing or cross-company department throws clear eligibility error.
     */
    public function test_explicitly_selected_employee_without_valid_department_returns_clear_error(): void
    {
        $employeeNoDept = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => null,
            'employee_code' => 'EMP-NODEPT-01',
            'first_name' => 'NoDept',
            'last_name' => 'User',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'Period 2026-08-B',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();

        $failed = false;
        try {
            $service->generateDraft($period, [$employeeNoDept->id]);
        } catch (\InvalidArgumentException $e) {
            $failed = true;
            $this->assertStringContainsString('Department is required before this employee can be included in payroll', $e->getMessage());
            $this->assertStringContainsString('EMP-NODEPT-01', $e->getMessage());
        }

        $this->assertTrue($failed, 'Targeting employee without department must throw InvalidArgumentException.');
    }

    /**
     * Test 5: Batch payroll generation excludes active employees missing a department.
     */
    public function test_batch_payroll_generation_excludes_employees_without_department(): void
    {
        $validEmp = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => $this->deptA->id,
            'employee_code' => 'EMP-VALID-BATCH',
            'first_name' => 'Valid',
            'last_name' => 'BatchUser',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $noDeptEmp = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'dept_id' => null,
            'employee_code' => 'EMP-INVALID-BATCH',
            'first_name' => 'Invalid',
            'last_name' => 'BatchUser',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'Period 2026-08-C',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, []); // Batch generation for all eligible employees

        $this->assertCount(1, $headers);
        $this->assertEquals($validEmp->id, $headers->first()->employee_id);
    }
}
