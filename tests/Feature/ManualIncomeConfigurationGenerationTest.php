<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
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

class ManualIncomeConfigurationGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected IncomeType $otType;
    protected IncomeType $allowanceType;
    protected IncomeType $bonusType;
    protected IncomeType $otherType;
    protected IncomeType $customType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_manual_inc@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Manual Income Corp',
            'company_code' => 'MANINC',
            'email' => 'manualinc@corp.com',
            'status' => '1',
        ]);

        $this->dept = Department::create([
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
        $this->otType = IncomeType::where('code', 'OVERTIME')->firstOrFail();
        $this->allowanceType = IncomeType::where('code', 'ALLOWANCE')->firstOrFail();
        $this->bonusType = IncomeType::where('code', 'BONUS')->firstOrFail();
        $this->otherType = IncomeType::where('code', 'OTHER')->firstOrFail();

        $this->customType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'COMMISSION',
            'name' => 'Custom Sales Commission',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Client reproduction case - Manual Mode honors submitted income values and quantities.
     */
    public function test_manual_mode_honors_submitted_income_values_and_quantities(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-MAN-01',
            'first_name' => 'Client',
            'last_name' => 'Repro',
            'monthly_rate' => 20000.00,
            'daily_rate' => 1000.00,
            'hourly_rate' => 100.00,
            'status' => 'active',
        ]);

        // Assign EmpIncome rates for employee
        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
        ], [
            'rate' => 100.00,
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->bonusType->id,
        ], [
            'rate' => 50.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->otherType->id,
        ], [
            'rate' => 20.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        EmpIncome::updateOrCreate([
            'employee_id' => $employee->id,
            'income_type_id' => $this->customType->id,
        ], [
            'rate' => 15.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $options = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'BASIC', 'display' => 1, 'amount' => 1],
                ['income_type' => 'OVERTIME', 'display' => 1, 'amount' => 2],
                ['income_type' => 'ALLOWANCE', 'display' => 1, 'amount' => 3],
                ['income_type' => 'BONUS', 'display' => 1, 'amount' => 4],
                ['income_type' => 'OTHER', 'display' => 1, 'amount' => 5],
                ['income_type' => 'COMMISSION', 'display' => 1, 'amount' => 6],
            ],
            'days_worked' => 10, // Attendance Days Worked
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $this->assertEquals(10.00, (float) $draft->days_worked);

        $payIns = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        // 1. Basic Pay (Basic=1 -> Monthly Cutoff Salary = 10,000.00)
        $basicPayIn = $payIns->firstWhere('income_type_id', $this->basicType->id);
        $this->assertNotNull($basicPayIn);
        $this->assertEquals(0.50, (float) $basicPayIn->number_input);
        $this->assertEquals(10000.00, (float) $basicPayIn->amount);

        // 2. Overtime Pay (Overtime=2 -> 2 OT Hours * (100 * 1.25) = 250.00)
        $otPayIn = $payIns->firstWhere('income_type_id', $this->otType->id);
        $this->assertNotNull($otPayIn);
        $this->assertEquals(2.00, (float) $otPayIn->number_input);
        $this->assertEquals(125.00, (float) $otPayIn->rate);
        $this->assertEquals(250.00, (float) $otPayIn->amount);

        // 3. Allowance (Allowance=3 -> 3 units * 100.00 rate = 300.00)
        $allowancePayIn = $payIns->firstWhere('income_type_id', $this->allowanceType->id);
        $this->assertNotNull($allowancePayIn);
        $this->assertEquals(3.00, (float) $allowancePayIn->number_input);
        $this->assertEquals(100.00, (float) $allowancePayIn->rate);
        $this->assertEquals(300.00, (float) $allowancePayIn->amount);

        // 4. Bonus (Bonus=4 -> 4 units * 50.00 rate = 200.00)
        $bonusPayIn = $payIns->firstWhere('income_type_id', $this->bonusType->id);
        $this->assertNotNull($bonusPayIn);
        $this->assertEquals(4.00, (float) $bonusPayIn->number_input);
        $this->assertEquals(50.00, (float) $bonusPayIn->rate);
        $this->assertEquals(200.00, (float) $bonusPayIn->amount);

        // 5. Other Income (Other=5 -> 5 units * 20.00 rate = 100.00)
        $otherPayIn = $payIns->firstWhere('income_type_id', $this->otherType->id);
        $this->assertNotNull($otherPayIn);
        $this->assertEquals(5.00, (float) $otherPayIn->number_input);
        $this->assertEquals(20.00, (float) $otherPayIn->rate);
        $this->assertEquals(100.00, (float) $otherPayIn->amount);

        // 6. Custom Income (Commission=6 -> 6 units * 15.00 rate = 90.00)
        $customPayIn = $payIns->firstWhere('income_type_id', $this->customType->id);
        $this->assertNotNull($customPayIn);
        $this->assertEquals(6.00, (float) $customPayIn->number_input);
        $this->assertEquals(15.00, (float) $customPayIn->rate);
        $this->assertEquals(90.00, (float) $customPayIn->amount);
    }

    /**
     * Test 2: Unchecked income (display = 0) is excluded from draft generation.
     */
    public function test_unchecked_income_is_excluded_from_manual_generation(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-EXCL-01',
            'first_name' => 'Excl',
            'last_name' => 'Worker',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'BASIC', 'display' => 1, 'amount' => 1],
                ['income_type' => 'ALLOWANCE', 'display' => 0, 'amount' => 500], // Unchecked
                ['income_type' => 'BONUS', 'display' => 1, 'amount' => 200],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payIns = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        $this->assertNotNull($payIns->firstWhere('income_type_id', $this->basicType->id));
        $this->assertNotNull($payIns->firstWhere('income_type_id', $this->bonusType->id));
        $this->assertNull($payIns->firstWhere('income_type_id', $this->allowanceType->id));
    }

    /**
     * Test 3: Automated mode safeguard - Unassigned catalog incomes are NOT created in automated mode.
     */
    public function test_automated_mode_safeguard_does_not_create_unassigned_catalog_incomes(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-AUTO-SAFE',
            'first_name' => 'AutoSafe',
            'last_name' => 'Worker',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        // Create an unassigned catalog IncomeType AFTER employee creation
        $unassignedType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'UNASSIGNED_PERK',
            'name' => 'Unassigned Perk',
            'is_active' => true,
        ]);

        $options = [
            'use_fixed_income_amount' => false, // Automated mode
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payIns = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        // Verifies unassigned catalog income is NOT generated in automated mode
        $this->assertNull($payIns->firstWhere('income_type_id', $unassignedType->id));
    }

    /**
     * Test 4: Cross-company income isolation - Another company's custom IncomeType cannot generate a PayIn.
     */
    public function test_cross_company_income_type_cannot_be_generated_in_manual_mode(): void
    {
        $otherCompany = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company B Corp',
            'company_code' => 'COMPB',
            'email' => 'compb@test.com',
            'status' => '1',
        ]);

        $otherCompanyIncome = IncomeType::create([
            'company_id' => $otherCompany->id,
            'code' => 'OTHER_COMPANY_PERK',
            'name' => 'Other Company Perk',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-COMP-A',
            'first_name' => 'CompA',
            'last_name' => 'Worker',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_income_amount' => true,
            'incomes' => [
                ['income_type' => 'OTHER_COMPANY_PERK', 'display' => 1, 'amount' => 500.00],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payIns = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        // Verifies Company B's income type was NOT resolved for Company A's draft
        $this->assertNull($payIns->firstWhere('income_type_id', $otherCompanyIncome->id));
    }
}
