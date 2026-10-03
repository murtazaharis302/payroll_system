<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpDeduction;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyMasterFinancialEntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected IncomeType $incomeType;
    protected DeductionType $deductionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Company Admin',
            'email' => 'admin@company.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $dept = \App\Models\Department::create([
            'company_id' => $this->company->id,
            'name' => 'General Dept',
            'code' => 'GEN',
            'is_active' => true,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-101',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'monthly_rate' => 25000.00,
            'daily_rate' => 961.54,
            'hourly_rate' => 120.19,
            'status' => 'active',
            'employment_type' => 'REGULAR',
        ]);

        $this->incomeType = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Food Allowance',
            'code' => 'FOOD_ALLOW',
            'type' => 'taxable',
            'is_active' => true,
        ]);

        $this->deductionType = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Uniform Fee',
            'code' => 'UNIFORM_FEE',
            'is_active' => true,
        ]);
    }

    public function test_company_master_page_contains_add_income_and_add_deduction_controls(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-employees.company-master'));

        $response->assertStatus(200);
        $response->assertSee('Add Income');
        $response->assertSee('Add Deduction');
        $response->assertSee('onclick="addIncomeRow()"', false);
        $response->assertSee('onclick="addDeductionRow()"', false);
    }

    public function test_employee_management_screens_include_unsaved_changes_protection(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@company.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        foreach ([
            route('admin.payroll-employees.company-master'),
            route('admin.payroll-employees.index'),
        ] as $route) {
            $response = $this->actingAs($superAdmin)->get($route);

            $response->assertStatus(200);
            $response->assertSee('id="employeeUnsavedChangesModal"', false);
            $response->assertSee("window.addEventListener('beforeunload'", false);
            $response->assertSee("event.target.closest('a')", false);
            $response->assertSee("event.target.closest('.employee-item')", false);
        }
    }

    public function test_update_quick_master_saves_new_income_and_deduction_for_employee(): void
    {
        $payload = [
            'incomes' => [
                [
                    'income_type_id' => $this->incomeType->id,
                    'amount' => 1500.00,
                ],
            ],
            'deductions' => [
                [
                    'deduction_type_id' => $this->deductionType->id,
                    'amount' => 350.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('payroll-employees.company-master.update-quick', $this->employee->id), $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('emp_income', [
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->incomeType->id,
            'is_active' => 1,
        ]);

        $this->assertEquals(
            1500.00,
            (float) EmpIncome::where('employee_id', $this->employee->id)->where('income_type_id', $this->incomeType->id)->first()->amount
        );

        $this->assertDatabaseHas('emp_deductions', [
            'employee_id' => $this->employee->id,
            'deduction_type_id' => $this->deductionType->id,
            'is_active' => 1,
        ]);

        $this->assertEquals(
            350.00,
            (float) EmpDeduction::where('employee_id', $this->employee->id)->where('deduction_type_id', $this->deductionType->id)->first()->amount
        );
    }

    public function test_reloading_employee_details_returns_saved_income_and_deduction(): void
    {
        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->incomeType->id,
            'amount' => 2000.00,
            'rate' => 2000.00,
            'is_active' => true,
        ]);

        EmpDeduction::create([
            'employee_id' => $this->employee->id,
            'deduction_type_id' => $this->deductionType->id,
            'amount' => 500.00,
            'rate' => 500.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('payroll-employees.company-master.details', $this->employee->id));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
        $response->assertJsonFragment([
            'income_type_id' => $this->incomeType->id,
            'amount' => 2000.00,
        ]);
        $response->assertJsonFragment([
            'deduction_type_id' => $this->deductionType->id,
            'amount' => 500.00,
        ]);
    }

    public function test_payroll_processing_includes_recurring_income_and_deduction_added_via_company_master(): void
    {
        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->incomeType->id,
            'amount' => 1200.00,
            'rate' => 1200.00,
            'is_active' => true,
        ]);

        EmpDeduction::create([
            'employee_id' => $this->employee->id,
            'deduction_type_id' => $this->deductionType->id,
            'amount' => 400.00,
            'rate' => 400.00,
            'is_active' => true,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '08/01/2026 to 08/15/2026',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $service->generateDraft($period, [$this->employee->id]);

        $header = PayHeader::where('payroll_period_id', $period->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($header);

        $payIn = PayIn::where('pay_id', $header->id)
            ->where('income_type_id', $this->incomeType->id)
            ->first();

        $this->assertNotNull($payIn, 'Recurring income added in financial entry should be included in payroll calculation.');
        $this->assertEquals(1200.00, (float) $payIn->rate);
        $this->assertEquals(600.00, (float) $payIn->amount);

        $payOut = PayOut::where('pay_id', $header->id)
            ->where('deduction_type_id', $this->deductionType->id)
            ->first();

        $this->assertNotNull($payOut, 'Recurring deduction added in financial entry should be included in payroll calculation.');
        $this->assertEquals(400.00, (float) $payOut->rate);
    }
}
