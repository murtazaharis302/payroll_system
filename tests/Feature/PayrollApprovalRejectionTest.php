<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollApprovalRejectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $companyAdmin;

    private User $otherCompanyAdmin;

    private User $superAdmin;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => Role::SUPER_ADMIN, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => Role::COMPANY_ADMIN, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => Role::HR, 'name' => 'HR']);

        $this->companyAdmin = $this->makeUser('Company Reject Admin', 'company-reject-admin@example.test', 'company', Role::COMPANY_ADMIN);
        $this->company = $this->makeCompany($this->companyAdmin, 'Reject Company', 'REJECT-A');
        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->otherCompanyAdmin = $this->makeUser('Other Reject Admin', 'other-reject-admin@example.test', 'company', Role::COMPANY_ADMIN);
        $this->otherCompany = $this->makeCompany($this->otherCompanyAdmin, 'Other Reject Company', 'REJECT-B');
        $this->otherCompanyAdmin->update(['company_id' => $this->otherCompany->id]);

        $this->superAdmin = $this->makeUser('Super Reject Admin', 'super-reject-admin@example.test', 'admin', Role::SUPER_ADMIN);
        $this->hr = $this->makeUser('Reject HR', 'reject-hr@example.test', 'hr', Role::HR, $this->company->id);
    }

    public function test_company_admin_single_reject_uses_company_route_and_persists_reason(): void
    {
        $draft = $this->makeDraft($this->company);

        $this->actingAs($this->companyAdmin)
            ->get(route('company.payroll-approvals.index'))
            ->assertOk()
            ->assertSee('company\\/payroll-approvals\\/__draft_id__\\/reject', false)
            ->assertDontSee('/admin/payroll-approvals/${draftId}/reject')
            ->assertSee('name="rejection_reason"', false);

        $this->post(route('company.payroll-approvals.reject', $draft->id), [
            'rejection_reason' => 'Company review needs corrected attendance.',
        ])->assertRedirect(route('company.payroll-approvals.index'));

        $draft->refresh();
        $this->assertSame('rejected', $draft->status);
        $this->assertSame('Company review needs corrected attendance.', $draft->rejection_reason);
        $this->assertSame($this->companyAdmin->id, $draft->rejected_by);
        $this->assertNotNull($draft->rejected_at);
    }

    public function test_company_admin_batch_rejects_pending_company_drafts_with_the_submitted_reason(): void
    {
        $first = $this->makeDraft($this->company);
        $second = $this->makeDraft($this->company);

        $this->actingAs($this->companyAdmin)
            ->post(route('company.payroll-approvals.batch-reject'), [
                'draft_ids' => [$first->id, $second->id],
                'rejection_reason' => 'Batch rejection reason reaches the workflow.',
            ])
            ->assertSessionHas('status', 'Rejected 2 payroll drafts successfully.');

        foreach ([$first, $second] as $draft) {
            $draft->refresh();
            $this->assertSame('rejected', $draft->status);
            $this->assertSame('Batch rejection reason reaches the workflow.', $draft->rejection_reason);
            $this->assertSame($this->companyAdmin->id, $draft->rejected_by);
        }
    }

    public function test_hr_cannot_reject_individually_or_in_batch(): void
    {
        $draft = $this->makeDraft($this->company);

        $this->actingAs($this->hr)
            ->post(route('admin.payroll-approvals.reject', $draft->id), [
                'rejection_reason' => 'HR must not reject payroll.',
            ])
            ->assertForbidden();

        $this->post(route('admin.payroll-approvals.batch-reject'), [
            'draft_ids' => [$draft->id],
            'rejection_reason' => 'HR must not batch reject payroll.',
        ])->assertForbidden();

        $this->assertSame('pending_approval', $draft->fresh()->status);
    }

    public function test_company_admin_cannot_reject_another_company_draft_and_batch_skips_it(): void
    {
        $ownDraft = $this->makeDraft($this->company);
        $foreignDraft = $this->makeDraft($this->otherCompany);

        $this->actingAs($this->companyAdmin)
            ->post(route('company.payroll-approvals.reject', $foreignDraft->id), [
                'rejection_reason' => 'Cross-company request.',
            ])
            ->assertForbidden();

        $this->post(route('company.payroll-approvals.batch-reject'), [
            'draft_ids' => [$ownDraft->id, $foreignDraft->id],
            'rejection_reason' => 'Only the owned draft should be rejected.',
        ])->assertSessionHas('status', 'Rejected 1 payroll drafts successfully.');

        $this->assertSame('rejected', $ownDraft->fresh()->status);
        $this->assertSame('pending_approval', $foreignDraft->fresh()->status);
    }

    public function test_non_pending_drafts_are_not_rejected_and_super_admin_can_still_reject(): void
    {
        $draftRecord = $this->makeDraft($this->company, 'draft');
        $approvedRecord = $this->makeDraft($this->company, 'approved');
        $pendingRecord = $this->makeDraft($this->otherCompany);

        $this->actingAs($this->companyAdmin)
            ->post(route('company.payroll-approvals.batch-reject'), [
                'draft_ids' => [$draftRecord->id, $approvedRecord->id],
                'rejection_reason' => 'These records are not pending.',
            ])
            ->assertSessionHas('status', 'Rejected 0 payroll drafts successfully.');

        $this->assertSame('draft', $draftRecord->fresh()->status);
        $this->assertSame('approved', $approvedRecord->fresh()->status);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.payroll-approvals.reject', $pendingRecord->id), [
                'rejection_reason' => 'Super Admin rejection remains available.',
            ])
            ->assertRedirect(route('admin.payroll-approvals.index'));

        $this->assertSame('rejected', $pendingRecord->fresh()->status);
        $this->assertSame('Super Admin rejection remains available.', $pendingRecord->fresh()->rejection_reason);
    }

    private function makeUser(string $name, string $email, string $role, int $roleId, ?int $companyId = null): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'role_id' => $roleId,
            'company_id' => $companyId,
        ]);
    }

    private function makeCompany(User $owner, string $name, string $code): Company
    {
        return Company::create([
            'user_id' => $owner->id,
            'name' => $name,
            'company_code' => $code,
            'email' => strtolower($code).'@example.test',
            'status' => '1',
        ]);
    }

    private function makeDraft(Company $company, string $status = 'pending_approval'): PayHeader
    {
        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'REJ-'.strtoupper(uniqid()),
            'first_name' => 'Payroll',
            'last_name' => 'Review',
            'monthly_rate' => 20000,
            'status' => 'active',
        ]);
        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Rejection '.uniqid(),
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        return PayHeader::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'status' => $status,
            'total_gross_pay' => 10000,
            'net_pay' => 10000,
        ]);
    }
}
