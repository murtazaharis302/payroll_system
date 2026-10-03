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
use App\Exceptions\PayrollGenerationSetupException;
use App\Services\PayrollProcessingService;
use App\Services\Reports\PayrollSummaryReportService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DynamicIncomeTypePayrollScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Company $otherCompany;
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
            'name' => 'Scenario Admin',
            'email' => 'admin@scenarios-income.test',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->user->id,
            'name' => 'Scenario Test Corp',
            'company_code' => 'STC_CORP',
            'email' => 'company@scenarios-income.test',
            'status' => '1',
        ]);
        $this->user->update(['company_id' => $this->company->id]);

        $this->dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'code' => 'DEPT_TEST',
            'name' => 'Test Department',
            'is_active' => true,
        ]);

        $this->otherCompany = Company::create([
            'user_id' => $this->user->id,
            'name' => 'Other Corp',
            'company_code' => 'OTHER_CORP',
            'email' => 'other@scenarios-income.test',
            'status' => '1',
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
            'first_name' => 'Jane',
            'last_name' => $code,
            'email' => strtolower($code) . '@scenarios.test',
            'status' => 'active',
            'monthly_rate' => $monthlyRate,
            'daily_rate' => round($monthlyRate / 26, 2),
            'hourly_rate' => round($monthlyRate / 26 / 8, 2),
        ]);
    }

    public function test_req6_selective_exclusion_in_automated_mode_prevents_payin_generation(): void
    {
        $employee = $this->createEmployee('EMP_REQ6', 30000.00);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 30000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
            'rate' => 5000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // Generate with basic excluded (display = 0) and allowance included (display = 1)
        $drafts = $this->processingService->generateDraft($this->period, [$employee->id], [
            'income_types' => [
                $this->basicType->id => ['mode' => 'automated', 'display' => '0'],
                $this->allowanceType->id => ['mode' => 'automated', 'display' => '1'],
            ],
        ]);

        $draft = $drafts->first();
        $allPayIns = PayIn::where('pay_id', $draft->id)->get();

        $this->assertCount(1, $allPayIns);
        $this->assertCount(0, $allPayIns->where('income_type_id', $this->basicType->id));
        $this->assertCount(1, $allPayIns->where('income_type_id', $this->allowanceType->id));
        $this->assertEquals(2500.00, (float) ($allPayIns->first()->number_input * $allPayIns->first()->rate));
    }

    public function test_req7_manual_mode_generates_payin_with_manual_amount_without_emp_income(): void
    {
        // Employee has NO EmpIncome records at all
        $employee = $this->createEmployee('EMP_REQ7', 0.00);

        $customBonus = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'One-time Performance Bonus',
            'code' => 'PERF_BONUS',
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $drafts = $this->processingService->generateDraft($this->period, [$employee->id], [
            'income_types' => [
                $customBonus->id => ['mode' => 'fixed', 'display' => '1', 'amount' => 7500.00],
            ],
        ]);

        $draft = $drafts->first();
        $bonusPayIns = PayIn::where('pay_id', $draft->id)->where('income_type_id', $customBonus->id)->get();

        $this->assertCount(1, $bonusPayIns);
        $this->assertEquals(1.00, (float) $bonusPayIns->first()->number_input);
        $this->assertEquals(7500.00, (float) $bonusPayIns->first()->rate);
        $this->assertEquals(7500.00, (float) $draft->total_gross_pay);
    }

    public function test_req8_company_scoped_validation_rejects_cross_company_income_type_id(): void
    {
        $employee = $this->createEmployee('EMP_REQ8', 30000.00);

        $otherCompanyIncome = IncomeType::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Company Benefit',
            'code' => 'OTHER_BENEFIT',
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $this->expectException(PayrollGenerationSetupException::class);

        $this->processingService->generateDraft($this->period, [$employee->id], [
            'income_types' => [
                $otherCompanyIncome->id => ['mode' => 'automated', 'display' => '1'],
            ],
        ]);
    }

    public function test_req9_frequency_calculations_preserved(): void
    {
        $employee = $this->createEmployee('EMP_REQ9', 24000.00);

        // 1. Monthly frequency recurring income -> semi-monthly draft gets factor 0.5
        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 24000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $drafts = $this->processingService->generateDraft($this->period, [$employee->id], [
            'income_types' => [
                $this->basicType->id => ['mode' => 'automated', 'display' => '1'],
            ],
        ]);

        $draft = $drafts->first();
        $payIn = $draft->payIns->where('income_type_id', $this->basicType->id)->first();

        $this->assertEquals(0.5, (float) $payIn->number_input);
        $this->assertEquals(24000.00, (float) $payIn->rate);
        $this->assertEquals(12000.00, (float) ($payIn->number_input * $payIn->rate));
    }

    public function test_req10_downstream_gross_and_reports_aggregate_cleanly(): void
    {
        $employee = $this->createEmployee('EMP_REQ10', 40000.00);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 40000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
            'rate' => 4000.00,
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

        // Gross pay = 20000 (basic) + 2000 (allowance) = 22000
        $this->assertEquals(22000.00, (float) $draft->total_gross_pay);
        $this->assertTrue((float) $draft->net_pay > 0);

        // Report aggregation
        $reportService = app(PayrollSummaryReportService::class);
        $report = $reportService->build($this->period, $this->company, $this->company->id);

        $this->assertCount(1, $report['payrolls']);
        $this->assertEquals(20000.00, $report['totals']['basic_amount']);
        $this->assertEquals(22000.00, $report['totals']['total_gross_pay']);
    }

    public function test_req11_no_name_heuristics_and_observer_rate_isolation(): void
    {
        // 1. EmployeeMasterObserver does NOT derive basic recurring rate from master monthly_rate
        $emp = $this->createEmployee('EMP_NO_DERIVE', 75000.00);
        $empIncomes = EmpIncome::withoutGlobalScopes()->where('employee_id', $emp->id)->get();
        $this->assertNotEmpty($empIncomes);
        foreach ($empIncomes as $inc) {
            $this->assertEquals(0.00, (float) $inc->rate, "Income type [{$inc->income_type_id}] must not derive master monthly rate.");
        }

        // 2. Custom income type with arbitrary name resolves strictly by income_type_id
        $customType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'CUSTOM_STIPEND_99',
            'name' => 'Arbitrary Non Keyword Stipend',
            'type' => 'taxable',
            'is_active' => true,
        ]);

        EmpIncome::create([
            'employee_id' => $emp->id,
            'income_type_id' => $customType->id,
            'rate' => 3000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $drafts = $this->processingService->generateDraft($this->period, [$emp->id], [
            'income_types' => [
                $customType->id => ['mode' => 'automated', 'display' => '0'],
            ],
        ]);

        $draft = $drafts->first();
        $payIns = PayIn::where('pay_id', $draft->id)->where('income_type_id', $customType->id)->get();
        $this->assertCount(0, $payIns, 'Excluded custom income type must be excluded based strictly on income_type_id without name heuristics.');
    }

    public function test_req12_arbitrary_renamed_basic_generates_correctly_without_keyword_dependency(): void
    {
        // Rename basic to completely arbitrary name/code containing zero "basic" / "salary" / "pay" keywords
        $this->basicType->update([
            'code' => 'EXECUTIVE_COMP_99',
            'name' => 'Retainer Remuneration',
        ]);

        $employee = $this->createEmployee('EMP_ARBITRARY_BASIC', 40000.00);

        EmpIncome::create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 40000.00,
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
        $this->assertEquals(40000.00, (float) $payIn->rate);
        $this->assertEquals(20000.00, (float) ($payIn->number_input * $payIn->rate));

        // Downstream summary report correctly aggregates basic by system_key metadata despite arbitrary name
        $reportService = app(\App\Services\Reports\PayrollSummaryReportService::class);
        $report = $reportService->build($this->period, $this->company, $this->company->id);
        $this->assertEquals(20000.00, $report['totals']['basic_amount']);
    }
}
