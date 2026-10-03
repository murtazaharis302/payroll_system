<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\HRAdjustment;
use App\Models\IncomeType;
use App\Models\DeductionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HRAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected IncomeType $incomeType;
    protected DeductionType $deductionType;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'id' => Role::SUPER_ADMIN,
            'name' => 'super-admin',
            'display_name' => 'Super Admin',
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $role->id,
            'role' => 'company',
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'company_code' => 'COMP-002',
            'name' => 'Test HR Company',
            'email' => 'hr@example.com',
            'phone' => '123-456-7890',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-101',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);

        $this->incomeType = IncomeType::create([
            'name' => 'Performance Bonus',
            'code' => 'BONUS',
        ]);

        $this->deductionType = DeductionType::create([
            'name' => 'Uniform Allowance Penalty',
            'code' => 'UNIFORM',
        ]);
    }

    /**
     * Test navigating to company/hr-adjustments renders the Blade UI.
     */
    public function test_company_hr_adjustments_index_renders_blade_view(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('company.hr-adjustments.index'));

        $response->assertStatus(200);
        $response->assertViewIs('company.hr-adjustments.index');
        $response->assertSee('HR Adjustments', false);
        $response->assertSee('Alice Smith');
    }

    /**
     * Test JSON requests return paginated JSON payload.
     */
    public function test_json_request_returns_json_response(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson(route('company.hr-adjustments.index'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page']);
    }

    /**
     * Test submitting an attendance adjustment correction via web form.
     */
    public function test_can_create_attendance_adjustment(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('company.hr-adjustments.store'), [
            'employee_id' => $this->employee->id,
            'type' => 'attendance',
            'effective_date' => '2026-07-20',
            'hours_worked' => 8.00,
            'overtime_hours' => 2.50,
            'description' => 'Approved overtime for shift extension',
            'status' => 'approved',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('hr_adjustments', [
            'employee_id' => $this->employee->id,
            'adjustment_type' => 'attendance',
            'hours_worked' => 8.00,
            'overtime_hours' => 2.50,
            'status' => 'approved',
        ]);
    }

    /**
     * Test submitting an income adjustment via web form.
     */
    public function test_can_create_income_adjustment(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('company.hr-adjustments.store'), [
            'employee_id' => $this->employee->id,
            'type' => 'income',
            'income_type_id' => $this->incomeType->id,
            'effective_date' => '2026-07-20',
            'amount' => 1500.00,
            'description' => 'Special sales milestone reward',
            'status' => 'approved',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('hr_adjustments', [
            'employee_id' => $this->employee->id,
            'adjustment_type' => 'income',
            'income_type_id' => $this->incomeType->id,
            'amount' => 1500.00,
        ]);
    }

    /**
     * Test approving a pending HR adjustment.
     */
    public function test_can_approve_pending_adjustment(): void
    {
        $adj = HRAdjustment::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'adjustment_type' => 'attendance',
            'effective_date' => '2026-07-20',
            'reason' => 'Pending tardiness reversal',
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('company.hr-adjustments.approve', $adj->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('hr_adjustments', [
            'id' => $adj->id,
            'status' => 'approved',
        ]);
    }
}
