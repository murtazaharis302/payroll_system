<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreservePayrollInputDraftGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Department $department;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected DeductionType $taxType;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $this->admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $this->company = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Preserve Input Corp',
            'company_code' => 'PRESERVE-01',
            'email' => 'preserve@corp.com',
            'status' => 1,
        ]);
        $this->department = Department::create([
            'company_id' => $this->company->id,
            'code' => 'PRESERVE',
            'name' => 'Preservation Tests',
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
     * Requirement 1 & 2: Daily-paid employee with daily_rate = 120 and entered days 10 generates:
     * number_input = 10, rate = 120, total = 1,200. Entered days are not replaced with 1.
     */
    public function test_daily_paid_employee_with_10_entered_days_generates_correct_pay_in(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-DAILY-120',
            'first_name' => 'Daily',
            'last_name' => 'Worker',
            'daily_rate' => 120.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 10,
                ],
            ],
        ]);

        $draft = $headers->first();
        $this->assertNotNull($draft);

        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->first();
        $this->assertNotNull($basicPayIn);
        $this->assertEquals(10.00, (float) $basicPayIn->number_input);
        $this->assertEquals(120.00, (float) $basicPayIn->rate);
        $this->assertEquals(1200.00, (float) $basicPayIn->amount);
    }

    /**
     * Requirement 3: Explicit entered days take priority over attendance-calculated days.
     */
    public function test_explicit_entered_days_take_priority_over_attendance_calculated_days(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-ATT-PRIORITY',
            'first_name' => 'Att',
            'last_name' => 'Priority',
            'daily_rate' => 200.00,
            'status' => 'active',
        ]);

        // Add 5 days of attendance logs
        for ($i = 1; $i <= 5; $i++) {
            $date = sprintf('2026-08-%02d', $i);
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'biometric_id' => 'BIO-' . $employee->id,
                'log_date' => $date,
                'log_time' => '08:00:00',
                'log_datetime' => $date . ' 08:00:00',
                'log_type' => 'in',
            ]);
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'biometric_id' => 'BIO-' . $employee->id,
                'log_date' => $date,
                'log_time' => '17:00:00',
                'log_datetime' => $date . ' 17:00:00',
                'log_type' => 'out',
            ]);
        }

        $service = new PayrollProcessingService();
        // Submit 10 days explicitly
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'days_worked' => 10,
                ],
            ],
        ]);

        $draft = $headers->first();
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->first();
        $this->assertEquals(10.00, (float) $basicPayIn->number_input);
        $this->assertEquals(2000.00, (float) $basicPayIn->amount);
    }

    /**
     * Requirement 4: When no manual value is entered, valid attendance-calculated days are used.
     */
    public function test_when_no_manual_value_entered_attendance_calculated_days_are_used(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-ATT-AUTO',
            'first_name' => 'Auto',
            'last_name' => 'Att',
            'daily_rate' => 150.00,
            'status' => 'active',
        ]);

        // Add 6 days of attendance logs
        for ($i = 1; $i <= 6; $i++) {
            $date = sprintf('2026-08-%02d', $i);
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'biometric_id' => 'BIO-' . $employee->id,
                'log_date' => $date,
                'log_time' => '08:00:00',
                'log_datetime' => $date . ' 08:00:00',
                'log_type' => 'in',
            ]);
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'biometric_id' => 'BIO-' . $employee->id,
                'log_date' => $date,
                'log_time' => '17:00:00',
                'log_datetime' => $date . ' 17:00:00',
                'log_type' => 'out',
            ]);
        }

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], []);

        $draft = $headers->first();
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->first();
        $this->assertEquals(6.00, (float) $basicPayIn->number_input);
        $this->assertEquals(150.00, (float) $basicPayIn->rate);
        $this->assertEquals(900.00, (float) $basicPayIn->amount);
    }

    /**
     * Requirement 5: When neither manual nor attendance data exists, the system does not silently use 1.
     */
    public function test_when_no_manual_or_attendance_exists_system_does_not_silently_use_1(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-NO-ATT',
            'first_name' => 'No',
            'last_name' => 'Logs',
            'daily_rate' => 500.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], []);

        $draft = $headers->first();
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->first();
        $this->assertNotNull($draft);
        $this->assertTrue((bool) $draft->requires_review);
        $this->assertNotEquals(1.00, (float) ($basicPayIn->number_input ?? 0.00));
    }

    /**
     * Requirement 6: Hourly-paid employees use entered or calculated hours and hourly_rate.
     */
    public function test_hourly_paid_employee_uses_entered_hours_and_hourly_rate(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-HOURLY-150',
            'first_name' => 'Hourly',
            'last_name' => 'Staff',
            'hourly_rate' => 150.00,
            'daily_rate' => 0.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [
                $employee->id => [
                    'number_input' => 40,
                ],
            ],
        ]);

        $draft = $headers->first();
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->first();
        $this->assertEquals(40.00, (float) $basicPayIn->number_input);
        $this->assertEquals(150.00, (float) $basicPayIn->rate);
        $this->assertEquals(6000.00, (float) $basicPayIn->amount);
    }

    /**
     * Requirement 7: Regenerating an editable draft uses the latest submitted payroll inputs.
     */
    public function test_regenerating_editable_draft_uses_latest_submitted_inputs(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-REGEN-01',
            'first_name' => 'Regen',
            'last_name' => 'Test',
            'daily_rate' => 120.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        // 1st Generation: 5 days
        $headers1 = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [$employee->id => ['days_worked' => 5]],
        ]);
        $draft1 = $headers1->first();
        $payIn1 = PayIn::withoutGlobalScopes()->where('pay_id', $draft1->id)->first();
        $this->assertEquals(5.00, (float) $payIn1->number_input);

        // 2nd Generation (Regeneration): 10 days
        $headers2 = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [$employee->id => ['days_worked' => 10]],
            'regenerate' => true,
        ]);
        $draft2 = PayHeader::withoutGlobalScopes()->where('payroll_period_id', $this->period->id)->where('employee_id', $employee->id)->first();
        $payIn2 = PayIn::withoutGlobalScopes()->where('pay_id', $draft2->id)->first();

        $this->assertEquals(10.00, (float) $payIn2->number_input);
        $this->assertEquals(120.00, (float) $payIn2->rate);
        $this->assertEquals(1200.00, (float) $payIn2->amount);
    }

    /**
     * Requirement 8: Approved or locked drafts cannot be regenerated.
     */
    public function test_approved_or_locked_drafts_cannot_be_regenerated(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-LOCKED-01',
            'first_name' => 'Locked',
            'last_name' => 'Draft',
            'daily_rate' => 120.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [$employee->id => ['days_worked' => 10]],
        ]);
        $draft = $headers->first();

        // Lock / approve draft
        $draft->update(['status' => 'approved']);

        // Attempt regeneration with new input of 20 days
        $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [$employee->id => ['days_worked' => 20]],
            'regenerate' => true,
        ]);

        $draft->refresh();
        $this->assertEquals('approved', $draft->status);

        $payIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->first();
        $this->assertEquals(10.00, (float) $payIn->number_input);
    }

    /**
     * Requirement 9: Editing Payroll Input updates pay_ins.number_input only.
     */
    public function test_editing_payroll_input_updates_pay_ins_number_input_only(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-EDIT-INPUT',
            'first_name' => 'Edit',
            'last_name' => 'Input',
            'daily_rate' => 120.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [$employee->id => ['days_worked' => 10]],
        ]);
        $draft = $headers->first();

        $payIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->first();

        $this->actingAs($this->admin);
        $response = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 12,
            'pay_ins' => [
                [
                    'id' => $payIn->id,
                    'number_input' => 12.00,
                ],
            ],
        ]);

        $response->assertRedirect();
        $payIn->refresh();
        $this->assertEquals(12.00, (float) $payIn->number_input);
        $this->assertEquals(120.00, (float) $payIn->rate);
        $this->assertEquals(1440.00, (float) $payIn->amount);
    }

    /**
     * Requirement 10: Employee master rates and recurring defaults remain unchanged.
     */
    public function test_employee_master_rates_remain_unchanged(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-UNCHANGED-RATE',
            'first_name' => 'Unchanged',
            'last_name' => 'Rate',
            'daily_rate' => 120.00,
            'monthly_rate' => 0.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], [
            'payroll_inputs' => [$employee->id => ['days_worked' => 10]],
        ]);
        $draft = $headers->first();

        $this->actingAs($this->admin);
        $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                [
                    'id' => $draft->payIns->first()->id,
                    'number_input' => 15.00,
                ],
            ],
        ]);

        $employee->refresh();
        $this->assertEquals(120.00, (float) $employee->daily_rate);
    }
}
