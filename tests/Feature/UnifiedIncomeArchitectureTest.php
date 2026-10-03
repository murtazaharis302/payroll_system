<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\DeductionType;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedIncomeArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $adminUser;
    protected PayrollPeriod $period;
    protected Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@unified.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Unified Income Corp',
            'email' => 'corp@unified.test',
            'code' => 'UIC',
            'user_id' => $this->adminUser->id,
            'is_active' => true,
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '1st Half Sep 2026',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'pay_date' => '2026-09-20',
            'frequency' => 'semi-monthly',
            'status' => 'open',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'is_active' => true,
        ]);

        PayrollPolicy::create([
            'company_id' => $this->company->id,
            'sss_calc_type' => 'table',
            'philhealth_calc_type' => 'percentage',
            'philhealth_rate' => 5.00,
            'pagibig_calc_type' => 'standard',
            'tax_calc_type' => 'table',
            'workdays_per_month' => 26,
            'hours_per_day' => 8,
            'no_attendance_policy' => 'fixed_cutoff_salary',
        ]);
    }

    public function test_creating_employee_auto_syncs_basic_emp_income(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@unified.test',
            'monthly_rate' => 35000.00,
            'status' => 'active',
        ]);

        $basicType = IncomeType::findBySystemKey('basic', $this->company->id);
        $this->assertNotNull($basicType);

        $empIncome = EmpIncome::where('employee_id', $employee->id)
            ->where('income_type_id', $basicType->id)
            ->first();

        $this->assertNotNull($empIncome);
        $this->assertEquals(35000.00, (float) $empIncome->rate);
    }

    public function test_updating_basic_emp_income_syncs_employee_master_base_rates(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-002',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@unified.test',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $basicType = IncomeType::findBySystemKey('basic', $this->company->id);
        $this->assertNotNull($basicType);

        $empIncome = EmpIncome::where('employee_id', $employee->id)
            ->where('income_type_id', $basicType->id)
            ->firstOrFail();

        $empIncome->update(['rate' => 52000.00, 'amount' => 52000.00]);

        $employee->refresh();

        $this->assertEquals(52000.00, (float) $employee->monthly_rate);
        $this->assertEquals(2000.00, (float) $employee->daily_rate);
        $this->assertEquals(250.00, (float) $employee->hourly_rate);
    }

    public function test_employee_creation_via_form_with_incomes_array_persists_emp_incomes_and_derives_rates(): void
    {
        $this->actingAs($this->adminUser);

        $basicType = IncomeType::findBySystemKey('basic', $this->company->id);
        $allowanceType = IncomeType::where('code', 'ALLOWANCE')->first();
        if (!$allowanceType) {
            $allowanceType = IncomeType::create([
                'company_id' => $this->company->id,
                'name' => 'Allowance',
                'code' => 'ALLOWANCE',
                'is_taxable' => false,
                'is_active' => true,
            ]);
        }

        $response = $this->post(panelRoute('payroll-employees.store'), [
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-NEW-FORM',
            'first_name' => 'Form',
            'last_name' => 'Created',
            'email' => 'formcreated@unified.test',
            'status' => 'active',
            'incomes' => [
                ['income_type_id' => $basicType->id, 'amount' => 39000.00],
                ['income_type_id' => $allowanceType->id, 'amount' => 2500.00],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-employees.index'));

        $employee = EmployeeMaster::where('employee_code', 'UIC-NEW-FORM')->firstOrFail();
        $this->assertEquals(39000.00, (float) $employee->monthly_rate);
        $this->assertEquals(1500.00, (float) $employee->daily_rate);
        $this->assertEquals(187.50, (float) $employee->hourly_rate);

        $this->assertDatabaseHas('emp_income', [
            'employee_id' => $employee->id,
            'income_type_id' => $basicType->id,
            'rate' => 39000.00,
            'frequency' => 'monthly',
        ]);
        $this->assertDatabaseHas('emp_income', [
            'employee_id' => $employee->id,
            'income_type_id' => $allowanceType->id,
            'rate' => 2500.00,
            'frequency' => 'monthly',
        ]);
    }

    public function test_employee_creation_preserves_proper_income_frequency(): void
    {
        $this->actingAs($this->adminUser);

        $basicType = IncomeType::findBySystemKey('basic', $this->company->id);
        $otType = IncomeType::findBySystemKey('overtime', $this->company->id);

        $response = $this->post(panelRoute('payroll-employees.store'), [
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-FREQ-TEST',
            'first_name' => 'Freq',
            'last_name' => 'Tester',
            'email' => 'freq@unified.test',
            'status' => 'active',
            'incomes' => [
                ['income_type_id' => $basicType->id, 'amount' => 30000.00],
                ['income_type_id' => $otType->id, 'amount' => 200.00],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-employees.index'));

        $employee = EmployeeMaster::where('employee_code', 'UIC-FREQ-TEST')->firstOrFail();

        $basicIncome = EmpIncome::where('employee_id', $employee->id)->where('income_type_id', $basicType->id)->firstOrFail();
        $this->assertEquals('monthly', $basicIncome->frequency);

        $otIncome = EmpIncome::where('employee_id', $employee->id)->where('income_type_id', $otType->id)->firstOrFail();
        $this->assertEquals('per_occurrence', $otIncome->frequency);
        $this->assertEquals(200.00, (float) $otIncome->rate);
    }

    public function test_employee_creation_rejects_income_type_from_another_company(): void
    {
        $this->actingAs($this->adminUser);

        $otherCompany = Company::create([
            'name' => 'Other Corp',
            'email' => 'other@corp.test',
            'code' => 'OTH',
            'user_id' => $this->adminUser->id,
            'is_active' => true,
        ]);

        $otherPrivateIncome = IncomeType::create([
            'company_id' => $otherCompany->id,
            'name' => 'Private Perk',
            'code' => 'PRIV_PERK',
            'is_active' => true,
        ]);

        $response = $this->post(panelRoute('payroll-employees.store'), [
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-SCOPE-FAIL',
            'first_name' => 'Scope',
            'last_name' => 'Fail',
            'email' => 'scopefail@unified.test',
            'status' => 'active',
            'incomes' => [
                ['income_type_id' => $otherPrivateIncome->id, 'amount' => 5000.00],
            ],
        ]);

        $response->assertSessionHasErrors(['incomes']);
        $this->assertDatabaseMissing('employees_master', ['employee_code' => 'UIC-SCOPE-FAIL']);
    }

    public function test_overtime_rate_from_emp_income_is_authoritatively_used_in_payroll(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-006',
            'first_name' => 'Overtime',
            'last_name' => 'Specialist',
            'email' => 'ot@unified.test',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $otType = IncomeType::findBySystemKey('overtime', $this->company->id);
        $this->assertNotNull($otType);

        // Configure custom Overtime rate in EmpIncome (e.g. 300.00/hour)
        EmpIncome::updateOrCreate(
            ['employee_id' => $employee->id, 'income_type_id' => $otType->id],
            ['rate' => 300.00, 'amount' => 300.00, 'frequency' => 'per_occurrence', 'is_active' => true]
        );

        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$employee->id], [
            'days_worked' => [$employee->id => 13],
            'overtime_hours' => [$employee->id => 10],
        ]);

        $draft = $drafts->first();
        $this->assertNotNull($draft);

        // 10 OT hours * 300.00/hr = 3000.00
        $this->assertEquals(3000.00, (float) $draft->overtime_pay);
    }

    public function test_overtime_hours_without_active_rate_flags_draft_for_review(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-NO-OT-RATE',
            'first_name' => 'No',
            'last_name' => 'OtRate',
            'email' => 'nootrate@unified.test',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $otType = IncomeType::findBySystemKey('overtime', $this->company->id);
        $this->assertNotNull($otType);

        // Explicitly deactivate or set rate to 0.00 for OVERTIME
        EmpIncome::updateOrCreate(
            ['employee_id' => $employee->id, 'income_type_id' => $otType->id],
            ['rate' => 0.00, 'amount' => 0.00, 'frequency' => 'per_occurrence', 'is_active' => false]
        );

        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$employee->id], [
            'days_worked' => [$employee->id => 13],
            'overtime_hours' => [$employee->id => 8],
        ]);

        $draft = $drafts->first();
        $this->assertNotNull($draft);

        // Overtime pay is 0.00 and draft must be flagged for review due to missing active OT rate
        $this->assertEquals(0.00, (float) $draft->overtime_pay);
        $this->assertTrue((bool) $draft->requires_review);
        $this->assertStringContainsString('Overtime hours recorded', (string) $draft->review_reason);
        $this->assertStringContainsString('no active Overtime rate configured', (string) $draft->review_reason);
    }

    public function test_quick_record_update_validates_uniqueness_for_code_and_email(): void
    {
        $this->actingAs($this->adminUser);

        $emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-UNIQUE-1',
            'first_name' => 'Emp',
            'last_name' => 'One',
            'email' => 'unique1@unified.test',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        $emp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-UNIQUE-2',
            'first_name' => 'Emp',
            'last_name' => 'Two',
            'email' => 'unique2@unified.test',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        // Attempt to update emp2 with emp1's email
        $response = $this->postJson(route('admin.payroll-employees.update-record', $emp2->id), [
            'first_name' => 'Emp',
            'last_name' => 'Two Updated',
            'email' => 'unique1@unified.test',
            'dept_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        // Attempt to update emp2 with emp1's employee code
        $responseCode = $this->postJson(route('admin.payroll-employees.update-record', $emp2->id), [
            'first_name' => 'Emp',
            'last_name' => 'Two Updated',
            'employee_code' => 'UIC-UNIQUE-1',
            'dept_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $responseCode->assertStatus(422);
        $responseCode->assertJsonValidationErrors(['employee_code']);
    }

    public function test_overtime_review_warning_disappears_when_recurring_ot_rate_is_configured_and_recalculated(): void
    {
        $this->actingAs($this->adminUser);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'UIC-OT-RESOLVE',
            'first_name' => 'Resolve',
            'last_name' => 'Ot',
            'email' => 'resolveot@unified.test',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $otType = IncomeType::findBySystemKey('overtime', $this->company->id);
        $this->assertNotNull($otType);

        // Step 1: Explicitly deactivate OVERTIME EmpIncome and generate draft with 8 OT hours -> flags review
        EmpIncome::updateOrCreate(
            ['employee_id' => $employee->id, 'income_type_id' => $otType->id],
            ['rate' => 0.00, 'amount' => 0.00, 'frequency' => 'per_occurrence', 'is_active' => false]
        );

        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$employee->id], [
            'days_worked' => [$employee->id => 13],
            'overtime_hours' => [$employee->id => 8],
        ]);

        $draft = $drafts->first();
        $this->assertNotNull($draft);
        $this->assertTrue((bool) $draft->requires_review);
        $this->assertStringContainsString('Overtime hours recorded', (string) $draft->review_reason);
        $this->assertEquals(0.00, (float) $draft->overtime_pay);

        // Step 2: Configure valid authoritative Overtime rate in Recurring Income
        EmpIncome::updateOrCreate(
            ['employee_id' => $employee->id, 'income_type_id' => $otType->id],
            ['rate' => 250.00, 'amount' => 250.00, 'frequency' => 'per_occurrence', 'is_active' => true]
        );

        // Step 3: Recalculate / save draft with 8 OT hours
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 13,
            'overtime_hours' => 8,
        ]);

        $response->assertSessionHasNoErrors();

        $draft->refresh();

        // Step 4: Missing OT review warning must disappear and OT pay calculated from EmpIncome rate
        $this->assertFalse((bool) $draft->requires_review);
        $this->assertNull($draft->review_reason);
        $this->assertEquals(2000.00, (float) $draft->overtime_pay); // 8 hours * 250.00
    }
}
