<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\IncomeType;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BasicIncomeSystemKeyFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_active_income_types_including_renamed_basic_appear_in_selector(): void
    {
        $this->seed(IncomeTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $user = User::create([
            'name' => 'Company Admin',
            'email' => 'basic-filter@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'name' => 'Basic Filter Company',
            'company_code' => 'BASIC_FILTER',
            'email' => 'company-basic-filter@test.com',
            'status' => '1',
        ]);
        $user->update(['company_id' => $company->id]);

        PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'September 2026 Cutoff 1',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'pay_date' => '2026-09-16',
            'status' => 'open',
        ]);

        $basicType = IncomeType::withoutGlobalScopes()
            ->where('system_key', 'basic')
            ->firstOrFail();

        $basicType->update(['code' => 'BASIC1']);

        $customIncome = IncomeType::create([
            'company_id' => $company->id,
            'name' => 'Custom Allowance',
            'code' => 'CUSTOM_ALLOWANCE',
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('payroll-drafts.create', ['company_id' => $company->id]));

        $response->assertStatus(200);

        $viewIncomes = collect($response->viewData('allCompanyIncomeTypes'));

        $this->assertTrue($viewIncomes->contains('id', $basicType->id));
        $this->assertTrue($viewIncomes->contains('code', 'BASIC1'));
        $this->assertTrue($viewIncomes->contains('id', $customIncome->id));
    }
}
