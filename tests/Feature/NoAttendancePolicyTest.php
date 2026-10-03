<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\HRAdjustment;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoAttendancePolicyTest extends TestCase
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

    public function test_daily_employee_with_zero_attendance_generates_zero_pay_and_requires_review(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Zero Attendance Corp',
            'company_code' => 'ZERO-001',
            'email' => 'zero@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-ZERO-01',
            'first_name' => 'Zero',
            'last_name' => 'Logs',
            'email' => 'zerologs@test.com',
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

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        // Zero-pay verification: quantity 0, basic_pay 0, requires_review true
        $this->assertTrue((bool) $draft->requires_review);
        $this->assertStringContainsString('No attendance logs', $draft->review_reason);
        $this->assertEquals(0.00, (float) $draft->basic_pay);
        $this->assertEquals(0.00, (float) $draft->total_gross_pay);

        $basicPayIn = $draft->payIns()->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))->first();
        $this->assertEquals(0.0, (float) $basicPayIn->number_input);
        $this->assertEquals(0.00, (float) $basicPayIn->amount);
    }

    public function test_partial_attendance_logs_generate_exact_proportional_pay(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Partial Attendance Corp',
            'company_code' => 'PART-001',
            'email' => 'partial@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-PART-01',
            'first_name' => 'Partial',
            'last_name' => 'Worker',
            'email' => 'partialworker@test.com',
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

        // Add 3 days of valid attendance logs (8h each)
        foreach (['2026-08-01', '2026-08-02', '2026-08-03'] as $date) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'biometric_id' => $employee->employee_code,
                'log_date' => $date,
                'log_time' => '08:00:00',
                'log_datetime' => $date . ' 08:00:00',
                'log_type' => 'in',
            ]);
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'biometric_id' => $employee->employee_code,
                'log_date' => $date,
                'log_time' => '17:00:00',
                'log_datetime' => $date . ' 17:00:00',
                'log_type' => 'out',
            ]);
        }

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        $this->assertFalse((bool) $draft->requires_review);
        $this->assertEquals(3000.00, (float) $draft->basic_pay);

        $basicPayIn = $draft->payIns()->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))->first();
        $this->assertEquals(3.0, (float) $basicPayIn->number_input);
        $this->assertEquals(3000.00, (float) $basicPayIn->amount);
    }

    public function test_overnight_shifts_calculate_correct_worked_hours(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Overnight Shift Corp',
            'company_code' => 'NIGHT-001',
            'email' => 'night@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-NIGHT-01',
            'first_name' => 'Night',
            'last_name' => 'ShiftWorker',
            'email' => 'nightworker@test.com',
            'hourly_rate' => 100.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        // Overnight shift: IN 22:00, OUT 06:00 (8h gross - 1h lunch = 7h net worked)
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'biometric_id' => $employee->employee_code,
            'log_date' => '2026-08-01',
            'log_time' => '22:00:00',
            'log_datetime' => '2026-08-01 22:00:00',
            'log_type' => 'in',
        ]);
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'biometric_id' => $employee->employee_code,
            'log_date' => '2026-08-01',
            'log_time' => '06:00:00',
            'log_datetime' => '2026-08-02 06:00:00',
            'log_type' => 'out',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        // 7 hours net worked * 100.00/hr = 700.00
        $this->assertEquals(700.00, (float) $draft->basic_pay);
    }

    public function test_approved_hr_adjustments_override_raw_logs(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'HR Adj Corp',
            'company_code' => 'ADJ-001',
            'email' => 'adj@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-ADJ-01',
            'first_name' => 'HR',
            'last_name' => 'Adjusted',
            'email' => 'hradj@test.com',
            'hourly_rate' => 100.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        // Approved HR Adjustment for 2026-08-01: 8.0 hours
        HRAdjustment::create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'adjustment_type' => 'attendance',
            'attendance_date' => '2026-08-01',
            'effective_date' => '2026-08-01',
            'hours_worked' => 8.00,
            'overtime_hours' => 2.00,
            'reason' => 'Approved adjustment per supervisor',
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        // 8 hours basic * 100 = 800.00
        $this->assertEquals(800.00, (float) $draft->basic_pay);
    }

    public function test_authorized_manual_override_clears_review_flag_and_records_audit(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Audit Override Corp',
            'company_code' => 'AUD-001',
            'email' => 'audit@corp.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-AUD-01',
            'first_name' => 'Audit',
            'last_name' => 'OverrideWorker',
            'email' => 'auditworker@test.com',
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

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        $this->assertTrue((bool) $draft->requires_review);

        $this->actingAs($admin);
        $response = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 10.0,
            'basic_pay' => 10000.00,
            'override_reason' => 'Approved physical timesheet per HR manager #102',
        ]);

        $response->assertSessionHasNoErrors();
        $draft->refresh();

        $this->assertFalse((bool) $draft->requires_review);
        $this->assertNull($draft->review_reason);
        $this->assertEquals('Approved physical timesheet per HR manager #102', $draft->attendance_override_reason);
        $this->assertEquals($admin->id, $draft->attendance_override_by);
        $this->assertNotNull($draft->attendance_override_at);
        $this->assertEquals(10000.00, (float) $draft->basic_pay);
    }

    public function test_fixed_salary_policy_when_enabled_generates_cutoff_days(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Fixed Salary Policy Corp',
            'company_code' => 'FIXPOL-001',
            'email' => 'fixpol@corp.com',
            'status' => 1,
        ]);

        PayrollPolicy::create([
            'company_id' => $company->id,
            'policy_name' => 'Fixed Cutoff Policy',
            'workdays_per_month' => 26.00,
            'hours_per_day' => 8.00,
            'no_attendance_policy' => 'fixed_cutoff_salary',
            'effective_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-FIXPOL-01',
            'first_name' => 'Fixed',
            'last_name' => 'PolicyStaff',
            'email' => 'fixedstaff@test.com',
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

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        // Under fixed_cutoff_salary policy: 26 / 2 = 13 cutoff days * 1000 = 13000.00
        $this->assertFalse((bool) $draft->requires_review);
        $this->assertEquals(13000.00, (float) $draft->basic_pay);
    }
}
