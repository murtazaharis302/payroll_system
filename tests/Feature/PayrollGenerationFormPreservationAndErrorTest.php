<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
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

class PayrollGenerationFormPreservationAndErrorTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected Department $department;
    protected PayrollPeriod $period;
    protected EmployeeMaster $employee1;
    protected EmployeeMaster $employee2;
    protected IncomeType $basicType;
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
            'email' => 'admin_preserv@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Preserve Corp',
            'company_code' => 'PRESERVE',
            'email' => 'preserve@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->department = Department::create([
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

        $this->basicType = IncomeType::where('code', 'BASIC')->firstOrFail();
        $this->allowanceType = IncomeType::where('code', 'ALLOWANCE')->firstOrFail();

        $this->employee1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $this->employee2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);
    }

    public function test_failed_generation_preserves_inputs_rolls_back_and_shows_specific_error(): void
    {
        // Give employee1 a valid allowance rate, but employee2 has EmpIncome with rate 0 (missing rate)
        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee1->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 1500.00, 'frequency' => 'monthly', 'is_active' => true]
        );
        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee2->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 0.00, 'frequency' => 'monthly', 'is_active' => true]
        );

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $payload = [
            'payroll_period_id' => $this->period->id,
            'dept_id' => $this->department->id,
            'employee_ids' => [$this->employee1->id, $this->employee2->id],
            'use_fixed_income_amount' => '1',
            'incomes' => [
                ['display' => '1', 'income_type' => 'BASIC', 'number_input' => '1.0'],
                ['display' => '1', 'income_type' => 'ALLOWANCE', 'number_input' => '2.5', 'amount' => '500.00'],
            ],
            'use_fixed_amount' => '1',
            'deductions' => [
                ['display' => '1', 'deduction_type' => 'SSS', 'amount' => '450.00'],
            ],
        ];

        $response = $this->post(route('company.payroll-drafts.generate'), $payload);

        // 1. Assert redirect back with withInput() and with statusDanger
        $response->assertStatus(302);
        $response->assertSessionHas('statusDanger');
        $errorMsg = session('statusDanger');
        $this->assertStringContainsString('EMP-002 Jane Smith — ALLOWANCE: no active Recurring Income rate', $errorMsg);

        // 2. Assert old inputs are preserved
        $response->assertSessionHas('_old_input');
        $this->assertEquals('1', session('_old_input.use_fixed_income_amount'));
        $this->assertEquals('2.5', session('_old_input.incomes.1.number_input'));
        $this->assertEquals('500.00', session('_old_input.incomes.1.amount'));

        // 3. Assert database transaction rollback (no headers created for either employee)
        $this->assertEquals(0, PayHeader::where('payroll_period_id', $this->period->id)->count());

        // 4. Assert GET create page renders the preserved values from old()
        $viewResponse = $this->get(route('company.payroll-drafts.create', ['payroll_period_id' => $this->period->id]));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('value="2.5"', false);
        $viewResponse->assertSee('value="500.00"', false);
        $viewResponse->assertSee('value="450.00"', false);

        // 5. Fix employee2 rate and retry generation using the same payload -> succeeds
        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee2->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 1200.00, 'frequency' => 'monthly', 'is_active' => true]
        );

        $retryResponse = $this->post(route('company.payroll-drafts.generate'), $payload);
        $retryResponse->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $this->assertEquals(2, PayHeader::where('payroll_period_id', $this->period->id)->count());
    }

    public function test_multi_employee_blocking_setup_errors_collected_in_single_preflight_pass(): void
    {
        $bonusType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'BONUS_TEST',
            'name' => 'Bonus Test',
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
        $loanTypeUnmapped = \App\Models\LoanType::withoutEvents(function () {
            return \App\Models\LoanType::create([
                'company_id' => $this->company->id,
                'name' => 'Emergency Aid Loan',
                'code' => 'EMG_AID',
                'deduction_type_id' => null,
                'is_active' => true,
            ]);
        });
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');

        $employee3 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => 'EMP-003',
            'first_name' => 'Mark',
            'last_name' => 'LoanUser',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        // Employee 1: missing ALLOWANCE rate (rate 0)
        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee1->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 0.00, 'frequency' => 'monthly', 'is_active' => true]
        );
        // Employee 2: missing BONUS_TEST rate (rate 0)
        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee2->id, 'income_type_id' => $bonusType->id],
            ['rate' => 0.00, 'frequency' => 'monthly', 'is_active' => true]
        );
        // Employee 3: active loan with unmapped Deduction Type reference
        \App\Models\EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee3->id,
            'loan_type_id' => $loanTypeUnmapped->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'monthly_amortization' => 500.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $payload = [
            'payroll_period_id' => $this->period->id,
            'dept_id' => $this->department->id,
            'employee_ids' => [$this->employee1->id, $this->employee2->id, $employee3->id],
            'use_fixed_income_amount' => '1',
            'incomes' => [
                ['display' => '1', 'income_type' => 'BASIC', 'number_input' => '1.0'],
                ['display' => '1', 'income_type' => 'ALLOWANCE', 'number_input' => '1.0'],
                ['display' => '1', 'income_type' => 'BONUS_TEST', 'number_input' => '1.0'],
            ],
        ];

        $response = $this->post(route('company.payroll-drafts.generate'), $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('statusDanger');
        $response->assertSessionHas('generation_errors');

        $errors = session('generation_errors');
        $this->assertIsArray($errors);
        $this->assertGreaterThanOrEqual(3, count($errors));

        // Assert all 3 specific issues are collected together
        $errorString = implode(' | ', $errors);
        $this->assertStringContainsString('EMP-001 John Doe — ALLOWANCE: no active Recurring Income rate', $errorString);
        $this->assertStringContainsString('EMP-002 Jane Smith — BONUS_TEST: no active Recurring Income rate', $errorString);
        $this->assertStringContainsString("EMP-003 Mark LoanUser — Loan 'Emergency Aid Loan': not mapped to a Deduction Type", $errorString);

        // Assert persistent toast contains total count
        $this->assertStringContainsString('Payroll generation blocked: ' . count($errors) . ' setup issues found', session('statusDanger'));

        // Assert zero drafts committed
        $this->assertEquals(0, PayHeader::where('payroll_period_id', $this->period->id)->count());

        // Assert form inputs preserved
        $this->assertEquals('1', session('_old_input.use_fixed_income_amount'));
        $this->assertEquals('1.0', session('_old_input.incomes.1.number_input'));
    }

    public function test_validation_failure_preserves_input_and_returns_validation_errors(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => '', // required field missing
            'dept_id' => $this->department->id,
            'use_fixed_income_amount' => '1',
            'incomes' => [
                ['display' => '1', 'income_type' => 'BASIC', 'number_input' => '5.0'],
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['payroll_period_id']);
        $this->assertEquals('5.0', session('_old_input.incomes.0.number_input'));
    }

    public function test_invalid_department_preserves_input(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'dept_id' => 999999, // invalid department
            'incomes' => [
                ['display' => '1', 'income_type' => 'BASIC', 'number_input' => '7.0'],
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['dept_id']);
        $this->assertEquals('7.0', session('_old_input.incomes.0.number_input'));
    }

    public function test_manual_mode_newly_submitted_amount_is_not_used_as_quantity(): void
    {
        EmpIncome::updateOrCreate(
            ['employee_id' => $this->employee1->id, 'income_type_id' => $this->allowanceType->id],
            ['rate' => 500.00, 'frequency' => 'monthly', 'is_active' => true]
        );

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id]);

        // Submit form in manual mode with amount = 250.00, but number_input = null / empty
        $response = $this->post(route('company.payroll-drafts.generate'), [
            'payroll_period_id' => $this->period->id,
            'employee_ids' => [$this->employee1->id],
            'use_fixed_income_amount' => '1',
            'incomes' => [
                ['display' => '1', 'income_type' => 'BASIC'],
                ['display' => '1', 'income_type' => 'ALLOWANCE', 'number_input' => '', 'amount' => '250.00'],
            ],
        ]);

        $response->assertRedirect(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]));

        $header = PayHeader::where('payroll_period_id', $this->period->id)->where('employee_id', $this->employee1->id)->firstOrFail();
        $allowancePayIn = \App\Models\PayIn::where('pay_id', $header->id)->where('income_type_id', $this->allowanceType->id)->firstOrFail();

        // Quantity should NOT be 250.00; it must be default 1.00 because number_input was not provided
        $this->assertEquals(1.00, (float) $allowancePayIn->number_input);
        $this->assertEquals(500.00, (float) $allowancePayIn->rate);
    }
}
