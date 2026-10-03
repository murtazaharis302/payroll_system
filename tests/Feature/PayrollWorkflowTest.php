<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);
    }

    public function test_valid_status_transitions_work_correctly(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Workflow Corp',
            'company_code' => 'WF-001',
            'email' => 'wf@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-WF-01',
            'first_name' => 'Workflow',
            'last_name' => 'Staff',
            'email' => 'wfstaff@test.com',
            'daily_rate' => 1000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'total_gross_pay' => 10000.00,
            'net_pay' => 10000.00,
        ]);

        $workflow = app(PayrollWorkflowService::class);

        // draft -> pending_approval
        $workflow->transition($draft, PayrollWorkflowService::STATE_PENDING_APPROVAL, ['user' => $admin]);
        $draft->refresh();
        $this->assertEquals('pending_approval', $draft->status);
        $this->assertNotNull($draft->submitted_at);
        $this->assertEquals($admin->id, $draft->submitted_by);

        // pending_approval -> approved
        $workflow->transition($draft, PayrollWorkflowService::STATE_APPROVED, ['user' => $admin, 'notes' => 'Verified']);
        $draft->refresh();
        $this->assertEquals('approved', $draft->status);
        $this->assertNotNull($draft->approved_at);
        $this->assertEquals($admin->id, $draft->approved_by);
        $this->assertEquals('Verified', $draft->notes);
    }

    public function test_approved_posted_and_paid_payrolls_cannot_be_edited(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Locked Corp',
            'company_code' => 'LCK-001',
            'email' => 'locked@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-LCK-01',
            'first_name' => 'Locked',
            'last_name' => 'Employee',
            'email' => 'lockedemp@test.com',
            'daily_rate' => 1000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'total_gross_pay' => 10000.00,
            'net_pay' => 10000.00,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        $this->assertFalse($draft->canEdit());

        $this->actingAs($admin);
        $response = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 15.0,
            'basic_pay' => 15000.00,
        ]);

        $response->assertSessionHas('statusDanger');

        $draft->refresh();
        $this->assertEquals(10000.00, (float) $draft->total_gross_pay);
        $this->assertEquals('approved', $draft->status);
    }

    public function test_rejected_records_remain_distinguishable_and_retain_reason(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Rejection Corp',
            'company_code' => 'REJ-001',
            'email' => 'rej@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-REJ-01',
            'first_name' => 'Rej',
            'last_name' => 'Employee',
            'email' => 'rejemp@test.com',
            'daily_rate' => 1000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 10000.00,
            'net_pay' => 10000.00,
        ]);

        $this->actingAs($admin);
        $response = $this->post(route('payroll-approvals.reject', $draft->id), [
            'rejection_reason' => 'Incorrect overtime hours logged',
        ]);

        $response->assertRedirect(route('payroll-approvals.index'));

        $draft->refresh();
        $this->assertEquals('rejected', $draft->status);
        $this->assertEquals('Incorrect overtime hours logged', $draft->rejection_reason);
        $this->assertEquals($admin->id, $draft->rejected_by);
        $this->assertNotNull($draft->rejected_at);
        $this->assertTrue($draft->canEdit());
    }

    public function test_approval_notes_and_draft_notes_persist(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Notes Corp',
            'company_code' => 'NTS-001',
            'email' => 'notes@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-NTS-01',
            'first_name' => 'Notes',
            'last_name' => 'User',
            'email' => 'notesuser@test.com',
            'daily_rate' => 1000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 10000.00,
            'net_pay' => 10000.00,
        ]);

        $this->actingAs($admin);
        $response = $this->post(route('payroll-approvals.approve', $draft->id), [
            'approval_notes' => 'Approved after verifying physical timesheets.',
        ]);

        $response->assertRedirect(route('payroll-approvals.index'));

        $draft->refresh();
        $this->assertEquals('approved', $draft->status);
        $this->assertEquals('Approved after verifying physical timesheets.', $draft->notes);
        $this->assertEquals($admin->id, $draft->approved_by);
    }

    public function test_illegal_transitions_return_409_conflict_and_do_not_change_data(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Illegal Corp',
            'company_code' => 'ILL-001',
            'email' => 'illegal@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-ILL-01',
            'first_name' => 'Illegal',
            'last_name' => 'User',
            'email' => 'illegal@test.com',
            'daily_rate' => 1000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'total_gross_pay' => 10000.00,
            'net_pay' => 10000.00,
        ]);

        $workflow = app(PayrollWorkflowService::class);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->expectExceptionCode(409);

        try {
            $workflow->transition($draft, PayrollWorkflowService::STATE_DRAFT, ['user' => $admin]);
        } finally {
            $draft->refresh();
            $this->assertEquals('approved', $draft->status);
        }
    }

    public function test_legacy_encoded_pending_records_are_backfilled_to_pending_approval_status(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Backfill Corp',
            'company_code' => 'BKF-001',
            'email' => 'bkf@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-BKF-01',
            'first_name' => 'Backfill',
            'last_name' => 'User',
            'email' => 'bkf@test.com',
            'daily_rate' => 1000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $postedTime = '2026-08-05 12:00:00';
        $legacyId = \Illuminate\Support\Facades\DB::table('pay_headers')->insertGetId([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'posted_at' => $postedTime,
            'total_gross_pay' => 10000.00,
            'total_deductions' => 0.00,
            'net_pay' => 10000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Re-run migration backfill logic
        \Illuminate\Support\Facades\DB::table('pay_headers')
            ->where('status', 'draft')
            ->whereNotNull('posted_at')
            ->update([
                'status' => 'pending_approval',
                'submitted_at' => \Illuminate\Support\Facades\DB::raw('posted_at'),
            ]);

        $migratedRecord = PayHeader::find($legacyId);
        $this->assertEquals('pending_approval', $migratedRecord->status);
        $this->assertNotNull($migratedRecord->submitted_at);
        $this->assertEquals('2026-08-05 12:00:00', $migratedRecord->submitted_at->format('Y-m-d H:i:s'));
    }
}
