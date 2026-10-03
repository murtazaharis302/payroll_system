<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollLineCalculator;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPolicyUnificationTest extends TestCase
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

    public function test_table_driven_rate_conversions_and_overtime_multipliers(): void
    {
        $policy = PayrollPolicy::makeDefaultPolicy();
        // Default policy: 26 workdays/month, 8 hours/day, 1.25 regular OT, 1.30 rest-day OT, 2.00 holiday OT

        $monthlyEmp = new EmployeeMaster(['monthly_rate' => 26000.00]);
        $dailyEmp = new EmployeeMaster(['daily_rate' => 1000.00]);
        $hourlyEmp = new EmployeeMaster(['hourly_rate' => 125.00]);

        // Monthly employee checks
        $this->assertEquals(1000.00, $policy->dailyRate($monthlyEmp));
        $this->assertEquals(125.00, $policy->hourlyRate($monthlyEmp));
        $this->assertEquals(156.25, $policy->overtimeRate($monthlyEmp, 'regular'));
        $this->assertEquals(162.50, $policy->overtimeRate($monthlyEmp, 'rest_day'));
        $this->assertEquals(250.00, $policy->overtimeRate($monthlyEmp, 'holiday'));

        // Daily employee checks
        $this->assertEquals(1000.00, $policy->dailyRate($dailyEmp));
        $this->assertEquals(125.00, $policy->hourlyRate($dailyEmp));
        $this->assertEquals(156.25, $policy->overtimeRate($dailyEmp, 'regular'));

        // Hourly employee checks
        $this->assertEquals(1000.00, $policy->dailyRate($hourlyEmp));
        $this->assertEquals(125.00, $policy->hourlyRate($hourlyEmp));
        $this->assertEquals(156.25, $policy->overtimeRate($hourlyEmp, 'regular'));
    }

    public function test_custom_company_payroll_policy_applies_to_generation_and_updates(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Custom Policy Corp',
            'company_code' => 'POLICY-001',
            'email' => 'policy@corp.com',
            'status' => 1,
        ]);

        // Custom policy: 20 workdays/month, 8 hours/day, 1.50 regular OT
        $customPolicy = PayrollPolicy::create([
            'company_id' => $company->id,
            'policy_name' => 'Custom 20-Day Policy',
            'pay_frequency' => 'semi_monthly',
            'workdays_per_month' => 20.00,
            'hours_per_day' => 8.00,
            'regular_ot_multiplier' => 1.5000,
            'effective_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-POL-01',
            'first_name' => 'Custom',
            'last_name' => 'PolicyWorker',
            'email' => 'policyworker@test.com',
            'monthly_rate' => 20000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        // Hourly rate under custom policy = 20,000 / 20 / 8 = 125.00/hr
        // OT rate under 1.50x multiplier = 125 * 1.50 = 187.50/hr
        $resolvedPolicy = PayrollPolicy::forCompany($company->id, '2026-08-01');
        $this->assertEquals(125.00, $resolvedPolicy->hourlyRate($employee));
        $this->assertEquals(187.50, $resolvedPolicy->overtimeRate($employee, 'regular'));

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id], [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'OVERTIME', 'display' => true, 'amount' => 10], // 10 hrs OT
            ],
        ]);

        $draft = $headers->first();
        $otPayIn = $draft->payIns()->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->first();
        $this->assertNotNull($otPayIn);
        $this->assertEquals(187.50, (float) $otPayIn->rate);
        $this->assertEquals(1875.00, (float) $otPayIn->amount);

        // Verify opening edit page works cleanly without missing view variables
        $this->actingAs($admin);
        $editPageResponse = $this->get(route('payroll-drafts.edit', $draft->id));
        $editPageResponse->assertStatus(200);
        $editPageResponse->assertViewHas('allCompanyDeductionTypes');
        $editPageResponse->assertViewHas('policy');

        // Verify update uses the same policy
        $response = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 10.0,
            'overtime_hours' => 10.0,
            'basic_pay' => 10000.00,
        ]);

        $response->assertSessionHasNoErrors();
        $draft->refresh();

        $updatedOtPayIn = $draft->payIns()->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->first();
        $this->assertEquals(187.50, (float) $updatedOtPayIn->rate);
        $this->assertEquals(1875.00, (float) $updatedOtPayIn->amount);
    }

    public function test_effective_dated_policy_does_not_alter_historical_payrolls(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Historical Corp',
            'company_code' => 'HIST-001',
            'email' => 'hist@corp.com',
            'status' => 1,
        ]);

        // Old policy effective 2026-01-01 (1.25x OT)
        $oldPolicy = PayrollPolicy::create([
            'company_id' => $company->id,
            'policy_name' => '2026 Old Policy',
            'workdays_per_month' => 26.00,
            'hours_per_day' => 8.00,
            'regular_ot_multiplier' => 1.2500,
            'effective_date' => '2026-01-01',
            'is_active' => true,
        ]);

        // New policy effective 2026-09-01 (1.50x OT)
        $newPolicy = PayrollPolicy::create([
            'company_id' => $company->id,
            'policy_name' => '2026 New Policy',
            'workdays_per_month' => 26.00,
            'hours_per_day' => 8.00,
            'regular_ot_multiplier' => 1.5000,
            'effective_date' => '2026-09-01',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-HIST-01',
            'first_name' => 'Hist',
            'last_name' => 'Worker',
            'email' => 'histworker@test.com',
            'hourly_rate' => 100.00,
        ]);

        // Period 1: August 2026 (resolved against old policy => 1.25x)
        $augustPolicy = PayrollPolicy::forCompany($company->id, '2026-08-15');
        $this->assertEquals(125.00, $augustPolicy->overtimeRate($employee, 'regular'));

        // Period 2: September 2026 (resolved against new policy => 1.50x)
        $septemberPolicy = PayrollPolicy::forCompany($company->id, '2026-09-15');
        $this->assertEquals(150.00, $septemberPolicy->overtimeRate($employee, 'regular'));
    }

    public function test_no_hardcoded_payroll_divisors_or_ot_multipliers_duplicated_outside_policy(): void
    {
        $appPath = app_path();
        
        // Scan app directory for hard-coded '/ 26 / 8' or '* 1.25'
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
        $matches = [];

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $filepath = $file->getPathname();
            // Ignore PayrollPolicy.php itself
            if (str_contains($filepath, 'PayrollPolicy.php')) {
                continue;
            }

            $content = file_get_contents($filepath);
            if (preg_match('/\/ 26 \/ 8|\* 1\.25/', $content)) {
                $matches[] = $filepath;
            }
        }

        $this->assertEmpty($matches, "Found hard-coded payroll divisors or OT multipliers in: " . implode(', ', $matches));
    }
}
