<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\EmpLoan;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAuthorizationAndIDORTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminCompanyA;
    protected User $adminCompanyB;
    protected User $superAdmin;
    protected Company $companyA;
    protected Company $companyB;
    protected EmployeeMaster $employeeMasterA;
    protected EmployeeMaster $employeeMasterB;
    protected PayrollPeriod $periodA;
    protected PayrollPeriod $periodB;
    protected PayHeader $draftA;
    protected PayHeader $draftB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Company A and User A
        $this->adminCompanyA = User::create([
            'name' => 'Admin Company A',
            'email' => 'admin.a@company.test',
            'password' => bcrypt('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->adminCompanyA->id,
            'name' => 'Company A',
            'company_code' => 'COMP-A',
            'email' => 'comp.a@test.com',
            'status' => 1,
        ]);

        $this->adminCompanyA->update(['company_id' => $this->companyA->id]);

        $this->employeeMasterA = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'employee_code' => 'EMP-A-001',
            'first_name' => 'Alice',
            'last_name' => 'Alpha',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $this->periodA = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'Period A 2025',
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-15',
            'pay_date' => '2025-01-20',
            'status' => 'open',
        ]);

        $this->draftA = PayHeader::create([
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->employeeMasterA->id,
            'status' => 'draft',
            'total_gross_pay' => 15000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 14000.00,
        ]);

        // 2. Setup Company B and User B
        $this->adminCompanyB = User::create([
            'name' => 'Admin Company B',
            'email' => 'admin.b@company.test',
            'password' => bcrypt('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->companyB = Company::create([
            'user_id' => $this->adminCompanyB->id,
            'name' => 'Company B',
            'company_code' => 'COMP-B',
            'email' => 'comp.b@test.com',
            'status' => 1,
        ]);

        $this->adminCompanyB->update(['company_id' => $this->companyB->id]);

        $this->employeeMasterB = EmployeeMaster::create([
            'company_id' => $this->companyB->id,
            'employee_code' => 'EMP-B-001',
            'first_name' => 'Bob',
            'last_name' => 'Beta',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        $this->periodB = PayrollPeriod::create([
            'company_id' => $this->companyB->id,
            'period_name' => 'Period B 2025',
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-15',
            'pay_date' => '2025-01-20',
            'status' => 'open',
        ]);

        $this->draftB = PayHeader::create([
            'payroll_period_id' => $this->periodB->id,
            'employee_id' => $this->employeeMasterB->id,
            'status' => 'draft',
            'total_gross_pay' => 20000.00,
            'total_deductions' => 1500.00,
            'net_pay' => 18500.00,
        ]);

        // 3. Setup Super Admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@system.test',
            'password' => bcrypt('password'),
            'role_id' => Role::SUPER_ADMIN,
            'role' => 'admin',
            'company_id' => $this->companyA->id,
        ]);
    }

    /** @test */
    public function company_a_user_cannot_edit_company_b_payroll_draft(): void
    {
        $this->actingAs($this->adminCompanyA);
        session(['company_id' => $this->companyA->id]);

        $response = $this->get(route('payroll-drafts.edit', $this->draftB->id));

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    /** @test */
    public function company_a_user_can_destroy_same_company_legacy_employee_record(): void
    {
        $this->actingAs($this->adminCompanyA);
        session(['company_id' => $this->companyA->id]);

        $userAStaff = User::create([
            'name' => 'Staff Company A',
            'email' => 'staff.a@test.com',
            'password' => bcrypt('password'),
            'role_id' => Role::EMPLOYEE,
            'company_id' => $this->companyA->id,
            'emp_id' => $this->employeeMasterA->id,
        ]);

        $legacyEmployeeA = Employee::create([
            'owner_user_id' => $this->adminCompanyA->id,
            'e_user_id' => $userAStaff->id,
            'id_number' => 'EMP-LEG-A',
            'designation' => 'Staff',
            'status' => 'active',
        ]);

        $response = $this->delete(route('deleteEmployee', $legacyEmployeeA->id));

        $response->assertRedirect(route('employee'));
        $this->assertDatabaseMissing('employees', ['id' => $legacyEmployeeA->id]);
        $this->assertDatabaseMissing('users', ['id' => $userAStaff->id]);
    }

    /** @test */
    public function company_a_user_cannot_destroy_company_b_employee_record(): void
    {
        $this->actingAs($this->adminCompanyA);
        session(['company_id' => $this->companyA->id]);

        // Create legacy employee record linked to Company B user & owner
        $userBStaff = User::create([
            'name' => 'Staff Company B',
            'email' => 'staff.b@test.com',
            'password' => bcrypt('password'),
            'role_id' => Role::EMPLOYEE,
            'company_id' => $this->companyB->id,
            'emp_id' => $this->employeeMasterB->id,
        ]);

        $legacyEmployeeB = Employee::create([
            'owner_user_id' => $this->adminCompanyB->id,
            'e_user_id' => $userBStaff->id,
            'id_number' => 'EMP-LEG-B',
            'designation' => 'Staff B',
            'status' => 'active',
        ]);

        $response = $this->delete(route('deleteEmployee', $legacyEmployeeB->id));

        $this->assertTrue(in_array($response->status(), [403, 404, 302], true));
        $this->assertDatabaseHas('employees', ['id' => $legacyEmployeeB->id]);
        $this->assertDatabaseHas('users', ['id' => $userBStaff->id]);
        $this->assertDatabaseHas('employees_master', ['id' => $this->employeeMasterB->id]);
    }

    /** @test */
    public function batch_submit_rejects_and_logs_unauthorized_company_b_drafts(): void
    {
        $this->actingAs($this->adminCompanyA);
        session(['company_id' => $this->companyA->id]);

        $response = $this->post(route('payroll-drafts.batch-submit'), [
            'draft_ids' => [$this->draftA->id, $this->draftB->id],
        ]);

        // Company A's draft should be submitted, Company B's draft should remain draft
        $this->assertEquals('pending_approval', $this->draftA->fresh()->status);
        $this->assertEquals('draft', $this->draftB->fresh()->status);

        // Verify security audit alert was logged for draftB
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'security_alert',
            'record_id' => $this->draftB->id,
        ]);
    }

    /** @test */
    public function batch_delete_rejects_and_logs_unauthorized_company_b_drafts(): void
    {
        $this->actingAs($this->adminCompanyA);
        session(['company_id' => $this->companyA->id]);

        $response = $this->post(route('payroll-drafts.batch-destroy'), [
            'draft_ids' => [$this->draftB->id],
        ]);

        // Draft B must NOT be deleted
        $this->assertDatabaseHas('pay_headers', ['id' => $this->draftB->id]);

        // Security alert log created
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'security_alert',
            'record_id' => $this->draftB->id,
        ]);
    }

    /** @test */
    public function ajax_employee_search_blocks_forged_company_id(): void
    {
        $this->actingAs($this->adminCompanyA);
        session(['company_id' => $this->companyA->id]);

        $response = $this->getJson(route('employees.ajax-search', [
            'company_id' => $this->companyB->id,
            'q' => 'Bob',
        ]));

        $response->assertStatus(403);
    }

    /** @test */
    public function super_admin_access_to_cross_tenant_resource_is_audited(): void
    {
        $this->actingAs($this->superAdmin);
        session(['company_id' => $this->companyA->id]);

        // Super Admin accesses Company B draft
        $response = $this->get(route('payroll-drafts.edit', $this->draftB->id));

        $response->assertStatus(200);

        // Verify Super Admin cross-tenant audit log entry was generated
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'super_admin_cross_tenant_access',
            'company_id' => $this->companyB->id,
        ]);
    }
}
