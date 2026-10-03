<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\DeductionType;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemKeyScopeAndOverrideTest extends TestCase
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
            'email' => 'admin@scope.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Scope Test Corp',
            'email' => 'corp@scope.test',
            'code' => 'STC',
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

        $dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Engineering',
            'code' => 'ENG',
            'is_active' => true,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'STC-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@scope.test',
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

    public function test_system_key_cannot_be_overridden_via_mass_assignment()
    {
        $type = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Hacker Pay',
            'code' => 'HACK',
            'system_key' => 'basic',
            'is_active' => true,
        ]);

        $this->assertNull($type->system_key);
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

    public function test_cannot_create_duplicate_global_system_key()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Duplicate system_key \'basic\' is not allowed for global scope/i');

        $duplicate = new IncomeType();
        $duplicate->company_id = null;
        $duplicate->name = 'Duplicate Global Basic';
        $duplicate->code = 'DUP_GLOBAL_BASIC';
        $duplicate->system_key = 'basic';
        $duplicate->is_taxable = true;
        $duplicate->is_active = true;
        $duplicate->save();
    }

    public function test_cannot_create_duplicate_global_deduction_system_key()
    {
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Duplicate system_key \'sss\' is not allowed for global scope/i');

        $duplicate = new DeductionType();
        $duplicate->company_id = null;
        $duplicate->name = 'Duplicate Global SSS';
        $duplicate->code = 'DUP_GLOBAL_SSS';
        $duplicate->system_key = 'sss';
        $duplicate->type = 'statutory';
        $duplicate->is_active = true;
        $duplicate->save();
    }

    public function test_emp_income_basic_pay_rate_override_works_with_system_key()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $basic = IncomeType::where('system_key', 'basic')->first();
        $basic->update(['name' => 'Custom Company Basic', 'code' => 'CUST_BASIC']);

        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $basic->id,
            'rate' => 40000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$this->employee->id], [
            'days_worked' => [$this->employee->id => 13],
        ]);
        $draft = $drafts->first();

        // 40000 / 2 = 20000 for 13 days
        $this->assertEquals(20000.00, round((float)$draft->basic_pay, 2));
    }

    public function test_payroll_employee_master_and_quick_record_allow_basic_pay_override()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->actingAs($this->adminUser);

        $basicType = IncomeType::where('system_key', 'basic')->first();

        // 1. Quick Master save allows BASIC EmpIncome override
        $response = $this->postJson(route('admin.payroll-employees.company-master.update-quick', $this->employee->id), [
            'incomes' => [
                [
                    'income_type_id' => $basicType->id,
                    'amount' => 45000.00,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('emp_income', [
            'employee_id' => $this->employee->id,
            'income_type_id' => $basicType->id,
            'rate' => 45000.00,
        ]);

        // 2. Fetch employee master details returns BASIC in available incomes
        $recordResponse = $this->getJson(route('admin.payroll-employees.company-master.details', $this->employee->id));
        $recordResponse->assertStatus(200);
        $data = $recordResponse->json();

        $basicInTypes = collect($data['income_types'] ?? [])->firstWhere('id', $basicType->id);
        $this->assertNotNull($basicInTypes, 'BASIC should be available in income types list');

        $basicInAssigned = collect($data['incomes'] ?? [])->firstWhere('income_type_id', $basicType->id);
        $this->assertNotNull($basicInAssigned, 'BASIC override should be present in assigned incomes');
        $this->assertEquals(45000.00, (float)($basicInAssigned['amount'] ?? 0));
    }

    public function test_company_specific_system_key_takes_precedence_over_global_system_key()
    {
        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $globalBasic = IncomeType::whereNull('company_id')->where('system_key', 'basic')->first();
        $globalSss = DeductionType::whereNull('company_id')->where('system_key', 'sss')->first();

        // Create company-specific custom basic and sss records for $this->company
        $companyBasic = new IncomeType([
            'company_id' => $this->company->id,
            'name' => 'Company 1 Specific Basic',
            'code' => 'C1_BASIC',
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $companyBasic->system_key = 'basic';
        $companyBasic->save();

        $companySss = new DeductionType([
            'company_id' => $this->company->id,
            'name' => 'Company 1 Specific SSS',
            'code' => 'C1_SSS',
            'type' => 'statutory',
            'is_active' => true,
        ]);
        $companySss->system_key = 'sss';
        $companySss->save();

        // 1. findBySystemKey with company_id selects company-specific record
        $this->assertEquals($companyBasic->id, IncomeType::findBySystemKey('basic', $this->company->id)?->id);
        $this->assertEquals($companySss->id, DeductionType::findBySystemKey('sss', $this->company->id)?->id);

        // 2. findBySystemKey for another company falls back to global record
        $this->assertEquals($globalBasic->id, IncomeType::findBySystemKey('basic', 9999)?->id);
        $this->assertEquals($globalSss->id, DeductionType::findBySystemKey('sss', 9999)?->id);

        // 3. Payroll generation uses company-specific type
        $service = app(PayrollProcessingService::class);
        $drafts = $service->generateDraft($this->period, [$this->employee->id], [
            'days_worked' => [$this->employee->id => 13],
        ]);
        $draft = $drafts->first();

        $basicPayIn = $draft->payIns->firstWhere('income_type_id', $companyBasic->id);
        $this->assertNotNull($basicPayIn, 'PayIn must use company-specific Basic Pay type');

        $sssPayOut = $draft->payOuts->firstWhere('deduction_type_id', $companySss->id);
        $this->assertNotNull($sssPayOut, 'PayOut must use company-specific SSS deduction type');
    }
}
