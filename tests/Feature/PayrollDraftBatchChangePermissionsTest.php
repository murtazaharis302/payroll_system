<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollDraftBatchChangePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $companyAdmin;
    private User $hrUser;
    private Department $dept;
    private PayrollPeriod $period;
    private EmployeeMaster $emp1;
    private PayHeader $draft1;
    private IncomeType $allowanceType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);
        $this->seed(MenusTableSeeder::class);

        Role::firstOrCreate(['id' => Role::SUPER_ADMIN, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => Role::COMPANY_ADMIN, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => Role::HR, 'name' => 'HR']);

        $this->companyAdmin = User::create([
            'name' => 'Batch Admin Perm',
            'email' => 'batch-admin-perm@example.test',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Batch Test Corp Perm',
            'company_code' => 'BATCHPERM',
            'email' => 'batchperm@example.test',
            'status' => '1',
        ]);
        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->hrUser = User::create([
            'name' => 'Batch HR Perm',
            'email' => 'batch-hr-perm@example.test',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => Role::HR,
            'company_id' => $this->company->id,
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'BATCHDEPT',
            'name' => 'Batch Operations',
            'is_active' => true,
        ]);

        $this->emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-001',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'monthly_rate' => 30000,
            'status' => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Batch Test Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->draft1 = PayHeader::create([
            'employee_id' => $this->emp1->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'draft',
            'days_worked' => 10,
            'lock_version' => 1,
        ]);

        $basic = IncomeType::where('code', 'BASIC')->firstOrFail();
        PayIn::create([
            'pay_id' => $this->draft1->id,
            'income_type_id' => $basic->id,
            'number_input' => 10,
            'rate' => 1153.85,
        ]);
        $this->draft1->recalculateTotals();

        $this->allowanceType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'MEAL_ALLW',
            'name' => 'Meal Allowance',
            'is_active' => true,
        ]);
    }

    private function setModuleAccess(string $module, bool $enabled, int $roleId = Role::HR): void
    {
        $menu = Menu::where('module', $module)->firstOrFail();
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => $roleId, 'menu_id' => $menu->id],
            ['is_enabled' => $enabled ? 1 : 0]
        );
    }

    public function test_pending_approval_draft_requires_locked_edit_override(): void
    {
        $this->draft1->update(['status' => 'pending_approval', 'submitted_at' => now(), 'submitted_by' => $this->companyAdmin->id]);

        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_income_inputs', true);
        $this->setModuleAccess('payroll_locked_edit_override', false);

        EmpIncome::create([
            'employee_id' => $this->emp1->id,
            'income_type_id' => $this->allowanceType->id,
            'rate' => 150,
            'is_active' => true,
        ]);

        $responseOff = $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id],
            ]);

        $responseOff->assertStatus(403);
        $this->assertSame('pending_approval', $this->draft1->fresh()->status);

        $this->setModuleAccess('payroll_locked_edit_override', true);

        $responseOn = $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id],
            ]);

        $responseOn->assertRedirect();
        $freshDraft = $this->draft1->fresh();
        $this->assertSame('draft', $freshDraft->status);
        $this->assertNull($freshDraft->submitted_at);
        $this->assertNull($freshDraft->submitted_by);
        $this->assertEquals(2, $freshDraft->lock_version);
    }

    public function test_rejected_payroll_draft_becomes_draft_and_clears_rejection_metadata(): void
    {
        $this->draft1->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $this->companyAdmin->id,
            'rejection_reason' => 'Attendance error',
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id],
                'income_type_configs' => [
                    $this->allowanceType->id => ['number_input' => 1, 'rate' => 100.00],
                ],
            ]);

        $fresh = $this->draft1->fresh();
        $this->assertSame('draft', $fresh->status);
        $this->assertNull($fresh->rejected_at);
        $this->assertNull($fresh->rejected_by);
        $this->assertNull($fresh->rejection_reason);
        $this->assertEquals(2, $fresh->lock_version);
    }

    public function test_approved_and_finalized_payrolls_cannot_be_modified(): void
    {
        $this->draft1->update(['status' => 'approved', 'approved_at' => now()]);

        $response = $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->from(route('company.payroll-drafts.batch-change.create'))
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id],
                'income_type_configs' => [
                    $this->allowanceType->id => ['number_input' => 1, 'rate' => 100.00],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('generation_errors');
        $this->assertSame('approved', $this->draft1->fresh()->status);
        $this->assertDatabaseMissing('pay_ins', ['pay_id' => $this->draft1->id, 'income_type_id' => $this->allowanceType->id]);
    }

    public function test_permission_off_blocks_batch_changes(): void
    {
        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_income_inputs', false);

        $response = $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_income_rate_visibility_off_blocks_monetary_rate_submission(): void
    {
        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_income_inputs', true);
        $this->setModuleAccess('payroll_income_rate_visibility', false);

        $response = $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id],
                'income_type_configs' => [
                    $this->allowanceType->id => ['number_input' => 1, 'rate' => 500.00],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_cross_company_tampering_is_rejected(): void
    {
        $otherCompany = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Other Corp',
            'company_code' => 'OTHER',
            'email' => 'other@example.test',
            'status' => '1',
        ]);
        $otherPeriod = PayrollPeriod::create([
            'company_id' => $otherCompany->id,
            'period_name' => 'Other Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $otherPeriod->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_global_income_and_deduction_types_are_supported(): void
    {
        $globalIncome = IncomeType::create([
            'company_id' => null, // Global
            'code' => 'GLOBAL_INC',
            'name' => 'Global Allowance',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$globalIncome->id],
                'draft_ids' => [$this->draft1->id],
                'income_type_configs' => [
                    $globalIncome->id => ['number_input' => 1, 'rate' => 300.00],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pay_ins', [
            'pay_id' => $this->draft1->id,
            'income_type_id' => $globalIncome->id,
            'rate' => 300.00,
        ]);
    }
}
