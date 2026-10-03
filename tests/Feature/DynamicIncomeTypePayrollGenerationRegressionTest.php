<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DynamicIncomeTypePayrollGenerationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected IncomeType $allowanceType;
    protected \App\Models\Department $dept;
    protected PayrollProcessingService $processingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IncomeTypesSeeder::class, DeductionTypesSeeder::class]);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->user = User::create([
            'name' => 'Payroll Admin',
            'email' => 'admin@dynamic-income-test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->user->id,
            'name' => 'Dynamic Income Test Corp',
            'company_code' => 'DIT_CORP',
            'email' => 'company@dynamic-income.test',
            'status' => '1',
        ]);
        $this->user->update(['company_id' => $this->company->id]);

        $this->dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'code' => 'DEPT_TEST',
            'name' => 'Test Department',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'September 1-15, 2026',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'pay_date' => '2026-09-16',
            'frequency' => 'semi-monthly',
            'status' => 'open',
        ]);

        $this->basicType = IncomeType::withoutGlobalScopes()
            ->where('system_key', 'basic')
            ->firstOrFail();

        $this->allowanceType = IncomeType::withoutGlobalScopes()
            ->where('code', 'ALLOWANCE')
            ->firstOrFail();

        $this->processingService = app(PayrollProcessingService::class);
    }

    protected function createEmployee(string $code, float $monthlyRate = 30000.00): EmployeeMaster
    {
        return EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => $code,
            'first_name' => 'John',
            'last_name' => $code,
            'email' => strtolower($code) . '@dynamic.test',
            'status' => 'active',
            'monthly_rate' => $monthlyRate,
            'daily_rate' => round($monthlyRate / 26, 2),
            'hourly_rate' => round($monthlyRate / 26 / 8, 2),
        ]);
    }

    public function test_req1_recurring_income_with_code_basic_generates_normally(): void
    {
        $employee = $this->createEmployee('EMP_REQ1', 30000.00);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 30000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $drafts = $this->processingService->generateDraft($this->period, [$employee->id], [
            'income_types' => [
                $this->basicType->id => ['mode' => 'automated', 'display' => '1'],
            ],
        ]);

        $this->assertCount(1, $drafts);
        $draft = $drafts->first();
        $basicPayIns = $draft->payIns->where('income_type_id', $this->basicType->id);

        $this->assertCount(1, $basicPayIns);
        $payIn = $basicPayIns->first();
        $this->assertEquals(0.5, (float) $payIn->number_input);
        $this->assertEquals(30000.00, (float) $payIn->rate);
        $this->assertEquals(15000.00, (float) ($payIn->number_input * $payIn->rate));
    }

    public function test_req2_renaming_basic_to_basic1_generates_payroll_by_income_type_id(): void
    {
        $employee = $this->createEmployee('EMP_REQ2', 30000.00);

        $this->basicType->update(['code' => 'BASIC1', 'name' => 'Base Pay Renamed']);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 30000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $drafts = $this->processingService->generateDraft($this->period, [$employee->id], [
            'income_types' => [
                $this->basicType->id => ['mode' => 'automated', 'display' => '1'],
            ],
        ]);

        $this->assertCount(1, $drafts);
        $draft = $drafts->first();
        $payIns = $draft->payIns->where('income_type_id', $this->basicType->id);

        $this->assertCount(1, $payIns);
        $this->assertEquals(15000.00, (float) ($payIns->first()->number_input * $payIns->first()->rate));
    }

    public function test_req3_basic_and_renamed_basic_appears_in_normal_income_selector(): void
    {
        $this->basicType->update(['code' => 'BASIC1']);

        $response = $this->actingAs($this->user)
            ->get(route('payroll-drafts.create', ['company_id' => $this->company->id]));

        $response->assertStatus(200);
        $incomes = collect($response->viewData('allCompanyIncomeTypes'));

        $this->assertTrue($incomes->contains('id', $this->basicType->id));
        $this->assertTrue($incomes->contains('code', 'BASIC1'));
    }

    public function test_req4_basic_plus_allowance_generates_exactly_once_with_no_duplicate_payin(): void
    {
        $employee = $this->createEmployee('EMP_REQ4', 20000.00);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 20000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
            'rate' => 2000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $drafts = $this->processingService->generateDraft($this->period, [$employee->id], [
            'income_types' => [
                $this->basicType->id => ['mode' => 'automated', 'display' => '1'],
                $this->allowanceType->id => ['mode' => 'automated', 'display' => '1'],
            ],
        ]);

        $draft = $drafts->first();
        $allPayIns = PayIn::where('pay_id', $draft->id)->get();

        $this->assertCount(2, $allPayIns);
        $this->assertCount(1, $allPayIns->where('income_type_id', $this->basicType->id));
        $this->assertCount(1, $allPayIns->where('income_type_id', $this->allowanceType->id));

        $this->assertEquals(10000.00, (float) ($allPayIns->where('income_type_id', $this->basicType->id)->first()->number_input * $allPayIns->where('income_type_id', $this->basicType->id)->first()->rate));
        $this->assertEquals(1000.00, (float) ($allPayIns->where('income_type_id', $this->allowanceType->id)->first()->number_input * $allPayIns->where('income_type_id', $this->allowanceType->id)->first()->rate));
    }

    public function test_req5_income_without_emp_income_produces_no_payin_in_automated_mode(): void
    {
        // Employee has monthly_rate on master, but NO EmpIncome record
        $employee = $this->createEmployee('EMP_REQ5_NO_REC', 35000.00);
        EmpIncome::where('employee_id', $employee->id)->delete();

        $drafts = $this->processingService->generateDraft($this->period, [$employee->id], [
            'income_types' => [
                $this->basicType->id => ['mode' => 'automated', 'display' => '1'],
                $this->allowanceType->id => ['mode' => 'automated', 'display' => '1'],
            ],
        ]);

        $draft = $drafts->first();
        $allPayIns = PayIn::where('pay_id', $draft->id)->get();

        // Must NOT generate PayIn from hidden master salary fallback
        $this->assertCount(0, $allPayIns);
        $this->assertEquals(0.00, (float) $draft->total_gross_pay);
    }
}
