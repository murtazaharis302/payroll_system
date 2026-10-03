<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCompanyDefaultsTest extends TestCase
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

    public function test_draft_generation_with_company_defaults_present_completes_without_class_errors(): void
    {
        $owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $owner->id,
            'name' => 'Defaults Test Corp',
            'company_code' => 'DEF-0001',
            'email' => 'defaults@test.com',
            'status' => 1,
        ]);

        $company->setDeductionDefaults([
            'use_fixed_amount' => true,
            'deductions' => [
                ['name' => 'Health Insurance', 'amount' => 500],
            ],
        ]);

        $employeeMaster = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-DEF-01',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'daily_rate' => 1000,
            'monthly_rate' => 22000,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        // Unset relation so $period->company is null and forces Company::find resolution branch
        $period->unsetRelation('company');
        $this->assertFalse($period->relationLoaded('company'));

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($period);

        $this->assertNotNull($drafts);
    }

    public function test_draft_generation_with_company_defaults_absent_completes_without_class_errors(): void
    {
        $owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $owner->id,
            'name' => 'No Defaults Corp',
            'company_code' => 'NODEF-001',
            'email' => 'nodefaults@test.com',
            'status' => 1,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 2nd Half',
            'period_start' => '2026-08-16',
            'period_end' => '2026-08-31',
            'pay_date' => '2026-09-01',
            'status' => 'open',
        ]);

        $period->unsetRelation('company');

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($period);

        $this->assertNotNull($drafts);
    }

    public function test_draft_generation_with_missing_company_throws_domain_validation_exception(): void
    {
        $owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $owner->id,
            'name' => 'Temp Corp',
            'company_code' => 'TEMP-0001',
            'email' => 'temp@test.com',
            'status' => 1,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Orphan Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        // Delete company to simulate missing company relation/record
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
        \Illuminate\Support\Facades\DB::table('companies')->where('id', $company->id)->delete();
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');

        $period->unsetRelation('company');

        $service = new PayrollProcessingService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Payroll period [{$period->id}] has no valid associated company.");

        $service->generateDraft($period);
    }
}
