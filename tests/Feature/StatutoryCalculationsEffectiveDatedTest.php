<?php

namespace Tests\Feature;

use App\Exceptions\StatutoryRuleNotFoundException;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\StatutoryRule;
use App\Services\PayrollProcessingService;
use App\Services\StatutoryCalculationService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatutoryCalculationsEffectiveDatedTest extends TestCase
{
    use RefreshDatabase;

    protected StatutoryCalculationService $service;
    protected PayrollProcessingService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);

        $this->service = app(StatutoryCalculationService::class);
        $this->payrollService = app(PayrollProcessingService::class);
    }

    /** @test */
    public function sss_2025_schedule_boundary_calculations()
    {
        $rule = $this->service->resolveRule('sss', '2025-01-15');

        $this->assertEquals('SSS_2025_01', $rule->version_code);

        // Boundary 1: Minimum MSC (Salary 5,000)
        $res5k = $this->service->calculateSSS(5000.00, $rule);
        $this->assertEquals(5000.00, $res5k['msc']);
        $this->assertEquals(250.00, $res5k['monthly_ee']);
        $this->assertEquals(500.00, $res5k['monthly_er']);
        $this->assertEquals(10.00, $res5k['monthly_ec']);

        // Boundary 2: EC Step Transition (Salary 15,000)
        $res15k = $this->service->calculateSSS(15000.00, $rule);
        $this->assertEquals(15000.00, $res15k['msc']);
        $this->assertEquals(750.00, $res15k['monthly_ee']);
        $this->assertEquals(1500.00, $res15k['monthly_er']);
        $this->assertEquals(30.00, $res15k['monthly_ec']);

        // Boundary 3: WISP Start (Salary 25,000 -> Regular MSC 20k + WISP MSC 5k)
        $res25k = $this->service->calculateSSS(25000.00, $rule);
        $this->assertEquals(25000.00, $res25k['msc']);
        $this->assertEquals(1250.00, $res25k['monthly_ee']); // 1,000 regular + 250 WISP
        $this->assertEquals(2500.00, $res25k['monthly_er']); // 2,000 regular + 500 WISP
        $this->assertEquals(30.00, $res25k['monthly_ec']);

        // Boundary 4: Maximum MSC (Salary 35,000 -> Regular MSC 20k + WISP MSC 15k)
        $res35k = $this->service->calculateSSS(35000.00, $rule);
        $this->assertEquals(35000.00, $res35k['msc']);
        $this->assertEquals(1750.00, $res35k['monthly_ee']); // 1,000 regular + 750 WISP
        $this->assertEquals(3500.00, $res35k['monthly_er']); // 2,000 regular + 1,500 WISP
        $this->assertEquals(30.00, $res35k['monthly_ec']);

        // Boundary 5: Above Maximum MSC (Salary 50,000 -> capped at MSC 35,000)
        $res50k = $this->service->calculateSSS(50000.00, $rule);
        $this->assertEquals(35000.00, $res50k['msc']);
        $this->assertEquals(1750.00, $res50k['monthly_ee']);
        $this->assertEquals(3500.00, $res50k['monthly_er']);
    }

    /** @test */
    public function sss_2024_historical_pay_date_resolves_2024_schedule()
    {
        $rule = $this->service->resolveRule('sss', '2024-06-15');

        $this->assertEquals('SSS_2024_01', $rule->version_code);

        // Salary 30,000 in 2024 schedule (Max MSC 30,000, 4.5% EE, 9.5% ER)
        $res = $this->service->calculateSSS(30000.00, $rule);
        $this->assertEquals(30000.00, $res['msc']);
        $this->assertEquals(1350.00, $res['monthly_ee']); // 900 regular + 450 WISP
        $this->assertEquals(2850.00, $res['monthly_er']); // 1,900 regular + 950 WISP
        $this->assertEquals(30.00, $res['monthly_ec']);
    }

    /** @test */
    public function philhealth_floor_ceiling_and_rate_boundary_calculations()
    {
        $rule = $this->service->resolveRule('philhealth', '2025-01-15');

        $this->assertEquals('PHILHEALTH_2024_2025', $rule->version_code);

        // Below floor (Salary 8,000 -> capped at floor 10,000)
        $res8k = $this->service->calculatePhilHealth(8000.00, $rule);
        $this->assertEquals(250.00, $res8k['monthly_ee']);
        $this->assertEquals(250.00, $res8k['monthly_er']);

        // Floor boundary (Salary 10,000)
        $res10k = $this->service->calculatePhilHealth(10000.00, $rule);
        $this->assertEquals(250.00, $res10k['monthly_ee']);
        $this->assertEquals(250.00, $res10k['monthly_er']);

        // Mid salary (Salary 50,000 -> 2.5% EE, 2.5% ER)
        $res50k = $this->service->calculatePhilHealth(50000.00, $rule);
        $this->assertEquals(1250.00, $res50k['monthly_ee']);
        $this->assertEquals(1250.00, $res50k['monthly_er']);

        // Ceiling boundary (Salary 100,000)
        $res100k = $this->service->calculatePhilHealth(100000.00, $rule);
        $this->assertEquals(2500.00, $res100k['monthly_ee']);
        $this->assertEquals(2500.00, $res100k['monthly_er']);

        // Above ceiling (Salary 150,000 -> capped at ceiling 100,000)
        $res150k = $this->service->calculatePhilHealth(150000.00, $rule);
        $this->assertEquals(2500.00, $res150k['monthly_ee']);
        $this->assertEquals(2500.00, $res150k['monthly_er']);
    }

    /** @test */
    public function pagibig_10k_salary_cap_and_rate_transition_calculations()
    {
        $rule = $this->service->resolveRule('pagibig', '2025-01-15');

        $this->assertEquals('PAGIBIG_2024_02', $rule->version_code);

        // Low salary (Salary 1,000 <= 1,500: EE 1%, ER 2%)
        $res1k = $this->service->calculatePagIbig(1000.00, $rule);
        $this->assertEquals(10.00, $res1k['monthly_ee']);
        $this->assertEquals(20.00, $res1k['monthly_er']);

        // Transition boundary (Salary 1,500: EE 1%, ER 2%)
        $res1500 = $this->service->calculatePagIbig(1500.00, $rule);
        $this->assertEquals(15.00, $res1500['monthly_ee']);
        $this->assertEquals(30.00, $res1500['monthly_er']);

        // Regular salary (Salary 5,000 > 1,500: EE 2%, ER 2%)
        $res5k = $this->service->calculatePagIbig(5000.00, $rule);
        $this->assertEquals(100.00, $res5k['monthly_ee']);
        $this->assertEquals(100.00, $res5k['monthly_er']);

        // Cap boundary (Salary 10,000 -> Max fund salary cap 10,000: EE 2%, ER 2%)
        $res10k = $this->service->calculatePagIbig(10000.00, $rule);
        $this->assertEquals(200.00, $res10k['monthly_ee']);
        $this->assertEquals(200.00, $res10k['monthly_er']);

        // Above cap (Salary 50,000 -> capped at 10,000 salary basis: EE 200, ER 200. NOT PHP 5,000 cap / EE 100!)
        $res50k = $this->service->calculatePagIbig(50000.00, $rule);
        $this->assertEquals(200.00, $res50k['monthly_ee']);
        $this->assertEquals(200.00, $res50k['monthly_er']);
    }

    /** @test */
    public function train_withholding_tax_bracket_boundary_calculations()
    {
        $rule = $this->service->resolveRule('tax', '2025-01-15');

        $this->assertEquals('TAX_TRAIN_2023', $rule->version_code);

        // Exempt Tier: Annualized income <= 250,000 (Taxable per semi-monthly period = 10,000 -> Annualized 240,000)
        $taxExempt = $this->service->calculateWithholdingTax(10000.00, 24, $rule);
        $this->assertEquals(0.00, $taxExempt);

        // Tier 2: Annualized income 360,000 (Semi-monthly 15,000 -> Annual 360,000; Excess over 250k = 110k @ 15% = 16,500 annual / 24 = 687.50)
        $taxTier2 = $this->service->calculateWithholdingTax(15000.00, 24, $rule);
        $this->assertEquals(687.50, $taxTier2);

        // Tier 4: Annualized income 1,200,000 (Monthly 100,000 -> Excess over 800k = 400k @ 25% = 100k + 102.5k = 202.5k annual / 12 = 16,875.00)
        $taxTier4 = $this->service->calculateWithholdingTax(100000.00, 12, $rule);
        $this->assertEquals(16875.00, $taxTier4);
    }

    /** @test */
    public function missing_or_expired_statutory_rule_blocks_payroll_draft_generation()
    {
        $user = \App\Models\User::create([
            'name' => 'User Old',
            'email' => 'old_user@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'name' => 'Test Company Old',
            'email' => 'old@company.test',
            'status' => '1',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-OLD-001',
            'first_name' => 'Old',
            'last_name' => 'Employee',
            'date_hired' => '2010-01-01',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        // Period with a pay_date before any effective rules exist
        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Legacy 2010 Period',
            'period_start' => '2010-01-01',
            'period_end' => '2010-01-15',
            'pay_date' => '2010-01-20',
            'status' => 'open',
        ]);

        $this->expectException(StatutoryRuleNotFoundException::class);
        $this->payrollService->generateDraft($period, [$employee->id]);
    }

    /** @test */
    public function payroll_draft_generation_records_statutory_rule_versions_on_pay_header()
    {
        $user = \App\Models\User::create([
            'name' => 'User 2025',
            'email' => 'user2025@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($user);

        $company = Company::create([
            'user_id' => $user->id,
            'name' => 'Test Company 2025',
            'email' => 'test2025@company.test',
            'status' => '1',
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'name' => 'General Dept',
            'code' => 'GEN',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-2025-001',
            'first_name' => 'Jan',
            'last_name' => 'TwentyFive',
            'date_hired' => '2025-01-01',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'January 2025 Semi-Monthly 1',
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-15',
            'pay_date' => '2025-01-20',
            'status' => 'open',
        ]);

        $headers = $this->payrollService->generateDraft($period, [$employee->id]);
        $header = PayHeader::withoutGlobalScopes()->find($headers->first()->id);

        $this->assertNotNull($header);
        $this->assertNotNull($header->policy_version);

        $versionData = json_decode($header->policy_version, true);
        $this->assertEquals('SSS_2025_01', $versionData['sss']);
        $this->assertEquals('PHILHEALTH_2024_2025', $versionData['philhealth']);
        $this->assertEquals('PAGIBIG_2024_02', $versionData['pagibig']);
        $this->assertEquals('TAX_TRAIN_2023', $versionData['tax']);

        // Verify Pag-IBIG contribution EE share is calculated using PHP 10,000 cap
        // Semi-monthly PHP 30,000 salary -> Monthly Pag-IBIG EE = 200.00 -> Semi-monthly EE = 100.00
        $pagibigContrib = \App\Models\GovernmentContribution::withoutGlobalScopes()
            ->where('payroll_draft_id', $header->id)
            ->where('type', 'pagibig')
            ->first();

        $this->assertNotNull($pagibigContrib);
        $this->assertEquals(100.00, (float) $pagibigContrib->employee_share);
        $this->assertEquals(100.00, (float) $pagibigContrib->employer_share);

        $pagibigType = DeductionType::withoutGlobalScopes()->where('code', 'PAGIBIG')->first();
        $pagibigPayout = $header->payOuts()->withoutGlobalScopes()->where('deduction_type_id', $pagibigType->id)->first();

        $this->assertNotNull($pagibigPayout);
        $this->assertEquals(100.00, (float) $pagibigPayout->rate);
    }
}
