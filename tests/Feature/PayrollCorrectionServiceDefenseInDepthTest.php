<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCorrectionServiceDefenseInDepthTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $adminA;
    protected User $adminB;
    protected User $hrUserA;
    protected PayrollPeriod $periodA;
    protected PayrollPeriod $periodB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MenusTableSeeder::class);

        $superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'super@test.com',
            'password' => bcrypt('password'),
            'role_id'  => Role::SUPER_ADMIN,
            'role'     => 'admin',
        ]);

        $this->companyA = Company::create([
            'name' => 'Company A', 'email' => 'a@corp.com', 'status' => '1', 'user_id' => $superAdmin->id, 'company_code' => 'COMA',
        ]);
        $this->companyB = Company::create([
            'name' => 'Company B', 'email' => 'b@corp.com', 'status' => '1', 'user_id' => $superAdmin->id, 'company_code' => 'COMB',
        ]);

        $this->adminA = User::create([
            'name' => 'Admin A', 'email' => 'adminA@a.com', 'password' => bcrypt('password'),
            'role_id' => Role::COMPANY_ADMIN, 'role' => 'company', 'company_id' => $this->companyA->id,
        ]);
        $this->adminB = User::create([
            'name' => 'Admin B', 'email' => 'adminB@b.com', 'password' => bcrypt('password'),
            'role_id' => Role::COMPANY_ADMIN, 'role' => 'company', 'company_id' => $this->companyB->id,
        ]);

        $this->hrUserA = User::create([
            'name' => 'HR Staff A', 'email' => 'hrA@a.com', 'password' => bcrypt('password'),
            'role_id' => Role::HR, 'role' => 'hr', 'company_id' => $this->companyA->id,
        ]);

        $this->periodA = PayrollPeriod::create([
            'company_id' => $this->companyA->id, 'period_name' => 'Period A', 'period_start' => '2026-03-01', 'period_end' => '2026-03-15', 'pay_date' => '2026-03-20', 'status' => 'open',
        ]);
        $this->periodB = PayrollPeriod::create([
            'company_id' => $this->companyB->id, 'period_name' => 'Period B', 'period_start' => '2026-03-01', 'period_end' => '2026-03-15', 'pay_date' => '2026-03-20', 'status' => 'open',
        ]);
    }

    protected function createApprovedPayroll(Company $company, PayrollPeriod $period): PayHeader
    {
        $dept = Department::firstOrCreate(
            ['code' => 'DEPT_' . $company->company_code, 'company_id' => $company->id],
            ['name' => 'Dept ' . $company->name, 'is_active' => true]
        );

        $emp = EmployeeMaster::create([
            'company_id'    => $company->id,
            'dept_id'       => $dept->id,
            'first_name'    => 'Emp',
            'last_name'     => 'Test ' . uniqid(),
            'email'         => 'emp' . uniqid() . '@test.com',
            'employee_code' => 'EMP-' . uniqid(),
            'monthly_rate'  => 30000,
            'status'        => 'active',
        ]);

        return PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id'       => $emp->id,
            'company_id'        => $company->id,
            'status'            => 'approved',
            'total_gross_pay'   => 15000,
            'total_deductions'  => 1000,
            'net_pay'           => 14000,
            'lock_version'      => 1,
            'submitted_at'      => now()->subDay(),
            'submitted_by'      => 1,
            'approved_at'       => now()->subHours(2),
            'approved_by'       => 1,
        ]);
    }

    public function test_service_reopen_direct_invocation_throws_authorization_exception_for_unauthorized_user(): void
    {
        $approved = $this->createApprovedPayroll($this->companyA, $this->periodA);
        $workflow = app(PayrollWorkflowService::class);

        $this->expectException(AuthorizationException::class);
        $workflow->reopenForCorrection($approved, 'Direct attempt without override capability', $this->hrUserA);
    }

    public function test_service_reopen_direct_invocation_throws_authorization_exception_for_cross_company(): void
    {
        $approvedInCompanyB = $this->createApprovedPayroll($this->companyB, $this->periodB);
        $workflow = app(PayrollWorkflowService::class);

        $this->expectException(AuthorizationException::class);
        $workflow->reopenForCorrection($approvedInCompanyB, 'Cross company attempt', $this->adminA);
    }

    public function test_service_reopen_records_passed_user_in_activity_log_not_auth_fallback(): void
    {
        $approved = $this->createApprovedPayroll($this->companyA, $this->periodA);
        $workflow = app(PayrollWorkflowService::class);

        // Run as unauthenticated CLI/queue context but passing adminA explicitly
        auth()->logout();

        $workflow->reopenForCorrection($approved, 'Explicit user actor audit tracking', $this->adminA);

        $log = ActivityLog::where('action', 'reopen_for_correction')
            ->where('record_id', $approved->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->adminA->id, $log->user_id);
    }

    public function test_hr_user_with_override_can_reopen_via_http_company_endpoint(): void
    {
        $overrideMenu = Menu::where('module', 'payroll_locked_edit_override')->firstOrFail();
        $approved = $this->createApprovedPayroll($this->companyA, $this->periodA);

        // 1. HR without override gets 403 Forbidden
        $this->actingAs($this->hrUserA);
        $this->post(route('company.payroll-approvals.reopen', $approved->id), [
            'reopen_reason' => 'Unauthorized HR attempt',
        ])->assertForbidden();

        // 2. Grant override to HR role in Permission Matrix
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $overrideMenu->id],
            ['is_enabled' => true]
        );

        // 3. HR with override successfully reopens via company endpoint
        $response = $this->post(route('company.payroll-approvals.reopen', $approved->id), [
            'reopen_reason' => 'Authorized HR correcting overtime discrepancy',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $approved->refresh();
        $this->assertEquals('pending_approval', $approved->status);
        $this->assertNull($approved->approved_at);

        // 4. Revoke override and assert blocked again on another draft
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->companyA->id, 'role_id' => Role::HR, 'menu_id' => $overrideMenu->id],
            ['is_enabled' => false]
        );

        $secondApproved = $this->createApprovedPayroll($this->companyA, $this->periodA);
        $this->post(route('company.payroll-approvals.reopen', $secondApproved->id), [
            'reopen_reason' => 'Attempt after revocation',
        ])->assertForbidden();
    }
}
