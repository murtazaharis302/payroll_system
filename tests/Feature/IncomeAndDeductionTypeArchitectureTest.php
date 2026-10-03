<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpIncome;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\PayrollGenerationService;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeAndDeductionTypeArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $adminUser;
    protected PayrollPeriod $period;
    protected EmployeeMaster $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@architecture.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Architecture Test Corp',
            'email' => 'corp@architecture.test',
            'code' => 'ATC',
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

        $dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'name' => 'Engineering',
            'code' => 'ENG',
            'is_active' => true,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'ATC-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@architecture.test',
            'monthly_rate' => 30000.00,
            'daily_rate' => 1153.85,
            'hourly_rate' => 144.23,
            'status' => 'active',
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

    public function test_seeded_types_have_correct_system_keys()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $basic = IncomeType::where('system_key', 'basic')->first();
        $this->assertNotNull($basic);
        $this->assertTrue($basic->isBasic());

        $ot = IncomeType::where('system_key', 'overtime')->first();
        $this->assertNotNull($ot);
        $this->assertTrue($ot->isOvertime());

        $customIncome = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Project Allowance',
            'code' => 'PROJ_ALLOW',
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $this->assertNull($customIncome->system_key);
        $this->assertFalse($customIncome->isBasic());

        $sss = DeductionType::where('system_key', 'sss')->first();
        $this->assertNotNull($sss);
        $this->assertTrue($sss->isStatutory());

        $tax = DeductionType::where('system_key', 'tax')->first();
        $this->assertNotNull($tax);
        $this->assertTrue($tax->isStatutory());

        $vale = DeductionType::where('code', 'VALE')->first();
        $this->assertNotNull($vale);
        $this->assertNull($vale->system_key);
        $this->assertFalse($vale->isStatutory());
    }

    public function test_payroll_generation_works_when_income_and_deduction_types_are_renamed()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        // Rename Basic and Overtime
        $basic = IncomeType::where('system_key', 'basic')->first();
        $basic->update(['name' => 'Regular Base Salary', 'code' => 'REG_BASE']);

        $ot = IncomeType::where('system_key', 'overtime')->first();
        $ot->update(['name' => 'Extra Hours Worked', 'code' => 'X_HOURS']);

        // Rename Statutory deductions
        $sss = DeductionType::where('system_key', 'sss')->first();
        $sss->update(['name' => 'Social Security System (PH)', 'code' => 'SOC_SEC']);

        $ph = DeductionType::where('system_key', 'philhealth')->first();
        $ph->update(['name' => 'Health Insurance Corp', 'code' => 'HEALTH_INS']);

        $pi = DeductionType::where('system_key', 'pagibig')->first();
        $pi->update(['name' => 'Home Development Fund', 'code' => 'HOME_DEV']);

        $tax = DeductionType::where('system_key', 'tax')->first();
        $tax->update(['name' => 'Bureau of Internal Revenue Tax', 'code' => 'BIR_WTAX']);

        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$this->employee->id], [
            'days_worked' => [$this->employee->id => 13],
            'overtime_hours' => [$this->employee->id => 5],
        ]);

        $this->assertCount(1, $drafts);
        $draft = $drafts->first();

        // Verify Basic Pay calculated
        $this->assertGreaterThan(0, $draft->basic_pay);
        $this->assertEquals(15000.00, round((float)$draft->basic_pay, 2));

        // Verify Overtime Pay calculated (5 hours * 180.29 = 901.45)
        $this->assertGreaterThan(0, $draft->overtime_pay);
        $this->assertEquals(901.45, round((float)$draft->overtime_pay, 2));

        // Verify Statutory deductions calculated & mapped
        $this->assertGreaterThan(0, $draft->sss_contribution);
        $this->assertGreaterThan(0, $draft->philhealth_contribution);
        $this->assertGreaterThan(0, $draft->pagibig_contribution);

        // Verify PayIn line links
        $basicPayIn = $draft->payIns()->where('income_type_id', $basic->id)->first();
        $this->assertNotNull($basicPayIn);
        $this->assertEquals(15000.00, round((float)($basicPayIn->rate * $basicPayIn->number_input), 2));

        // Verify PayOut line links
        $sssPayOut = $draft->payOuts()->where('deduction_type_id', $sss->id)->first();
        $this->assertNotNull($sssPayOut);
        $this->assertEquals((float)$draft->sss_contribution, (float)($sssPayOut->rate * $sssPayOut->number_input));
    }

    public function test_emp_income_basic_pay_rate_override_works_with_system_key()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $basic = IncomeType::where('system_key', 'basic')->first();
        $basic->update(['name' => 'Custom Base', 'code' => 'CUSTOM_BASIC']);

        // Create an EmpIncome for Basic Pay providing a special rate override
        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $basic->id,
            'rate' => 40000.00,
            'amount' => 40000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$this->employee->id], [
            'days_worked' => [$this->employee->id => 13],
        ]);

        $draft = $drafts->first();

        // 40000 / 2 = 20000 semi-monthly basic pay
        $this->assertEquals(20000.00, round((float)$draft->basic_pay, 2));
    }

    public function test_custom_incomes_and_deductions_without_system_key()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $customIncome = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Internet Stipend',
            'code' => 'STIPEND',
            'is_taxable' => false,
            'is_active' => true,
        ]);

        $customDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Uniform Fee',
            'code' => 'UNIFORM',
            'type' => 'other',
            'is_taxable' => false,
            'is_active' => true,
        ]);

        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $customIncome->id,
            'rate' => 1500.00,
            'amount' => 1500.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        \App\Models\EmpDeduction::create([
            'employee_id' => $this->employee->id,
            'deduction_type_id' => $customDeduction->id,
            'rate' => 250.00,
            'amount' => 250.00,
            'frequency' => 'per_payroll',
            'is_active' => true,
        ]);

        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$this->employee->id], [
            'days_worked' => [$this->employee->id => 13],
        ]);

        $draft = $drafts->first();

        // 1500 monthly / 2 = 750 semi-monthly
        $this->assertEquals(750.00, round((float)($draft->custom_incomes[$customIncome->id] ?? 0), 2));
        $this->assertEquals(250.00, round((float)($draft->custom_deductions[$customDeduction->id] ?? 0), 2));
    }

    public function test_system_key_cannot_be_overridden_via_mass_assignment()
    {
        $type = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Hacker Pay',
            'code' => 'HACK',
            'system_key' => 'basic', // should be ignored by fillable
            'is_active' => true,
        ]);

        $this->assertNull($type->system_key);
    }

    public function test_admin_cannot_delete_core_statutory_deductions()
    {
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);
        $this->actingAs($this->adminUser);

        $sss = DeductionType::where('system_key', 'sss')->first();

        $response = $this->delete(panelRoute('deduction-types.destroy', ['id' => $sss->id]));
        $response->assertStatus(403);
        $this->assertDatabaseHas('deduction_types', ['id' => $sss->id]);
    }

    public function test_cannot_create_duplicate_system_key_in_same_company_scope()
    {
        \Illuminate\Support\Facades\DB::table('income_types')->insert([
            'company_id' => $this->company->id,
            'name' => 'Company Basic 1',
            'code' => 'COMP_BASIC_1',
            'system_key' => 'basic',
            'is_taxable' => 1,
            'is_active' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('income_types')->insert([
            'company_id' => $this->company->id,
            'name' => 'Company Basic 2',
            'code' => 'COMP_BASIC_2',
            'system_key' => 'basic',
            'is_taxable' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_thirteenth_month_service_works_when_basic_pay_is_renamed()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $basic = IncomeType::where('system_key', 'basic')->first();
        $basic->update(['name' => 'Salario Base', 'code' => 'SAL_BASE']);

        // Generate payroll
        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$this->employee->id], [
            'days_worked' => [$this->employee->id => 13],
        ]);
        $draft = $drafts->first();
        $draft->update(['status' => 'approved']);

        $thirteenthService = app(\App\Services\ThirteenthMonthService::class);
        $ytd = $thirteenthService->calculateYtdBasicPay($this->employee, 2026);

        $this->assertEquals(15000.00, round($ytd, 2));
    }

    public function test_deduction_report_resolver_resolves_statutory_and_attendance_by_system_key()
    {
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $sss = DeductionType::where('system_key', 'sss')->first();
        $sss->update(['name' => 'Custom SSS Plan', 'code' => 'CUST_SSS_99']);

        $payHeader = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'status' => 'approved',
        ]);

        $payOut = PayOut::create([
            'pay_id' => $payHeader->id,
            'deduction_type_id' => $sss->id,
            'rate' => 1350.00,
            'number_input' => 1.00,
        ]);

        $resolvedKey = \App\Support\Payroll\DeductionReportResolver::resolveCanonicalKey($payOut, ['sss_contribution', 'philhealth_contribution']);
        $this->assertEquals('sss_contribution', $resolvedKey);

        $amount = \App\Support\Payroll\DeductionReportResolver::resolveAmountForHeader($payHeader, 'sss_contribution');
        $this->assertEquals(1350.00, $amount);
    }
}
