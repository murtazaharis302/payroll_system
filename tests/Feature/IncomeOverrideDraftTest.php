<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IncomeOverrideDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
    }

    public function test_draft_generation_with_manual_income_overrides()
    {
        $adminUser = User::create([
            'name' => 'Owner',
            'email' => 'owner_income@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Test Company Income',
            'email' => 'comp_income@test.com',
            'status' => '1',
        ]);

        $companyAdmin = User::create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'admin_income@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'code' => 'MGMT',
            'name' => 'Management',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-INC-001',
            'first_name' => 'Michael',
            'last_name' => 'Scott',
            'date_hired' => '2026-01-01',
            'position' => 'Regional Manager',
            'daily_rate' => 1000.00,
            'hourly_rate' => 125.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08-INC',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        $response = $this->actingAs($companyAdmin)->post(route('payroll-drafts.generate'), [
            'payroll_period_id' => $period->id,
            'employee_ids' => [$employee->id],
            'use_fixed_income_amount' => 1,
            'incomes' => [
                ['income_type' => 'BASIC', 'display' => 1, 'amount' => '10'],
                ['income_type' => 'OVERTIME', 'display' => 1, 'amount' => '8'],
                ['income_type' => 'ALLOWANCE', 'display' => 1, 'amount' => '500'],
                ['income_type' => 'BONUS', 'display' => 1, 'amount' => '1500'],
            ],
        ]);

        $response->assertRedirect();

        $header = PayHeader::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($header);

        // Basic pay line item: 10 days * 1000 daily rate = 10,000
        $basicPayIn = PayIn::where('pay_id', $header->id)
            ->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))
            ->first();

        $this->assertNotNull($basicPayIn);
        $this->assertEquals(10.0, (float) $basicPayIn->number_input);
        $this->assertEquals(1000.0, (float) $basicPayIn->rate);

        // OT line item: 8 hours * (125 * 1.25) = 1,250
        $otPayIn = PayIn::where('pay_id', $header->id)
            ->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))
            ->first();

        $this->assertNotNull($otPayIn);
        $this->assertEquals(8.0, (float) $otPayIn->number_input);

        // Bonus line item: flat 1500 -> number_input = 1500, rate = 1.0
        $bonusPayIn = PayIn::where('pay_id', $header->id)
            ->whereHas('incomeType', fn($q) => $q->where('code', 'BONUS'))
            ->first();

        $this->assertNotNull($bonusPayIn);
        $this->assertEquals(1500.0, (float) $bonusPayIn->number_input);
        $this->assertEquals(1.0, (float) $bonusPayIn->rate);
    }

    public function test_saving_company_wide_income_defaults()
    {
        $adminUser = User::create([
            'name' => 'Owner Defaults',
            'email' => 'owner_defaults@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Test Company Defaults',
            'email' => 'comp_defaults@test.com',
            'status' => '1',
        ]);

        $companyAdmin = User::create([
            'company_id' => $company->id,
            'name' => 'Company Admin Defaults',
            'email' => 'admin_defaults@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $response = $this->actingAs($companyAdmin)->post(route('payroll-drafts.save-company-income-defaults'), [
            'company_id' => $company->id,
            'use_fixed_income_amount' => 1,
            'incomes' => [
                ['income_type' => 'BASIC', 'display' => 1, 'amount' => 12],
                ['income_type' => 'ALLOWANCE', 'display' => 1, 'amount' => 800],
            ],
        ]);

        $response->assertRedirect();

        $company->refresh();
        $defaults = $company->income_defaults;

        $this->assertTrue((bool) $defaults['use_fixed_income_amount']);
        $this->assertCount(2, $defaults['incomes']);
        $this->assertEquals('BASIC', $defaults['incomes'][0]['income_type']);
        $this->assertEquals(12.0, $defaults['incomes'][0]['amount']);
    }
}
