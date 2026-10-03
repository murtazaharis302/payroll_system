<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollLineCalculator;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExactNumberInputFixedRateCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected \App\Models\Department $dept;
    protected User $adminUser;
    protected PayrollLineCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin_c07@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company C07 Test',
            'email' => 'comp_c07@test.com',
            'status' => '1',
        ]);
        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'code' => 'TEST_DEPT',
            'name' => 'Test Department',
            'is_active' => true,
        ]);

        $this->calculator = new PayrollLineCalculator();
    }

    /**
     * Permanent Example 1: Bi-monthly employee
     * Stored cutoff rate: 10,000
     * Payroll number input: 1
     * 1 × 10,000 = 10,000
     */
    public function test_bimonthly_cutoff_rate_calculation(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-BIMONTHLY-001',
            'first_name' => 'BiMonthly',
            'last_name' => 'Worker',
            'status' => 'active',
            'monthly_rate' => 10000.00,
        ]);

        // When statutoryPeriodFactor is 1.0 (cutoff rate is 10,000 and number_input = 1)
        $line = $this->calculator->calculateBasicPay($employee, 1.0, null, 1.0);

        $this->assertEquals(1.00, (float) $line['number_input']);
        $this->assertEquals(10000.00, (float) $line['rate']);
        $this->assertEquals(10000.00, (float) $line['amount']);
        $this->assertEquals(round($line['number_input'] * $line['rate'], 2), (float) $line['amount']);
    }

    /**
     * Permanent Example 2: Daily employee
     * Daily rate: 500
     * Days worked: 15
     * 15 × 500 = 7,500
     */
    public function test_daily_employee_rate_calculation(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-DAILY-001',
            'first_name' => 'Daily',
            'last_name' => 'Worker',
            'status' => 'active',
            'daily_rate' => 500.00,
        ]);

        $line = $this->calculator->calculateBasicPay($employee, 15.0, null, 2.0);

        $this->assertEquals(15.00, (float) $line['number_input']);
        $this->assertEquals(500.00, (float) $line['rate']);
        $this->assertEquals(7500.00, (float) $line['amount']);
        $this->assertEquals(round($line['number_input'] * $line['rate'], 2), (float) $line['amount']);
    }

    /**
     * Permanent Example 3: Monthly employee paid twice monthly
     * Stored monthly rate: 20,000
     * Payroll number input: 0.5
     * 0.5 × 20,000 = 10,000
     */
    public function test_monthly_employee_half_month_calculation(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-MONTHLY-001',
            'first_name' => 'Monthly',
            'last_name' => 'Worker',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);

        // Standard semi-monthly draft generation passing statutoryPeriodFactor = 2.0
        $line = $this->calculator->calculateBasicPay($employee, 1.0, null, 2.0);

        $this->assertEquals(0.50, (float) $line['number_input']);
        $this->assertEquals(20000.00, (float) $line['rate']);
        $this->assertEquals(10000.00, (float) $line['amount']);
        $this->assertEquals(round($line['number_input'] * $line['rate'], 2), (float) $line['amount']);

        // Explicit 0.5 quantity
        $lineExplicit = $this->calculator->calculateBasicPay($employee, 0.5, null, 2.0);

        $this->assertEquals(0.50, (float) $lineExplicit['number_input']);
        $this->assertEquals(20000.00, (float) $lineExplicit['rate']);
        $this->assertEquals(10000.00, (float) $lineExplicit['amount']);
        $this->assertEquals(round($lineExplicit['number_input'] * $lineExplicit['rate'], 2), (float) $lineExplicit['amount']);
    }

    /**
     * Permanent Example 4: Overtime
     * Overtime hours: 5
     * Hourly overtime rate: 78.125
     * 5 × 78.125 = 390.63
     */
    public function test_overtime_rate_calculation(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-OT-001',
            'first_name' => 'OT',
            'last_name' => 'Worker',
            'status' => 'active',
            'hourly_rate' => 62.50, // 62.50 * 1.25 = 78.125
        ]);

        $line = $this->calculator->calculateOvertimePay($employee, 5.0, null, 'regular');

        $this->assertEquals(5.00, (float) $line['number_input']);
        $this->assertEquals(78.125, (float) $line['rate']);
        $this->assertEquals(390.63, (float) $line['amount']);
    }

    /**
     * Permanent Example 5: Client reported salary example
     * Stored rate: 144
     * Payroll number input: 12
     * 12 × 144 = 1,728
     */
    public function test_client_reported_salary_example_calculation(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-CLIENT-001',
            'first_name' => 'Client',
            'last_name' => 'Worker',
            'status' => 'active',
            'hourly_rate' => 144.00,
        ]);

        $line = $this->calculator->calculateBasicPay($employee, 12.0, null, 1.0);

        $this->assertEquals(12.00, (float) $line['number_input']);
        $this->assertEquals(144.00, (float) $line['rate']);
        $this->assertEquals(1728.00, (float) $line['amount']);
        $this->assertEquals(round($line['number_input'] * $line['rate'], 2), (float) $line['amount']);
    }

    /**
     * Test 6: Monthly rate is not divided during generation.
     */
    public function test_monthly_rate_is_not_divided_during_generation(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-MON-UNDIV',
            'first_name' => 'Monthly',
            'last_name' => 'Undivided',
            'status' => 'active',
            'monthly_rate' => 30000.00,
        ]);

        $line = $this->calculator->calculateBasicPay($employee, 1.0, null, 2.0);

        // Rate remains exactly 30000.00 (NOT divided to 15000)
        $this->assertEquals(30000.00, (float) $line['rate']);
        $this->assertEquals(0.50, (float) $line['number_input']);
        $this->assertEquals(15000.00, (float) $line['amount']);
    }

    /**
     * Test 7: Daily rate is not converted from monthly salary.
     */
    public function test_daily_rate_is_not_converted_from_monthly_salary(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-DAILY-PURE',
            'first_name' => 'PureDaily',
            'last_name' => 'Worker',
            'status' => 'active',
            'daily_rate' => 650.00,
        ]);

        $line = $this->calculator->calculateBasicPay($employee, 10.0, null, 2.0);

        // Rate remains exactly 650.00 (NOT converted)
        $this->assertEquals(650.00, (float) $line['rate']);
        $this->assertEquals(10.00, (float) $line['number_input']);
        $this->assertEquals(6500.00, (float) $line['amount']);
    }

    /**
     * Test 8: Number Input accepts decimal values and editing recalculates the total.
     */
    public function test_number_input_accepts_decimal_values_and_editing_recalculates_total(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-1',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-DECIMAL-001',
            'first_name' => 'Decimal',
            'last_name' => 'Input',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'days_worked' => 10.0,
            'total_gross_pay' => 10000.00,
            'total_deductions' => 0.00,
            'net_pay' => 10000.00,
        ]);

        $basicType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['company_id' => $this->company->id, 'name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]
        );

        $payIn = PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $basicType->id,
            'number_input' => 0.50,
            'rate' => 20000.00,
        ]);

        // Submit decimal update number_input = 0.75
        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                ['id' => $payIn->id, 'number_input' => 0.75],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(200);

        $payIn->refresh();
        $this->assertEquals(0.75, (float) $payIn->number_input);
        $this->assertEquals(20000.00, (float) $payIn->rate);

        $draft->refresh();
        $this->assertEquals(15000.00, (float) $draft->total_gross_pay);
    }

    /**
     * Test 9: Different pay types can exist in the same payroll batch.
     */
    public function test_different_pay_types_can_exist_in_the_same_payroll_batch(): void
    {
        $bimonthlyEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-BATCH-BIMON',
            'first_name' => 'BiMonthly',
            'last_name' => 'Batch',
            'status' => 'active',
            'monthly_rate' => 10000.00,
        ]);

        $dailyEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-BATCH-DAILY',
            'first_name' => 'Daily',
            'last_name' => 'Batch',
            'status' => 'active',
            'daily_rate' => 500.00,
        ]);

        $monthlyEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-BATCH-MONTHLY',
            'first_name' => 'Monthly',
            'last_name' => 'Batch',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Batch Period 2026-08',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $basicType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['company_id' => $this->company->id, 'name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]
        );

        EmpIncome::create([
            'employee_id' => $bimonthlyEmp->id,
            'income_type_id' => $basicType->id,
            'rate' => 10000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
        EmpIncome::create([
            'employee_id' => $dailyEmp->id,
            'income_type_id' => $basicType->id,
            'rate' => 500.00,
            'frequency' => 'daily',
            'is_active' => true,
        ]);
        EmpIncome::create([
            'employee_id' => $monthlyEmp->id,
            'income_type_id' => $basicType->id,
            'rate' => 20000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [
            $bimonthlyEmp->id,
            $dailyEmp->id,
            $monthlyEmp->id,
        ]);

        $this->assertCount(3, $headers);

        // Bi-monthly employee line in batch
        $bimonthlyHeader = $headers->firstWhere('employee_id', $bimonthlyEmp->id);
        $bimonthlyPayIn = PayIn::where('pay_id', $bimonthlyHeader->id)->where('income_type_id', $basicType->id)->first();
        $this->assertEquals(10000.00, (float) $bimonthlyPayIn->rate);

        // Daily employee line in batch
        $dailyHeader = $headers->firstWhere('employee_id', $dailyEmp->id);
        $dailyPayIn = PayIn::where('pay_id', $dailyHeader->id)->where('income_type_id', $basicType->id)->first();
        $this->assertEquals(500.00, (float) $dailyPayIn->rate);

        // Monthly employee line in batch
        $monthlyHeader = $headers->firstWhere('employee_id', $monthlyEmp->id);
        $monthlyPayIn = PayIn::where('pay_id', $monthlyHeader->id)->where('income_type_id', $basicType->id)->first();
        $this->assertEquals(20000.00, (float) $monthlyPayIn->rate);
        $this->assertEquals(0.50, (float) $monthlyPayIn->number_input);
        $this->assertEquals(10000.00, (float) ($monthlyPayIn->rate * $monthlyPayIn->number_input));
    }

    /**
     * Test 10: Draft generation prevents duplicate BASIC PayIn lines.
     */
    public function test_draft_generation_prevents_duplicate_basic_payin_lines(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-NODUP-001',
            'first_name' => 'NoDup',
            'last_name' => 'Basic',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);

        $basicType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['company_id' => $this->company->id, 'name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]
        );

        // Employee Record Maintenance default assignment for BASIC
        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $basicType->id,
            'amount' => 20000.00,
            'rate' => 20000.00,
            'is_active' => true,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-1',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$employee->id]);

        $this->assertCount(1, $headers);
        $header = $headers->first();

        // Exactly ONE BASIC PayIn line item must be generated
        $basicPayIns = PayIn::where('pay_id', $header->id)->where('income_type_id', $basicType->id)->get();
        $this->assertCount(1, $basicPayIns);

        $basicLine = $basicPayIns->first();
        $this->assertEquals(0.50, (float) $basicLine->number_input);
        $this->assertEquals(20000.00, (float) $basicLine->rate);
    }

    /**
     * Test 11: Recurring monthly incomes preserve assigned rate snapshot and set number_input = 0.5 in semi-monthly drafts.
     */
    public function test_recurring_monthly_incomes_preserve_rate_snapshot_and_set_half_month_number_input(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-001',
            'first_name' => 'Recurring',
            'last_name' => 'Income',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);

        $allowanceType = IncomeType::firstOrCreate(
            ['code' => 'ALLOWANCE'],
            ['company_id' => $this->company->id, 'name' => 'Allowances', 'type' => 'non_taxable', 'is_active' => true]
        );

        // Employee default allowance assignment of 4,000 monthly
        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $allowanceType->id,
        ], [
            'frequency' => 'monthly',
            'amount' => 4000.00,
            'rate' => 4000.00,
            'is_active' => true,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-1',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $service = app(PayrollProcessingService::class);
        $headers = $service->generateDraft($period, [$employee->id], ['use_fixed_income_amount' => false]);

        $header = $headers->first();

        $allowancePayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $allowanceType->id)->first();
        $this->assertNotNull($allowancePayIn);

        // Preserves assigned rate snapshot 4,000 and uses number_input 0.5
        $this->assertEquals(4000.00, (float) $allowancePayIn->rate);
        $this->assertEquals(0.50, (float) $allowancePayIn->number_input);
        $this->assertEquals(2000.00, (float) ($allowancePayIn->rate * $allowancePayIn->number_input));
    }

    /**
     * Test 12: Attendance values are saved to PayHeader and persist after reload.
     */
    public function test_attendance_values_are_saved_to_payheader_and_persist_after_reload(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-Att',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-ATT-001',
            'first_name' => 'Att',
            'last_name' => 'Save',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'days_worked' => 10.0,
            'days_absent' => 0.0,
            'overtime_hours' => 0.0,
            'late_hours' => 0.0,
            'total_gross_pay' => 10000.00,
            'total_deductions' => 0.00,
            'net_pay' => 10000.00,
        ]);

        $basicType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['company_id' => $this->company->id, 'name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]
        );

        $payIn = PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $basicType->id,
            'number_input' => 0.50,
            'rate' => 20000.00,
        ]);

        $payload = [
            'days_worked' => 11.5,
            'days_absent' => 1.0,
            'overtime_hours' => 3.5,
            'late_hours' => 2.0,
            'pay_ins' => [
                ['id' => $payIn->id, 'number_input' => 0.50],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(200);

        // Verify attendance fields are saved in database on PayHeader
        $draft->refresh();
        $this->assertEquals(11.50, (float) $draft->days_worked);
        $this->assertEquals(1.00, (float) $draft->days_absent);
        $this->assertEquals(3.50, (float) $draft->overtime_hours);
        $this->assertEquals(2.00, (float) $draft->late_hours);
    }

    /**
     * Test 13: Daily employee days_worked syncs to BASIC number_input while monthly employee remains manual.
     */
    public function test_daily_employee_days_worked_syncs_to_basic_number_input(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-Sync',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $dailyEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-DAILY-SYNC',
            'first_name' => 'Daily',
            'last_name' => 'Sync',
            'status' => 'active',
            'daily_rate' => 500.00,
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $dailyEmp->id,
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'days_worked' => 10.0,
            'total_gross_pay' => 5000.00,
            'total_deductions' => 0.00,
            'net_pay' => 5000.00,
        ]);

        $basicType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['company_id' => $this->company->id, 'name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]
        );

        $payIn = PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $basicType->id,
            'number_input' => 10.00,
            'rate' => 500.00,
        ]);

        // Submit days_worked = 12.0 for daily employee
        $payload = [
            'days_worked' => 12.0,
            'pay_ins' => [
                ['id' => $payIn->id, 'number_input' => 12.00],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(200);

        // Daily employee BASIC number_input syncs with days_worked
        $payIn->refresh();
        $this->assertEquals(12.00, (float) $payIn->number_input);

        $draft->refresh();
        $this->assertEquals(6000.00, (float) $draft->total_gross_pay);
    }

    /**
     * Test 14: Monthly employee days_worked does not replace BASIC manual number_input.
     */
    public function test_monthly_employee_days_worked_does_not_replace_basic_manual_number_input(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-MonSync',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $monthlyEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-MON-NOSYNC',
            'first_name' => 'Monthly',
            'last_name' => 'Manual',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $monthlyEmp->id,
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'days_worked' => 10.0,
            'total_gross_pay' => 10000.00,
            'total_deductions' => 0.00,
            'net_pay' => 10000.00,
        ]);

        $basicType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['company_id' => $this->company->id, 'name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]
        );

        $payIn = PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $basicType->id,
            'number_input' => 0.50,
            'rate' => 20000.00,
        ]);

        // Submit days_worked = 12.0 but keep pay_ins BASIC number_input = 0.50 for monthly employee
        $payload = [
            'days_worked' => 12.0,
            'pay_ins' => [
                ['id' => $payIn->id, 'number_input' => 0.50],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(200);

        // Monthly employee BASIC number_input remains manual (0.50)
        $payIn->refresh();
        $this->assertEquals(0.50, (float) $payIn->number_input);

        $draft->refresh();
        $this->assertEquals(10000.00, (float) $draft->total_gross_pay);
    }

    /**
     * Test 15: Manual deductions remain untouched when saving Days Worked or Overtime.
     */
    public function test_manual_deductions_remain_untouched_after_saving_days_worked_or_overtime(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-DedPreserve',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-DED-PRESERVE',
            'first_name' => 'Ded',
            'last_name' => 'Preserve',
            'status' => 'active',
            'monthly_rate' => 20000.00,
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'days_worked' => 10.0,
            'total_gross_pay' => 10000.00,
            'total_deductions' => 1500.00,
            'net_pay' => 8500.00,
        ]);

        $basicType = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            ['company_id' => $this->company->id, 'name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]
        );

        $payIn = PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $basicType->id,
            'number_input' => 0.50,
            'rate' => 20000.00,
        ]);

        $deductionType = DeductionType::firstOrCreate(
            ['code' => 'OTHER'],
            ['company_id' => $this->company->id, 'name' => 'Other Deduction', 'is_active' => true]
        );

        $payOut = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $deductionType->id,
            'number_input' => 1.00,
            'rate' => 1500.00,
        ]);

        // Submit only attendance / days_worked update without pay_outs deduction array
        $payload = [
            'days_worked' => 11.0,
            'overtime_hours' => 2.0,
            'pay_ins' => [
                ['id' => $payIn->id, 'number_input' => 0.50],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->putJson(route('payroll-drafts.update', $draft->id), $payload);

        $response->assertStatus(200);

        // Verify existing PayOut record is preserved untouched
        $payOut->refresh();
        $this->assertEquals(1500.00, (float) $payOut->rate);
        $this->assertEquals(1.00, (float) $payOut->number_input);

        // Verify total deductions is recalculated using the preserved deduction (1,500)
        $draft->refresh();
        $this->assertEquals(1500.00, (float) $draft->total_deductions);
        $this->assertEquals(8500.00, (float) $draft->net_pay);
    }
}

