<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HrGenerationIncomeNumberInputTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected User $hrUser;
    protected PayrollPeriod $period;
    protected EmployeeMaster $employee;
    protected Menu $incomeInputMenu;
    protected IncomeType $basicType;
    protected IncomeType $otType;
    protected IncomeType $allowanceType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);
        $this->seed(MenusTableSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'compadmin_num@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Number Input Test Corp',
            'company_code' => 'NUMCORP',
            'email' => 'numcorp@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->hrUser = User::create([
            'name' => 'HR Staff',
            'email' => 'hr_num@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company->id,
        ]);

        $dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'NUMDEPT',
            'name' => 'Numbers Department',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 Cutoff Num',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-NUM-1',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'monthly_rate' => 20000.00,
            'daily_rate' => 0.00,
            'hourly_rate' => 0.00,
            'status' => 'active',
        ]);

        $this->basicType = IncomeType::where('code', 'BASIC')->firstOrFail();
        $this->otType = IncomeType::where('code', 'OVERTIME')->firstOrFail();
        $this->allowanceType = IncomeType::where('code', 'ALLOWANCE')->firstOrFail();

        $this->incomeInputMenu = Menu::where('module', 'payroll_income_inputs')->firstOrFail();
    }

    protected function enableHrIncomeInput(): void
    {
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $this->incomeInputMenu->id],
            ['is_enabled' => true]
        );
    }

    public function test_hr_with_income_inputs_can_submit_number_input_and_generates_correct_payin_quantities(): void
    {
        $this->enableHrIncomeInput();

        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 1500.00, 'frequency' => 'monthly', 'is_active' => true]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'number_input' => '10.0'],
                ['display' => 1, 'income_type' => 'OVERTIME', 'number_input' => '10.5'],
                ['display' => 1, 'income_type' => 'ALLOWANCE', 'number_input' => '2.0'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $basicPayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->basicType->id)->first();
        $this->assertNotNull($basicPayIn);
        $this->assertEquals(10.0, (float) $basicPayIn->number_input);

        $otPayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->otType->id)->first();
        $this->assertNotNull($otPayIn);
        $this->assertEquals(10.5, (float) $otPayIn->number_input);

        $allowancePayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->allowanceType->id)->first();
        $this->assertNotNull($allowancePayIn);
        $this->assertEquals(2.0, (float) $allowancePayIn->number_input);
        $this->assertEquals(1500.00, (float) $allowancePayIn->rate);
        $this->assertEquals(3000.00, $allowancePayIn->amount);
    }

    public function test_explicit_zero_number_input_for_overtime_does_not_fall_through_to_attendance(): void
    {
        $this->enableHrIncomeInput();

        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'biometric_id' => 101,
            'log_date' => '2026-08-05',
            'log_datetime' => '2026-08-05 08:00:00',
            'log_type' => 'in',
            'log_time' => '08:00:00',
        ]);
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'biometric_id' => 101,
            'log_date' => '2026-08-05',
            'log_datetime' => '2026-08-05 19:00:00',
            'log_type' => 'out',
            'log_time' => '19:00:00',
        ]);

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC'],
                ['display' => 1, 'income_type' => 'OVERTIME', 'number_input' => '0'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $otPayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->otType->id)->first();
        $this->assertNotNull($otPayIn);
        $this->assertEquals(0.0, (float) $otPayIn->number_input);
    }

    public function test_basic_precedence_per_employee_overrides_batch_number_input(): void
    {
        $this->enableHrIncomeInput();

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'payroll_inputs' => [
                $this->employee->id => [
                    'number_input' => '12.0',
                ],
            ],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'number_input' => '5.0'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $basicPayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->basicType->id)->firstOrFail();
        $this->assertEquals(12.0, (float) $basicPayIn->number_input);
    }

    public function test_explicit_zero_number_input_for_basic_generates_zero_quantity(): void
    {
        $this->enableHrIncomeInput();

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'number_input' => '0'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $basicPayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->basicType->id)->first();
        $this->assertNotNull($basicPayIn);
        $this->assertEquals(0.0, (float) $basicPayIn->number_input);
        $this->assertEquals(0.0, $basicPayIn->amount);
    }

    public function test_null_number_input_for_recurring_income_uses_default_monthly_fraction(): void
    {
        $this->enableHrIncomeInput();

        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 1000.00, 'frequency' => 'monthly', 'is_active' => true]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC'],
                ['display' => 1, 'income_type' => 'ALLOWANCE'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $allowancePayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->allowanceType->id)->firstOrFail();
        $this->assertEquals(0.5, (float) $allowancePayIn->number_input);
        $this->assertEquals(1000.00, (float) $allowancePayIn->rate);
        $this->assertEquals(500.00, $allowancePayIn->amount);
    }

    public function test_explicit_zero_number_input_for_recurring_income_sets_zero_quantity(): void
    {
        $this->enableHrIncomeInput();

        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 1000.00, 'frequency' => 'monthly', 'is_active' => true]
        );

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC'],
                ['display' => 1, 'income_type' => 'ALLOWANCE', 'number_input' => '0'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $allowancePayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->allowanceType->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $allowancePayIn->number_input);
        $this->assertEquals(1000.00, (float) $allowancePayIn->rate);
        $this->assertEquals(0.00, $allowancePayIn->amount);
    }

    public function test_legacy_company_defaults_with_only_amount_preserve_behavior(): void
    {
        $this->company->setIncomeDefaults([
            'use_fixed_income_amount' => false,
            'incomes' => [
                ['display' => true, 'income_type' => 'BASIC', 'amount' => 10000.00],
                ['display' => true, 'income_type' => 'ALLOWANCE', 'amount' => 500.00],
            ],
        ]);

        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 800.00, 'frequency' => 'monthly', 'is_active' => true]
        );

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $allowancePayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->allowanceType->id)->firstOrFail();
        $this->assertEquals(0.5, (float) $allowancePayIn->number_input);
        $this->assertEquals(800.00, (float) $allowancePayIn->rate);
    }

    public function test_company_admin_can_save_company_defaults_with_number_input(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.save-company-income-defaults'), [
            'company_id' => $this->company->id,
            'use_fixed_income_amount' => 0,
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC', 'number_input' => '1.0'],
                ['display' => 1, 'income_type' => 'OVERTIME', 'number_input' => '8.0'],
                ['display' => 1, 'income_type' => 'ALLOWANCE', 'number_input' => '3.0', 'amount' => '250.00'],
            ],
        ]);

        $response->assertSessionHas('status');

        $this->company->refresh();
        $defaults = $this->company->income_defaults;

        $this->assertFalse($defaults['use_fixed_income_amount']);
        $incomes = collect($defaults['incomes']);

        $basicDef = $incomes->firstWhere('income_type', 'BASIC');
        $this->assertEquals(1.0, $basicDef['number_input']);

        $otDef = $incomes->firstWhere('income_type', 'OVERTIME');
        $this->assertEquals(8.0, $otDef['number_input']);

        $allowanceDef = $incomes->firstWhere('income_type', 'ALLOWANCE');
        $this->assertEquals(3.0, $allowanceDef['number_input']);
        $this->assertEquals(250.0, $allowanceDef['amount']);
    }

    public function test_overtime_per_employee_overrides_batch_number_input(): void
    {
        $this->enableHrIncomeInput();

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee->id],
            'payroll_inputs' => [
                $this->employee->id => [
                    'overtime_hours' => '15.0',
                ],
            ],
            'incomes' => [
                ['display' => 1, 'income_type' => 'BASIC'],
                ['display' => 1, 'income_type' => 'OVERTIME', 'number_input' => '4.0'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $otPayIn = PayIn::where('pay_id', $header->id)->where('income_type_id', $this->otType->id)->firstOrFail();
        $this->assertEquals(15.0, (float) $otPayIn->number_input);
    }
}
