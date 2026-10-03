<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\DeductionType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnsavedChangesProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User UnsavedTest',
            'email' => 'admin_unsaved@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Unsaved Test Corp',
            'company_code' => 'UNSAVEDTEST',
            'email' => 'unsaved@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'HR',
            'name' => 'Human Resources',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Payroll Draft Edit view includes the unsaved changes protection modal and dirty tracking scripts.
     */
    public function test_payroll_draft_edit_renders_unsaved_changes_protection_modal_and_dirty_tracking(): void
    {
        $period = \App\Models\PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '1st Half Aug 2026',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'cut_off_start' => '2026-08-01',
            'cut_off_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DRAFT-01',
            'first_name' => 'Sara',
            'last_name' => 'Draft',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        $draft = \App\Models\PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $emp->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'days_absent' => 0.0,
            'overtime_hours' => 0.0,
            'late_hours' => 0.0,
            'total_gross_pay' => 12500.00,
            'total_deductions' => 1000.00,
            'net_pay' => 11500.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(panelRoute('payroll-drafts.edit', ['id' => $draft->id]));

        $response->assertStatus(200);
        $response->assertSee('id="unsavedChangesModal"', false);
        $response->assertSee('isDraftFormDirty', false);
        $response->assertSee('btnUnsavedSave', false);
        $response->assertSee('btnUnsavedDiscard', false);
        $response->assertSee('btnUnsavedCancel', false);
        $response->assertSee("e.target.closest('a')", false);
        $response->assertSee("window.addEventListener('beforeunload'", false);
    }

    /**
     * Test 2: Payroll Draft update via AJAX JSON path persists edits and returns JSON success response.
     */
    public function test_payroll_draft_update_json_ajax_path_persists_edits_and_returns_success(): void
    {
        $period = \App\Models\PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '1st Half Aug 2026',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'cut_off_start' => '2026-08-01',
            'cut_off_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DRAFT-01',
            'first_name' => 'Alice',
            'last_name' => 'Draft',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        $draft1 = \App\Models\PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $emp1->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 12500.00,
            'total_deductions' => 1000.00,
            'net_pay' => 11500.00,
        ]);

        $payload = [
            'days_worked' => 12.0,
        ];

        $response = $this->actingAs($this->adminUser)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->put(panelRoute('payroll-drafts.update', ['id' => $draft1->id]), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'draft_id' => $draft1->id,
            'payroll_period_id' => $period->id,
        ]);

        $this->assertDatabaseHas('pay_headers', [
            'id' => $draft1->id,
            'days_worked' => 12.0,
        ]);
    }
}
