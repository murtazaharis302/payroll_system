<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpDeduction;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use App\Support\Payroll\DeductionReportResolver;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollAbsencesDecouplingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $adminUser;
    protected PayrollPolicy $policy;
    protected DeductionType $absType;

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
            'name' => 'Admin Decoupling Test',
            'email' => 'admin_decouple@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Decoupling Corp',
            'company_code' => 'DEC',
            'email' => 'dec@test.com',
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
            'policy_name' => 'Standard Policy',
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

        $this->absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();

        session(['active_company_id' => $this->company->id]);
    }

    /**
     * 1. Fixed ABSENCES 500.00 remains exactly 500.00 with days_absent 0, 1 and 3.5.
     */
    public function test_fixed_absences_remains_exact_amount_regardless_of_days_absent(): void
    {
        $emp0 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DEC-0',
            'first_name' => 'Zero',
            'last_name' => 'Days',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DEC-1',
            'first_name' => 'One',
            'last_name' => 'Day',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $emp3 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DEC-3',
            'first_name' => 'ThreeHalf',
            'last_name' => 'Days',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        $genOptions = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 500.00],
            ],
            'payroll_inputs' => [
                $emp0->id => ['days_absent' => 0.0],
                $emp1->id => ['days_absent' => 1.0],
                $emp3->id => ['days_absent' => 3.5],
            ],
        ];

        $drafts = $service->generateDraft($this->period, [$emp0->id, $emp1->id, $emp3->id], $genOptions);

        foreach ($drafts as $draft) {
            $absPo = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
            $this->assertNotNull($absPo, "Draft for employee {$draft->employee_id} should have ABSENCES payout.");
            $this->assertEquals(1.00, (float) $absPo->number_input, 'ABSENCES number_input should be 1.00');
            $this->assertEquals(500.00, (float) $absPo->rate, 'ABSENCES rate should be 500.00');
            $this->assertEquals(500.00, (float) ($absPo->rate * $absPo->number_input), 'Total amount should be exactly 500.00');
            $this->assertEquals(500.00, DeductionReportResolver::resolveAmountForHeader($draft, 'absences_deduction'));
        }
    }

    /**
     * 2. Editing days_absent after draft generation does not alter the ABSENCES PayOut.
     */
    public function test_editing_days_absent_does_not_alter_absences_payout(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-EDIT-ABS',
            'first_name' => 'Edit',
            'last_name' => 'Abs',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$emp->id], [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 500.00],
            ],
            'payroll_inputs' => [$emp->id => ['days_absent' => 1.0]],
        ])->first();

        // Edit days_absent from 1.0 to 4.5 via controller
        $res = $this->actingAs($this->adminUser)->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 9,
            'days_absent' => 4.5,
            'late_hours' => 0,
            'undertime_hours' => 0,
            'undertime_deduction' => 0,
            'overtime_hours' => 0,
        ]);
        $res->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertEquals(4.5, (float) $draft->days_absent);

        $absPo = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($absPo);
        $this->assertEquals(1.00, (float) $absPo->number_input);
        $this->assertEquals(500.00, (float) $absPo->rate);
        $this->assertEquals(500.00, (float) ($absPo->rate * $absPo->number_input));
    }

    /**
     * 3. Adding an ABSENCES deduction does not modify PayHeader.days_absent.
     */
    public function test_adding_absences_deduction_does_not_modify_days_absent(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ADD-ABS',
            'first_name' => 'Add',
            'last_name' => 'Abs',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$emp->id], [
            'payroll_inputs' => [$emp->id => ['days_absent' => 2.5]],
        ])->first();

        $this->assertEquals(2.5, (float) $draft->days_absent);

        // Add ABSENCES line with rate 750.00
        $res = $this->actingAs($this->adminUser)->post(route('admin.payroll-drafts.add-deduction', $draft->id), [
            'deduction_type_id' => $this->absType->id,
            'rate' => 750.00,
            'number_input' => 3.0, // Attempt crafted quantity
        ]);
        $res->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertEquals(2.5, (float) $draft->days_absent, 'days_absent must remain untouched');

        $absPo = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($absPo);
        $this->assertEquals(1.00, (float) $absPo->number_input, 'ABSENCES number_input must be enforced to 1.00');
        $this->assertEquals(750.00, (float) $absPo->rate);
    }

    /**
     * 4. Days absent alone does not automatically create an ABSENCES PayOut.
     */
    public function test_days_absent_alone_does_not_auto_create_absences_payout(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-NO-AUTO',
            'first_name' => 'No',
            'last_name' => 'Auto',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$emp->id], [
            'payroll_inputs' => [$emp->id => ['days_absent' => 3.0]],
        ])->first();

        $this->assertEquals(3.0, (float) $draft->days_absent);
        $absPo = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNull($absPo, 'No ABSENCES payout should be auto-generated.');
        $this->assertEquals(0.00, DeductionReportResolver::resolveAmountForHeader($draft, 'absences_deduction'));
    }

    /**
     * 5. Ordinary recurring ABSENCES uses its EmpDeduction setup when fixed mode is not used.
     */
    public function test_recurring_emp_deduction_for_absences_uses_assignment_rate(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-REC-ABS',
            'first_name' => 'Rec',
            'last_name' => 'Abs',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        EmpDeduction::create([
            'employee_id' => $emp->id,
            'deduction_type_id' => $this->absType->id,
            'rate' => 600.00,
            'frequency' => 'per_payroll',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period, [$emp->id], [
            'payroll_inputs' => [$emp->id => ['days_absent' => 2.0]],
        ])->first();

        $absPo = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($absPo);
        $this->assertEquals(1.00, (float) $absPo->number_input);
        $this->assertEquals(600.00, (float) $absPo->rate);
        $this->assertEquals(600.00, (float) ($absPo->rate * $absPo->number_input));
    }
}
