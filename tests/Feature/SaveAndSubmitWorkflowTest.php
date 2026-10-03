<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaveAndSubmitWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_submit_wf@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Submit Workflow Corp',
            'company_code' => 'SUBMITWF',
            'email' => 'submitwf@corp.com',
            'status' => '1',
        ]);

        session(['active_company_id' => $this->company->id]);
        $this->actingAs($this->adminUser);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);
    }

    /**
     * Test 1: Edit values + Save & Submit in one action -> values persisted, totals recalculated, status pending_approval.
     */
    public function test_edit_values_and_save_and_submit_in_one_action(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-SUB-ONESTOP',
            'first_name' => 'Submit',
            'last_name' => 'OneStop',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [
                ['income_type' => 'BASIC', 'display' => 1],
                ['income_type' => 'OVERTIME', 'display' => 1],
            ],
            'deductions' => [
                ['deduction_type' => 'TAX', 'display' => 1],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $otPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->firstOrFail();

        // Edit Overtime number_input to 10.0 and Submit
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
            'pay_ins' => [
                ['id' => $otPayIn->id, 'number_input' => 10.0, 'rate' => 200.00],
            ],
        ]);

        $response->assertRedirect(route('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));
        $response->assertSessionHas('status', 'Payroll draft saved and submitted for approval successfully.');

        $draft->refresh();
        $otPayIn->refresh();

        $this->assertEquals('pending_approval', $draft->status);
        $this->assertNotNull($draft->submitted_at);
        $this->assertEquals($this->adminUser->id, $draft->submitted_by);
        $this->assertEquals(10.0, (float) $otPayIn->number_input);
    }

    /**
     * Test 2a: Income changed + statutory deduction untouched on submit -> statutory amount recalculates.
     */
    public function test_income_changed_and_statutory_untouched_on_submit_recalculates_statutory_deduction(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-STAT-RECALC',
            'first_name' => 'Stat',
            'last_name' => 'Recalc',
            'monthly_rate' => 60000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1]],
            'deductions' => [['deduction_type' => 'TAX', 'display' => 1]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))->firstOrFail();
        $taxPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('deductionType', fn($q) => $q->where('code', 'TAX'))->firstOrFail();
        $initialTax = (float) $taxPayOut->rate;

        // Lower Basic Pay rate to 10,000.00 while submitting untouched taxPayOut rate
        $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 1.0, 'rate' => 10000.00],
            ],
            'pay_outs' => [
                ['id' => $taxPayOut->id, 'rate' => $initialTax],
            ],
        ]);

        $draft->refresh();
        $taxPayOut->refresh();

        $this->assertEquals('pending_approval', $draft->status);
        $this->assertLessThan($initialTax, (float) $taxPayOut->rate);
    }

    /**
     * Test 2b: Statutory deduction explicitly edited on submit -> user override survives submission.
     */
    public function test_statutory_deduction_explicitly_edited_on_submit_user_override_survives(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-STAT-OVERRIDE',
            'first_name' => 'Stat',
            'last_name' => 'Override',
            'monthly_rate' => 50000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1]],
            'deductions' => [['deduction_type' => 'TAX', 'display' => 1]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $taxPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('deductionType', fn($q) => $q->where('code', 'TAX'))->firstOrFail();

        // Explicitly edit Tax to 999.99 and Submit
        $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
            'pay_outs' => [
                ['id' => $taxPayOut->id, 'rate' => 999.99],
            ],
        ]);

        $draft->refresh();
        $taxPayOut->refresh();

        $this->assertEquals('pending_approval', $draft->status);
        $this->assertEquals(999.99, (float) $taxPayOut->rate);
    }

    /**
     * Test 3: Validation Failure - Unhandled requires_review prevents submission.
     */
    public function test_unhandled_requires_review_prevents_submission(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REV-SUB',
            'first_name' => 'Review',
            'last_name' => 'Sub',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $draft->update([
            'requires_review' => true,
            'review_reason' => 'Missing attendance logs for employee.',
        ]);

        $response = $this->putJson(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
        ]);

        $response->assertStatus(422);

        $draft->refresh();
        $this->assertEquals('draft', $draft->status);
    }

    /**
     * Test 3b: Review flag due to missing loan deductions can be submitted with confirmed override reason.
     */
    public function test_requires_review_due_to_missing_loans_submits_with_confirmed_override_reason(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REV-LOANS',
            'first_name' => 'Review',
            'last_name' => 'Loans',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $draft->update([
            'requires_review' => true,
            'review_reason' => 'Selected loan deductions skipped due to missing active loans: SSS Loan',
        ]);

        // Submit with confirmed override_reason
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
            'override_reason' => 'Employee has no active loans; skipped loan deductions confirmed.',
        ]);

        $response->assertRedirect(route('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $draft->refresh();
        $this->assertEquals('pending_approval', $draft->status);
        $this->assertFalse((bool) $draft->requires_review);
        $this->assertNull($draft->review_reason);
        $this->assertEquals('Employee has no active loans; skipped loan deductions confirmed.', $draft->attendance_override_reason);
    }

    /**
     * Test 4: Validation Failure - Negative net pay prevents submission.
     */
    public function test_negative_net_pay_prevents_submission(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-NEG-SUB',
            'first_name' => 'Neg',
            'last_name' => 'Sub',
            'monthly_rate' => 10000.00,
            'status' => 'active',
        ]);

        $customDed = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'BIG_DED',
            'name' => 'Big Fee',
            'category' => 'other',
            'is_active' => true,
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [['income_type' => 'BASIC', 'display' => 1]],
            'deductions' => [['deduction_type' => 'BIG_DED', 'display' => 1, 'amount' => 50000.00]],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $response = $this->putJson(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
        ]);

        $response->assertStatus(422);

        $draft->refresh();
        $this->assertEquals('draft', $draft->status);
    }

    /**
     * Test 5: Double Submission Prevention - Draft already in pending_approval cannot be submitted twice.
     */
    public function test_already_submitted_draft_cannot_be_submitted_twice(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DOUBLE-SUB',
            'first_name' => 'Double',
            'last_name' => 'Sub',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $draft->update(['status' => 'pending_approval']);

        $response = $this->putJson(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 6: Security - Unauthorized user cannot submit another company's draft.
     */
    public function test_unauthorized_user_cannot_submit_cross_tenant_draft(): void
    {
        $otherCompany = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Other Security Corp',
            'company_code' => 'SEC2',
            'email' => 'sec2@corp.com',
            'status' => '1',
        ]);

        $otherDept = Department::create([
            'company_id' => $otherCompany->id,
            'code' => 'SEC2ENG',
            'name' => 'Engineering 2',
            'is_active' => true,
        ]);

        $otherEmployee = EmployeeMaster::create([
            'company_id' => $otherCompany->id,
            'dept_id' => $otherDept->id,
            'employee_code' => 'EMP-SEC2',
            'first_name' => 'Sec2',
            'last_name' => 'Emp',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $otherPeriod = PayrollPeriod::create([
            'company_id' => $otherCompany->id,
            'period_name' => 'August 2026 Cutoff 2',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($otherPeriod, [$otherEmployee->id]);
        $draft2 = $headers->first();

        $comp1User = User::create([
            'name' => 'Comp1 Admin',
            'email' => 'comp1admin_sec@test.com',
            'password' => Hash::make('password'),
            'role' => 'company_admin',
            'role_id' => 2,
            'company_id' => $this->company->id,
        ]);

        session(['active_company_id' => $this->company->id]);
        $this->actingAs($comp1User);

        // CompanyAdmin of Company 1 attempts to submit $draft2 of $otherCompany
        $response = $this->putJson(route('admin.payroll-drafts.update', $draft2->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 7: Approved/Locked draft cannot be edited or submitted.
     */
    public function test_approved_or_locked_payroll_remains_protected(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-LOCKED-SUB',
            'first_name' => 'Locked',
            'last_name' => 'Sub',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $draft->update(['status' => 'approved']);

        $response = $this->putJson(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'submit_after_save' => '1',
        ]);

        $response->assertStatus(422);
    }
}
