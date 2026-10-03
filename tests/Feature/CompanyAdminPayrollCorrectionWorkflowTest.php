<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use App\Services\PayrollWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAdminPayrollCorrectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected User $hrUser;
    protected EmployeeMaster $employee;
    protected PayrollPeriod $period;
    protected IncomeType $basicIncomeType;
    protected DeductionType $taxDeductionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MenusTableSeeder::class);

        $superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id'  => Role::SUPER_ADMIN,
            'role'     => 'admin',
        ]);

        $this->company = Company::create([
            'name'         => 'Acme Global Corp',
            'email'        => 'corp@acme.com',
            'status'       => '1',
            'user_id'      => $superAdmin->id,
            'company_code' => 'ACME01',
        ]);

        $this->companyAdmin = User::create([
            'name'       => 'Acme Company Admin',
            'email'      => 'admin@acme.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::COMPANY_ADMIN,
            'role'       => 'company',
            'company_id' => $this->company->id,
        ]);

        $this->hrUser = User::create([
            'name'       => 'Acme HR Staff',
            'email'      => 'hr@acme.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::HR,
            'role'       => 'hr',
            'company_id' => $this->company->id,
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'code'       => 'OPS',
            'name'       => 'Operations',
            'is_active'  => true,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'dept_id'       => $dept->id,
            'first_name'    => 'Jane',
            'last_name'     => 'Doe',
            'email'         => 'jane@acme.com',
            'employee_code' => 'EMP-101',
            'monthly_rate'  => 30000,
            'daily_rate'    => 1153.85,
            'hourly_rate'   => 144.23,
            'status'        => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id'   => $this->company->id,
            'period_name'  => 'March 2026 1st Cutoff',
            'period_start' => '2026-03-01',
            'period_end'   => '2026-03-15',
            'pay_date'     => '2026-03-20',
            'status'       => 'open',
        ]);

        $this->basicIncomeType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['name' => 'Basic Pay', 'company_id' => $this->company->id, 'is_active' => true]
        );

        $this->taxDeductionType = DeductionType::firstOrCreate(
            ['code' => 'TAX'],
            ['name' => 'Withholding Tax', 'company_id' => $this->company->id, 'is_active' => true]
        );
    }

    protected function createPayrollHeader(string $status, float $gross = 15000.0, float $deductions = 1000.0, ?EmployeeMaster $emp = null): PayHeader
    {
        $dept = \App\Models\Department::firstOrCreate(
            ['code' => 'OPS', 'company_id' => $this->company->id],
            ['name' => 'Operations', 'is_active' => true]
        );

        $employee = $emp ?? EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'dept_id'       => $dept->id,
            'first_name'    => 'Test',
            'last_name'     => 'Employee ' . uniqid(),
            'email'         => 'emp' . uniqid() . '@acme.com',
            'employee_code' => 'EMP-' . uniqid(),
            'monthly_rate'  => 30000,
            'daily_rate'    => 1153.85,
            'hourly_rate'   => 144.23,
            'status'        => 'active',
        ]);

        $header = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id'       => $employee->id,
            'company_id'        => $this->company->id,
            'status'            => $status,
            'days_worked'       => 10,
            'total_gross_pay'   => $gross,
            'total_deductions'  => $deductions,
            'net_pay'           => $gross - $deductions,
            'lock_version'      => 1,
            'submitted_at'      => $status !== 'draft' ? now()->subDay() : null,
            'submitted_by'      => $status !== 'draft' ? $this->hrUser->id : null,
            'approved_at'       => in_array($status, ['approved', 'posted', 'paid']) ? now()->subHours(5) : null,
            'approved_by'       => in_array($status, ['approved', 'posted', 'paid']) ? $this->companyAdmin->id : null,
            'posted_at'         => in_array($status, ['posted', 'paid']) ? now()->subHours(2) : null,
        ]);

        PayIn::create([
            'pay_id'         => $header->id,
            'income_type_id' => $this->basicIncomeType->id,
            'number_input'   => 10,
            'rate'           => 1500.0,
        ]);

        PayOut::create([
            'pay_id'            => $header->id,
            'deduction_type_id' => $this->taxDeductionType->id,
            'number_input'      => 1,
            'rate'              => 1000.0,
        ]);

        return $header;
    }

    public function test_hr_cannot_edit_pending_approval_or_approved_without_override_capability(): void
    {
        $pending = $this->createPayrollHeader('pending_approval');
        $approved = $this->createPayrollHeader('approved');

        $this->actingAs($this->hrUser);

        // HR trying to edit pending approval
        $responsePending = $this->put(route('company.payroll-drafts.update', $pending->id), [
            'days_worked' => 12,
        ]);
        $responsePending->assertSessionHas('statusDanger');

        // HR trying to edit approved
        $responseApproved = $this->put(route('company.payroll-drafts.update', $approved->id), [
            'days_worked' => 12,
        ]);
        $responseApproved->assertSessionHas('statusDanger');

        $pending->refresh();
        $this->assertEquals(10.0, (float) $pending->days_worked);
        $this->assertEquals('pending_approval', $pending->status);

        $approved->refresh();
        $this->assertEquals(10.0, (float) $approved->days_worked);
        $this->assertEquals('approved', $approved->status);
    }

    public function test_company_admin_can_save_pending_approval_and_status_remains_pending_approval(): void
    {
        $pending = $this->createPayrollHeader('pending_approval');
        $payIn = $pending->payIns()->first();

        $this->actingAs($this->companyAdmin);

        $response = $this->put(route('company.payroll-drafts.update', $pending->id), [
            'days_worked' => 12,
            'pay_ins' => [
                ['id' => $payIn->id, 'number_input' => 12, 'rate' => 1500.0],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Payroll draft saved successfully.');

        $pending->refresh();
        $this->assertEquals('pending_approval', $pending->status);
        $this->assertEquals(12.0, (float) $pending->days_worked);
        $this->assertEquals(18000.0, (float) $pending->total_gross_pay);
        $this->assertEquals((float) $pending->total_gross_pay - (float) $pending->total_deductions, (float) $pending->net_pay);
    }

    public function test_approved_payroll_cannot_be_directly_edited_before_reopening(): void
    {
        $approved = $this->createPayrollHeader('approved');
        $this->assertFalse($approved->canBeEditedBy($this->companyAdmin));

        $this->actingAs($this->companyAdmin);

        $response = $this->put(route('company.payroll-drafts.update', $approved->id), [
            'days_worked' => 14,
        ]);

        $response->assertSessionHas('statusDanger');

        $approved->refresh();
        $this->assertEquals('approved', $approved->status);
        $this->assertEquals(10.0, (float) $approved->days_worked);
    }

    public function test_reopening_requires_override_permission_and_mandatory_reason(): void
    {
        $approved = $this->createPayrollHeader('approved');

        // HR without override cannot reopen
        $this->actingAs($this->hrUser);
        $responseHr = $this->post(route('company.payroll-approvals.reopen', $approved->id), [
            'reopen_reason' => 'HR attempting reopen',
        ]);
        $responseHr->assertForbidden();

        // Company Admin without reason fails validation
        $this->actingAs($this->companyAdmin);
        $responseNoReason = $this->post(route('company.payroll-approvals.reopen', $approved->id), [
            'reopen_reason' => '',
        ]);
        $responseNoReason->assertSessionHasErrors(['reopen_reason']);
    }

    public function test_reopening_clears_approval_metadata_preserves_submission_and_lines_and_logs_audit(): void
    {
        $submittedAt = now()->subDays(2)->startOfMinute();
        $approved = $this->createPayrollHeader('approved');
        $approved->update([
            'submitted_at' => $submittedAt,
            'submitted_by' => $this->hrUser->id,
            'lock_version' => 3,
        ]);

        $initialPayInCount = $approved->payIns()->count();
        $initialPayOutCount = $approved->payOuts()->count();

        $this->actingAs($this->companyAdmin);
        $response = $this->post(route('company.payroll-approvals.reopen', $approved->id), [
            'reopen_reason' => 'Employee overtime hours were underreported in initial submission.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $approved->refresh();
        $this->assertEquals('pending_approval', $approved->status);
        $this->assertNull($approved->approved_at);
        $this->assertNull($approved->approved_by);
        $this->assertEquals($submittedAt->format('Y-m-d H:i:s'), $approved->submitted_at->format('Y-m-d H:i:s'));
        $this->assertEquals($this->hrUser->id, $approved->submitted_by);
        $this->assertEquals(4, $approved->lock_version);
        $this->assertEquals($initialPayInCount, $approved->payIns()->count());
        $this->assertEquals($initialPayOutCount, $approved->payOuts()->count());

        // Single audit log recorded
        $reopenLog = ActivityLog::where('action', 'reopen_for_correction')
            ->where('record_id', $approved->id)
            ->first();

        $this->assertNotNull($reopenLog);
        $this->assertStringContainsString('approved -> pending_approval', $reopenLog->description);
        $this->assertStringContainsString('overtime hours were underreported', $reopenLog->description);
    }

    public function test_reopened_payroll_can_be_edited_and_reapproved(): void
    {
        $approved = $this->createPayrollHeader('approved');
        $payIn = $approved->payIns()->first();

        // 1. Reopen
        $workflow = app(PayrollWorkflowService::class);
        $workflow->reopenForCorrection($approved, 'Correction needed', $this->companyAdmin);

        $approved->refresh();
        $this->assertEquals('pending_approval', $approved->status);
        $this->assertTrue($approved->canBeEditedBy($this->companyAdmin));

        // 2. Edit
        $this->actingAs($this->companyAdmin);
        $editResponse = $this->put(route('company.payroll-drafts.update', $approved->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $payIn->id, 'number_input' => 15, 'rate' => 1500.0],
            ],
        ]);
        $editResponse->assertRedirect();

        $approved->refresh();
        $this->assertEquals('pending_approval', $approved->status);
        $this->assertEquals(15.0, (float) $approved->days_worked);
        $this->assertEquals(22500.0, (float) $approved->total_gross_pay);

        // 3. Re-approve
        $approveResponse = $this->post(route('company.payroll-approvals.approve', $approved->id), [
            'approval_notes' => 'Re-approved after corrected attendance.',
        ]);
        $approveResponse->assertRedirect();

        $approved->refresh();
        $this->assertEquals('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertEquals($this->companyAdmin->id, $approved->approved_by);
    }

    public function test_posted_and_paid_remain_locked_against_editing_and_reopening(): void
    {
        $posted = $this->createPayrollHeader('posted');
        $paid = $this->createPayrollHeader('paid');

        $this->actingAs($this->companyAdmin);

        // Direct update blocked
        $this->put(route('company.payroll-drafts.update', $posted->id), ['days_worked' => 11])
            ->assertSessionHas('statusDanger');
        $this->put(route('company.payroll-drafts.update', $paid->id), ['days_worked' => 11])
            ->assertSessionHas('statusDanger');

        // Reopen blocked
        $this->post(route('company.payroll-approvals.reopen', $posted->id), ['reopen_reason' => 'Try reopen posted'])
            ->assertSessionHas('statusDanger');
        $this->post(route('company.payroll-approvals.reopen', $paid->id), ['reopen_reason' => 'Try reopen paid'])
            ->assertSessionHas('statusDanger');

        $posted->refresh();
        $paid->refresh();
        $this->assertEquals('posted', $posted->status);
        $this->assertEquals('paid', $paid->status);
    }

    public function test_concurrency_reopen_fails_if_payroll_transitions_to_posted_before_write(): void
    {
        $approved = $this->createPayrollHeader('approved');

        // Simulate status changing to posted immediately before transition execute
        $approved->update(['status' => 'posted']);

        $workflow = app(PayrollWorkflowService::class);

        $this->expectException(InvalidStatusTransitionException::class);
        $workflow->reopenForCorrection($approved, 'Concurrency race condition test', $this->companyAdmin);
    }

    public function test_generic_transition_from_approved_to_pending_approval_is_rejected(): void
    {
        $approved = $this->createPayrollHeader('approved');
        $workflow = app(PayrollWorkflowService::class);

        $this->assertFalse($workflow->canTransition(PayrollWorkflowService::STATE_APPROVED, PayrollWorkflowService::STATE_PENDING_APPROVAL));

        $this->expectException(InvalidStatusTransitionException::class);
        $workflow->transition($approved, PayrollWorkflowService::STATE_PENDING_APPROVAL, ['user' => $this->companyAdmin]);
    }

    public function test_regeneration_skips_pending_and_approved_records_because_can_edit_was_not_broadened(): void
    {
        $pending = $this->createPayrollHeader('pending_approval', 15000.0, 1000.0);
        $approved = $this->createPayrollHeader('approved', 15000.0, 1000.0);

        $this->assertFalse($pending->canEdit());
        $this->assertFalse($approved->canEdit());

        $service = app(PayrollProcessingService::class);
        $options = [
            'incomes' => [['income_type' => 'BASIC', 'display' => 1]],
            'deductions' => [['deduction_type' => 'TAX', 'display' => 1]],
        ];

        // Run draft generation for these employees
        $service->generateDraft($this->period, [$pending->employee_id, $approved->employee_id], $options);

        $pending->refresh();
        $approved->refresh();

        // Both headers survive unmodified
        $this->assertEquals('pending_approval', $pending->status);
        $this->assertEquals(15000.0, (float) $pending->total_gross_pay);

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals(15000.0, (float) $approved->total_gross_pay);
    }

    public function test_permission_matrix_dynamic_delegation_of_locked_edit_override(): void
    {
        $overrideMenu = Menu::where('module', 'payroll_locked_edit_override')->firstOrFail();
        $pending = $this->createPayrollHeader('pending_approval');

        // Initially HR cannot edit
        $this->assertFalse($pending->canBeEditedBy($this->hrUser));

        // Grant override capability to HR in menu_role_access
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $overrideMenu->id],
            ['is_enabled' => true]
        );

        $this->assertTrue($pending->canBeEditedBy($this->hrUser));

        // Revoke override capability
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $overrideMenu->id],
            ['is_enabled' => false]
        );

        $this->assertFalse($pending->canBeEditedBy($this->hrUser));
    }
}
