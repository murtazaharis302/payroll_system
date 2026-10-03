<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionMatrixCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected User $hrUser;
    protected EmployeeMaster $employee;
    protected PayrollPeriod $period;
    protected PayHeader $draft;
    protected EmpLoan $activeLoan;
    protected IncomeType $allowanceType;
    protected DeductionType $loanDeductionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MenusTableSeeder::class);

        $superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id'  => Role::SUPER_ADMIN,
            'role'     => 'admin',
        ]);

        $this->company = Company::create([
            'name'         => 'Test Company',
            'email'        => 'corp@test.com',
            'status'       => '1',
            'user_id'      => $superAdmin->id,
            'company_code' => 'TESTCORP',
        ]);

        $this->companyAdmin = User::create([
            'name'       => 'Company Admin',
            'email'      => 'admin@testcorp.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::COMPANY_ADMIN,
            'role'       => 'company',
            'company_id' => $this->company->id,
        ]);

        $this->hrUser = User::create([
            'name'       => 'HR Staff',
            'email'      => 'hr@testcorp.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::HR,
            'role'       => 'hr',
            'company_id' => $this->company->id,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'first_name'    => 'Juan',
            'last_name'     => 'Dela Cruz',
            'email'         => 'juan@testcorp.com',
            'employee_code' => 'EMP-001',
            'monthly_rate'  => 30000,
            'daily_rate'    => 1153.85,
            'hourly_rate'   => 144.23,
            'status'        => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id'   => $this->company->id,
            'period_name'  => 'March 1-15, 2026',
            'period_start' => '2026-03-01',
            'period_end'   => '2026-03-15',
            'payout_date'  => '2026-03-20',
            'status'       => 'open',
        ]);

        $this->draft = PayHeader::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'status'            => 'draft',
            'gross_pay'         => 15000,
            'net_pay'           => 14000,
            'days_worked'       => 11,
            'basic_pay'         => 15000,
        ]);

        $this->allowanceType = IncomeType::create([
            'company_id' => $this->company->id,
            'code'       => 'MEAL_ALLW',
            'name'       => 'Meal Allowance',
            'is_active'  => true,
        ]);

        $this->loanDeductionType = DeductionType::create([
            'company_id'      => $this->company->id,
            'code'            => 'VALE_DED',
            'name'            => 'Vale Deduction',
            'is_active'       => true,
            'is_loan_enabled' => true,
        ]);

        $loanType = \App\Models\LoanType::create([
            'company_id'        => $this->company->id,
            'name'              => 'Company Vale',
            'code'              => 'VALE',
            'deduction_type_id' => $this->loanDeductionType->id,
            'is_active'         => true,
        ]);

        $this->activeLoan = EmpLoan::create([
            'company_id'          => $this->company->id,
            'employee_id'         => $this->employee->id,
            'loan_type_id'        => $loanType->id,
            'principal_amount'    => 5000,
            'current_balance'     => 5000,
            'monthly_amortization'=> 500,
            'status'              => 'ACTIVE',
            'start_date'          => '2026-03-01',
        ]);
    }

    private function setModuleAccess(string $module, bool $enabled, int $roleId = Role::HR): void
    {
        $menu = Menu::where('module', $module)->firstOrFail();
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => $roleId, 'menu_id' => $menu->id],
            ['is_enabled' => $enabled ? 1 : 0]
        );
    }

    public function test_sub_capabilities_exist_and_linked_to_parents(): void
    {
        $draftsMenu = Menu::where('module', 'payroll_drafts')->firstOrFail();
        $loansMenu = Menu::where('module', 'vale_loans')->firstOrFail();

        $incomeCap = Menu::where('module', 'payroll_income_inputs')->firstOrFail();
        $deductionCap = Menu::where('module', 'payroll_deduction_inputs')->firstOrFail();
        $rateCap = Menu::where('module', 'payroll_income_rate_visibility')->firstOrFail();
        $columnCap = Menu::where('module', 'payroll_income_column_visibility')->firstOrFail();
        $paymentCap = Menu::where('module', 'loan_record_payment')->firstOrFail();
        $loanEditCap = Menu::where('module', 'loan_edit')->firstOrFail();
        $paymentEditCap = Menu::where('module', 'loan_payment_edit')->firstOrFail();

        $this->assertEquals($draftsMenu->id, $incomeCap->parent_id);
        $this->assertEquals($draftsMenu->id, $deductionCap->parent_id);
        $this->assertEquals($draftsMenu->id, $rateCap->parent_id);
        $this->assertEquals($draftsMenu->id, $columnCap->parent_id);
        $this->assertEquals($loansMenu->id, $paymentCap->parent_id);
        $this->assertEquals($loansMenu->id, $loanEditCap->parent_id);
        $this->assertEquals($loansMenu->id, $paymentEditCap->parent_id);

        $this->assertNull($incomeCap->route);
        $this->assertNull($deductionCap->route);
        $this->assertNull($rateCap->route);
        $this->assertNull($columnCap->route);
        $this->assertNull($paymentCap->route);
        $this->assertNull($loanEditCap->route);
        $this->assertNull($paymentEditCap->route);
    }

    public function test_loan_record_payment_permission_on_and_off(): void
    {
        // 1. Enable parent vale_loans and sub-capability loan_record_payment
        $this->setModuleAccess('vale_loans', true);
        $this->setModuleAccess('loan_record_payment', true);

        $response = $this->actingAs($this->hrUser)->get(route('admin.loan-monitor.index'));
        $response->assertOk();
        $response->assertSee('Record Payment');

        $payResponse = $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.record-payment'), [
            'emp_loan_id'  => $this->activeLoan->id,
            'amount_paid'  => 1000,
            'payment_date' => '2026-03-05',
        ]);
        $payResponse->assertRedirect();
        $this->assertEquals(4000, (float) $this->activeLoan->fresh()->current_balance);

        // 2. Disable sub-capability loan_record_payment
        $this->setModuleAccess('loan_record_payment', false);

        $responseOff = $this->actingAs($this->hrUser)->get(route('admin.loan-monitor.index'));
        $responseOff->assertOk();
        $responseOff->assertDontSee('Record Payment');

        $payResponseOff = $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.record-payment'), [
            'emp_loan_id'  => $this->activeLoan->id,
            'amount_paid'  => 500,
            'payment_date' => '2026-03-06',
        ]);
        $payResponseOff->assertStatus(403);
    }

    public function test_payroll_income_inputs_permission_on_and_off(): void
    {
        $this->setModuleAccess('payroll_drafts', true);

        // 1. Enable payroll_income_inputs
        $this->setModuleAccess('payroll_income_inputs', true);

        $viewResponse = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', [
            'id' => $this->draft->id,
            'payroll_period_id' => $this->period->id,
        ]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Add Income');

        $postResponse = $this->actingAs($this->hrUser)->post(route('payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $this->allowanceType->id,
            'number_input'   => 5,
        ]);
        $postResponse->assertRedirect();
        $this->assertDatabaseHas('pay_ins', [
            'pay_id'         => $this->draft->id,
            'income_type_id' => $this->allowanceType->id,
        ]);

        // 2. Disable payroll_income_inputs
        $this->setModuleAccess('payroll_income_inputs', false);

        $viewResponseOff = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', [
            'id' => $this->draft->id,
            'payroll_period_id' => $this->period->id,
        ]));
        $viewResponseOff->assertOk();
        $viewResponseOff->assertDontSee('Add Income');

        $anotherIncome = IncomeType::create([
            'company_id' => $this->company->id,
            'code'       => 'COMM',
            'name'       => 'Commission',
            'is_active'  => true,
        ]);

        $postResponseOff = $this->actingAs($this->hrUser)->post(route('payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $anotherIncome->id,
            'number_input'   => 1,
        ]);
        $postResponseOff->assertStatus(403);
    }

    public function test_payroll_deduction_inputs_permission_on_and_off(): void
    {
        $this->setModuleAccess('payroll_drafts', true);

        $standardDeduction = DeductionType::create([
            'company_id'      => $this->company->id,
            'code'            => 'UNIFORM',
            'name'            => 'Uniform Fee',
            'is_active'       => true,
            'is_loan_enabled' => false,
        ]);

        // 1. Enable payroll_deduction_inputs
        $this->setModuleAccess('payroll_deduction_inputs', true);

        $viewResponse = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', [
            'id' => $this->draft->id,
            'payroll_period_id' => $this->period->id,
        ]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Add Deduction');

        $postResponse = $this->actingAs($this->hrUser)->post(route('payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $standardDeduction->id,
            'number_input'      => 1,
            'rate'              => 200,
        ]);
        $postResponse->assertRedirect();
        $this->assertDatabaseHas('pay_outs', [
            'pay_id'            => $this->draft->id,
            'deduction_type_id' => $standardDeduction->id,
        ]);

        // 2. Disable payroll_deduction_inputs
        $this->setModuleAccess('payroll_deduction_inputs', false);

        $viewResponseOff = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', [
            'id' => $this->draft->id,
            'payroll_period_id' => $this->period->id,
        ]));
        $viewResponseOff->assertOk();
        $viewResponseOff->assertDontSee('Add Deduction');

        $postResponseOff = $this->actingAs($this->hrUser)->post(route('payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $this->loanDeductionType->id,
            'loan_id'           => $this->activeLoan->id,
            'number_input'      => 1,
            'rate'              => 500,
        ]);
        $postResponseOff->assertStatus(403);
    }

    public function test_payroll_income_rate_visibility_permission_on_and_off(): void
    {
        $this->setModuleAccess('payroll_drafts', true);
        $this->setModuleAccess('payroll_income_inputs', true);

        // 1. Rate visibility ON
        $this->setModuleAccess('payroll_income_rate_visibility', true);
        $responseOn = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', [
            'id' => $this->draft->id,
            'payroll_period_id' => $this->period->id,
        ]));
        $responseOn->assertOk();
        $responseOn->assertSee('Rate (Fixed)');
        $responseOn->assertSee('Total (Calculated)');

        // 2. Rate visibility OFF
        $this->setModuleAccess('payroll_income_rate_visibility', false);
        $responseOff = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', [
            'id' => $this->draft->id,
            'payroll_period_id' => $this->period->id,
        ]));
        $responseOff->assertOk();
        $responseOff->assertDontSee('Rate (Fixed)');
        $responseOff->assertDontSee('Total (Calculated)');
    }

    public function test_hr_can_access_devices_reports_and_periods_when_enabled(): void
    {
        $this->setModuleAccess('attendance', true);
        $this->actingAs($this->hrUser)->get(route('admin.attendance.devices.index'))->assertOk();

        $this->setModuleAccess('reports', true);
        $this->actingAs($this->hrUser)->get(route('reports.index'))->assertOk();

        $this->setModuleAccess('payroll_periods', true);
        $this->actingAs($this->hrUser)->get(route('payroll-periods.index'))->assertOk();
    }
}
