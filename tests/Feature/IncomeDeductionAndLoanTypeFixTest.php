<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpDeduction;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeDeductionAndLoanTypeFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);
    }

    public function test_assign_income_persists_amount_and_displays_non_zero(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Income Test Co',
            'company_code' => 'ITC-001',
            'email' => 'itc@test.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-INC-01',
            'first_name' => 'John',
            'last_name' => 'Income',
            'email' => 'johninc@test.com',
            'monthly_rate' => 30000,
            'date_hired' => '2026-01-01',
            'status' => 'active',
        ]);

        $incomeType = IncomeType::create([
            'code' => 'ALLOWANCE_FOOD',
            'name' => 'Food Allowance',
            'description' => 'Food Allowance',
            'is_taxable' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('emp-incomes.store'), [
            'employee_id' => $employee->id,
            'income_type_id' => $incomeType->id,
            'amount' => 3500.50,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('emp-incomes.index'));

        $empIncome = EmpIncome::where('employee_id', $employee->id)->where('income_type_id', $incomeType->id)->first();
        $this->assertNotNull($empIncome);
        $this->assertEquals(3500.50, (float)$empIncome->amount);
        $this->assertEquals(3500.50, (float)$empIncome->rate);

        $indexResponse = $this->get(route('emp-incomes.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('3,500.50');
    }

    public function test_assign_deduction_persists_amount_and_displays_non_zero(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Deduction Test Co',
            'company_code' => 'DTC-001',
            'email' => 'dtc@test.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-DED-01',
            'first_name' => 'Jane',
            'last_name' => 'Deduction',
            'email' => 'janeded@test.com',
            'monthly_rate' => 25000,
            'date_hired' => '2026-01-01',
            'status' => 'active',
        ]);

        $deductionType = DeductionType::create([
            'code' => 'UNIFORM_FEE',
            'name' => 'Uniform Deduction',
            'description' => 'Uniform Deduction',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('emp-deductions.store'), [
            'employee_id' => $employee->id,
            'deduction_type_id' => $deductionType->id,
            'amount' => 1250.75,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('emp-deductions.index'));

        $empDeduction = EmpDeduction::where('employee_id', $employee->id)->first();
        $this->assertNotNull($empDeduction);
        $this->assertEquals(1250.75, (float)$empDeduction->amount);
        $this->assertEquals(1250.75, (float)$empDeduction->rate);

        $indexResponse = $this->get(route('emp-deductions.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('1,250.75');
    }

    public function test_loan_monitor_renders_loan_types(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Loan Monitor Co',
            'company_code' => 'LMC-001',
            'email' => 'lmc@test.com',
            'status' => 1,
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('admin.loan-monitor.index'));
        $response->assertStatus(200);
        $response->assertSee('Select Loan Type');
        $response->assertSee('Cash Advance / Vale');
    }
}
