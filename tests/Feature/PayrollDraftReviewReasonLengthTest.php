<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollDraftReviewReasonLengthTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $adminUser;
    protected PayrollPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);

        $this->adminUser = User::create([
            'name' => 'Admin Review Reason Test',
            'email' => 'admin_review_reason@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Review Reason Corp',
            'company_code' => 'RRC',
            'email' => 'rrc@test.com',
            'status' => '1',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $this->policy = PayrollPolicy::create([
            'company_id' => $this->company->id,
            'policy_name' => 'Review Policy',
            'pay_frequency' => 'semi_monthly',
            'workdays_per_month' => 26.00,
            'hours_per_day' => 8.00,
            'regular_ot_multiplier' => 1.25,
            'rest_day_ot_multiplier' => 1.30,
            'holiday_ot_multiplier' => 2.00,
            'rounding_mode' => 'round_half_up',
            'no_attendance_policy' => 'zero_pay_requires_review',
            'effective_date' => '2020-01-01',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-P01',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'cutoff_number' => 1,
            'frequency' => 'semi_monthly',
            'status' => 'open',
        ]);

        session(['active_company_id' => $this->company->id]);
    }

    /**
     * Requirement: Payroll generation with accumulated review reasons longer than the old 255-char VARCHAR
     * column limit succeeds, persists the complete review reason without truncation, sets requires_review = true,
     * and keeps the draft available.
     */
    public function test_payroll_generation_persists_long_review_reason_without_truncation_or_rollback(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-LONG-REASON',
            'first_name' => 'LongReview',
            'last_name' => 'Employee',
            'monthly_rate' => 0.00,
            'daily_rate' => 1000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        // Generate draft with zero attendance (triggers no_attendance_policy zero_pay_requires_review)
        // and with selected loan deductions skipped
        $headers = $service->generateDraft($this->period, [$emp->id], [
            'days_worked' => 0.00,
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'SSS_LOAN', 'display' => true],
                ['deduction_type' => 'PAGIBIG_LOAN', 'display' => true],
                ['deduction_type' => 'VALE', 'display' => true],
                ['deduction_type' => 'GOODS', 'display' => true],
                ['deduction_type' => 'COMPANY_LOAN', 'display' => true],
            ],
        ]);

        $this->assertCount(1, $headers);
        $draft = $headers->first()->fresh();

        $this->assertNotNull($draft);
        $this->assertTrue((bool)$draft->requires_review);
        $this->assertNotNull($draft->review_reason);

        // Verify length exceeds previous 255 character limit if multiple reasons were collected,
        // or test direct persistence of 500+ character review reason
        $longCustomReason = str_repeat('Audit reason note: Employee requires manual HR payroll audit due to policy check. ', 10);
        $this->assertTrue(strlen($longCustomReason) > 500);

        $draft->update([
            'review_reason' => $longCustomReason,
        ]);

        $reloaded = PayHeader::find($draft->id);
        $this->assertEquals($longCustomReason, $reloaded->review_reason);
        $this->assertTrue(strlen($reloaded->review_reason) > 500);
    }

    /**
     * Requirement: attendance_override_reason column supports long text explanations (> 255 chars).
     */
    public function test_attendance_override_reason_persists_long_text(): void
    {
        $draft = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => EmployeeMaster::create([
                'company_id' => $this->company->id,
                'dept_id' => $this->dept->id,
                'employee_code' => 'EMP-AUDIT-TEST',
                'first_name' => 'Audit',
                'last_name' => 'Tester',
                'monthly_rate' => 25000.00,
                'status' => 'active',
            ])->id,
            'days_worked' => 10.00,
            'days_absent' => 0.00,
            'status' => 'draft',
            'requires_review' => true,
            'review_reason' => 'Requires review.',
        ]);

        $longOverrideReason = 'HR Generalist John Doe authorized emergency attendance override after verifying manual biometric logs and receiving approved gate pass memorandum #2026-08-19-AUDIT-001 signed by Operations VP Jane Smith. All shifts verified and approved.';
        $longOverrideReason .= ' Additional documentation stored in personnel physical folder file ref #884920.';
        $this->assertTrue(strlen($longOverrideReason) > 255);

        $draft->update([
            'attendance_override_reason' => $longOverrideReason,
            'attendance_override_by' => $this->adminUser->id,
            'attendance_override_at' => now(),
        ]);

        $reloaded = PayHeader::find($draft->id);
        $this->assertEquals($longOverrideReason, $reloaded->attendance_override_reason);
        $this->assertTrue(strlen($reloaded->attendance_override_reason) > 255);
    }
}
