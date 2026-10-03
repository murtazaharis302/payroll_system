<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollPeriodAccessAndDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $superAdmin;
    protected User $companyAdmin;
    protected User $hrUser;
    protected User $employeeUser;
    protected PayrollPeriod $emptyPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_pp@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'compadmin_pp@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Period Test Corp',
            'company_code' => 'PPCORP',
            'email' => 'pp@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->hrUser = User::create([
            'name' => 'HR User',
            'email' => 'hr_pp@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company->id,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Employee User',
            'email' => 'emp_pp@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'role_id' => 4,
            'company_id' => $this->company->id,
        ]);

        $this->emptyPeriod = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 Empty Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);
    }

    /**
     * Test: HR user can delete an empty payroll period without transactions.
     */
    public function test_hr_user_can_delete_empty_payroll_period(): void
    {
        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->delete(route('company.payroll-periods.destroy', $this->emptyPeriod->id));
        $response->assertRedirect();

        $this->assertDatabaseMissing('payroll_periods', ['id' => $this->emptyPeriod->id]);
    }

    /**
     * Test: Company Admin can delete an empty payroll period.
     */
    public function test_company_admin_can_delete_empty_payroll_period(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->delete(route('company.payroll-periods.destroy', $this->emptyPeriod->id));
        $response->assertRedirect();

        $this->assertDatabaseMissing('payroll_periods', ['id' => $this->emptyPeriod->id]);
    }

    /**
     * Test: Super Admin can delete an empty payroll period.
     */
    public function test_super_admin_can_delete_empty_payroll_period(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->delete(route('admin.payroll-periods.destroy', $this->emptyPeriod->id));
        $response->assertRedirect();

        $this->assertDatabaseMissing('payroll_periods', ['id' => $this->emptyPeriod->id]);
    }

    /**
     * Test: Cannot delete payroll period with existing transactions (pay headers).
     */
    public function test_cannot_delete_payroll_period_with_existing_transactions(): void
    {
        $dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'PPDEPT',
            'name' => 'Period Dept',
            'is_active' => true,
        ]);

        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-PP-1',
            'first_name' => 'Pay',
            'last_name' => 'Header',
            'status' => 'active',
        ]);

        PayHeader::create([
            'payroll_period_id' => $this->emptyPeriod->id,
            'employee_id' => $emp->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->delete(route('company.payroll-periods.destroy', $this->emptyPeriod->id));
        $response->assertSessionHas('error', 'Cannot delete period with existing payroll transactions.');

        $this->assertDatabaseHas('payroll_periods', ['id' => $this->emptyPeriod->id]);
    }

    /**
     * Test: Employee user is blocked with 403 Forbidden from accessing payroll periods menu and deletion.
     */
    public function test_employee_is_forbidden_from_payroll_periods_access_and_deletion(): void
    {
        $this->actingAs($this->employeeUser);

        $indexRes = $this->get(route('company.payroll-periods.index'));
        $indexRes->assertStatus(403);

        $deleteRes = $this->delete(route('company.payroll-periods.destroy', $this->emptyPeriod->id));
        $deleteRes->assertStatus(403);
    }
}
