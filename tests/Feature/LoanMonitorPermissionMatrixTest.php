<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\LoanPayment;
use App\Models\LoanType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MenusTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanMonitorPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected User $hrUser;
    protected EmployeeMaster $employee;
    protected LoanType $loanType;
    protected EmpLoan $activeLoan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MenusTableSeeder::class);

        $superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id'  => Role::SUPER_ADMIN,
            'role'     => 'admin',
        ]);

        $this->company = Company::create([
            'name'         => 'Test Company Matrix',
            'email'        => 'corp_matrix@test.com',
            'status'       => '1',
            'user_id'      => $superAdmin->id,
            'company_code' => 'TESTMATRIX',
        ]);

        $this->companyAdmin = User::create([
            'name'       => 'Company Admin',
            'email'      => 'admin@testmatrix.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::COMPANY_ADMIN,
            'role'       => 'company',
            'company_id' => $this->company->id,
        ]);

        $this->hrUser = User::create([
            'name'       => 'HR Staff',
            'email'      => 'hr@testmatrix.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::HR,
            'role'       => 'hr',
            'company_id' => $this->company->id,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'first_name'    => 'Juan',
            'last_name'     => 'Dela Cruz',
            'employee_code' => 'EMP-001',
            'monthly_rate'  => 30000,
            'status'        => 'active',
        ]);

        $deductionType = DeductionType::create([
            'company_id'      => $this->company->id,
            'code'            => 'VALE_DED',
            'name'            => 'Vale Deduction',
            'is_active'       => true,
            'is_loan_enabled' => true,
        ]);

        $this->loanType = LoanType::create([
            'company_id'        => $this->company->id,
            'name'              => 'Company Vale',
            'code'              => 'VALE',
            'deduction_type_id' => $deductionType->id,
            'is_active'         => true,
        ]);

        $this->activeLoan = EmpLoan::create([
            'company_id'            => $this->company->id,
            'employee_id'           => $this->employee->id,
            'loan_type_id'          => $this->loanType->id,
            'principal_amount'      => 10000,
            'total_amount'          => 10000,
            'current_balance'       => 10000,
            'amount_paid'           => 0,
            'monthly_interest_rate' => 0,
            'terms_months'          => 5,
            'per_cutoff_amount'     => 1000,
            'status'                => 'ACTIVE',
            'start_date'            => '2026-03-01',
            'purpose'               => 'Emergency Medical',
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

    /**
     * Test 1: loan_edit and loan_payment_edit exist as sub-capabilities under vale_loans.
     */
    public function test_loan_edit_and_payment_edit_capabilities_exist_under_vale_loans(): void
    {
        $valeLoansMenu = Menu::where('module', 'vale_loans')->firstOrFail();
        $loanEdit = Menu::where('module', 'loan_edit')->firstOrFail();
        $paymentEdit = Menu::where('module', 'loan_payment_edit')->firstOrFail();

        $this->assertEquals($valeLoansMenu->id, $loanEdit->parent_id);
        $this->assertEquals($valeLoansMenu->id, $paymentEdit->parent_id);
        $this->assertNull($loanEdit->route);
        $this->assertNull($paymentEdit->route);

        // Verify Permission Matrix UI renders both sub-capabilities
        $matrixResponse = $this->actingAs($this->companyAdmin)->get(route('company.permissions.matrix'));
        $matrixResponse->assertOk();
        $matrixResponse->assertSee('Loan Monitor — Edit Loan');
        $matrixResponse->assertSee('Loan Monitor — Edit Manual Payment');
        $matrixResponse->assertSee('Sub-Capability');
    }

    /**
     * Test 2: loan_edit permission allows HR to edit loan amount when enabled, and blocks (403) when disabled.
     */
    public function test_loan_edit_permission_on_and_off(): void
    {
        $this->setModuleAccess('vale_loans', true);

        // 1. Enable loan_edit
        $this->setModuleAccess('loan_edit', true);

        $viewResponseOn = $this->actingAs($this->hrUser)->get(route('admin.loan-monitor.index'));
        $viewResponseOn->assertOk();
        $viewResponseOn->assertSee('const CAN_EDIT_LOAN = true', false);

        $updateResponse = $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.update-loan', $this->activeLoan->id), [
            'principal_amount' => 15000,
        ]);
        $updateResponse->assertRedirect();
        $this->assertEquals(15000, (float) $this->activeLoan->fresh()->principal_amount);
        $this->assertEquals(15000, (float) $this->activeLoan->fresh()->current_balance);

        // 2. Disable loan_edit
        $this->setModuleAccess('loan_edit', false);

        $viewResponseOff = $this->actingAs($this->hrUser)->get(route('admin.loan-monitor.index'));
        $viewResponseOff->assertOk();
        $viewResponseOff->assertSee('const CAN_EDIT_LOAN = false', false);

        $updateResponseOff = $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.update-loan', $this->activeLoan->id), [
            'principal_amount' => 20000,
        ]);
        $updateResponseOff->assertStatus(403);

        // Assert database values remain unchanged
        $freshLoan = $this->activeLoan->fresh();
        $this->assertEquals(15000, (float) $freshLoan->principal_amount);
        $this->assertEquals(15000, (float) $freshLoan->current_balance);
    }

    /**
     * Test 3: loan_payment_edit permission allows HR to edit manual payments when enabled, and blocks (403) when disabled.
     */
    public function test_loan_payment_edit_permission_on_and_off(): void
    {
        $payment = LoanPayment::create([
            'emp_loan_id'  => $this->activeLoan->id,
            'amount_paid'  => 2000,
            'payment_date' => '2026-03-05',
            'source'       => 'MANUAL',
        ]);
        $this->activeLoan->recalculateBalance();
        $this->assertEquals(8000, (float) $this->activeLoan->fresh()->current_balance);

        $this->setModuleAccess('vale_loans', true);

        // 1. Enable loan_payment_edit
        $this->setModuleAccess('loan_payment_edit', true);

        $viewResponseOn = $this->actingAs($this->hrUser)->get(route('admin.loan-monitor.index'));
        $viewResponseOn->assertOk();
        $viewResponseOn->assertSee('const CAN_EDIT_PAYMENT = true', false);

        $updateResponse = $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.update-payment', $payment->id), [
            'amount_paid'  => 3000,
            'payment_date' => '2026-03-08',
        ]);
        $updateResponse->assertRedirect();

        $this->assertEquals(3000, (float) $payment->fresh()->amount_paid);
        $this->assertEquals('2026-03-08', $payment->fresh()->payment_date->format('Y-m-d'));
        $this->assertEquals(7000, (float) $this->activeLoan->fresh()->current_balance);

        // 2. Disable loan_payment_edit
        $this->setModuleAccess('loan_payment_edit', false);

        $viewResponseOff = $this->actingAs($this->hrUser)->get(route('admin.loan-monitor.index'));
        $viewResponseOff->assertOk();
        $viewResponseOff->assertSee('const CAN_EDIT_PAYMENT = false', false);

        $updateResponseOff = $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.update-payment', $payment->id), [
            'amount_paid'  => 5000,
            'payment_date' => '2026-03-10',
        ]);
        $updateResponseOff->assertStatus(403);

        // Assert database values remain unchanged
        $this->assertEquals(3000, (float) $payment->fresh()->amount_paid);
        $this->assertEquals('2026-03-08', $payment->fresh()->payment_date->format('Y-m-d'));
        $this->assertEquals(7000, (float) $this->activeLoan->fresh()->current_balance);
    }

    /**
     * Test 4: Payroll-generated loan payments remain locked (403) even when loan_payment_edit is enabled.
     */
    public function test_payroll_generated_payment_remains_locked_with_loan_payment_edit_enabled(): void
    {
        $payrollPayment = LoanPayment::create([
            'emp_loan_id'  => $this->activeLoan->id,
            'amount_paid'  => 1000,
            'payment_date' => '2026-03-15',
            'source'       => 'PAYROLL',
        ]);
        $this->activeLoan->recalculateBalance();

        $this->setModuleAccess('vale_loans', true);
        $this->setModuleAccess('loan_payment_edit', true);

        $response = $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.update-payment', $payrollPayment->id), [
            'amount_paid'  => 2500,
            'payment_date' => '2026-03-20',
        ]);

        $response->assertStatus(403);
        $this->assertEquals(1000, (float) $payrollPayment->fresh()->amount_paid);
    }

    /**
     * Test 5: Explicitly disabling loan_edit or loan_payment_edit for Company Admin in matrix blocks the action.
     */
    public function test_explicitly_disabled_capability_for_company_admin_blocks_action(): void
    {
        // 1. Without explicit override row, Company Admin inherits access
        $this->actingAs($this->companyAdmin)->post(route('admin.loan-monitor.update-loan', $this->activeLoan->id), [
            'principal_amount' => 12000,
        ])->assertRedirect();
        $this->assertEquals(12000, (float) $this->activeLoan->fresh()->principal_amount);

        // 2. Explicitly disable loan_edit for Company Admin
        $this->setModuleAccess('loan_edit', false, Role::COMPANY_ADMIN);

        $this->actingAs($this->companyAdmin)->post(route('admin.loan-monitor.update-loan', $this->activeLoan->id), [
            'principal_amount' => 18000,
        ])->assertStatus(403);
        $this->assertEquals(12000, (float) $this->activeLoan->fresh()->principal_amount);

        // 3. Explicitly disable loan_payment_edit for Company Admin
        $payment = LoanPayment::create([
            'emp_loan_id'  => $this->activeLoan->id,
            'amount_paid'  => 1000,
            'payment_date' => '2026-03-05',
            'source'       => 'MANUAL',
        ]);
        $this->setModuleAccess('loan_payment_edit', false, Role::COMPANY_ADMIN);

        $this->actingAs($this->companyAdmin)->post(route('admin.loan-monitor.update-payment', $payment->id), [
            'amount_paid'  => 2000,
            'payment_date' => '2026-03-06',
        ])->assertStatus(403);
        $this->assertEquals(1000, (float) $payment->fresh()->amount_paid);
    }

    /**
     * Test 6: loan_record_payment, loan_edit, and loan_payment_edit are completely independent permissions.
     */
    public function test_loan_record_payment_and_loan_payment_edit_are_independent(): void
    {
        $payment = LoanPayment::create([
            'emp_loan_id'  => $this->activeLoan->id,
            'amount_paid'  => 1000,
            'payment_date' => '2026-03-05',
            'source'       => 'MANUAL',
        ]);

        $this->setModuleAccess('vale_loans', true);

        // Scenario A: HR has loan_record_payment ONLY
        $this->setModuleAccess('loan_record_payment', true);
        $this->setModuleAccess('loan_payment_edit', false);
        $this->setModuleAccess('loan_edit', false);

        // Record payment succeeds
        $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.record-payment'), [
            'emp_loan_id'  => $this->activeLoan->id,
            'amount_paid'  => 500,
            'payment_date' => '2026-03-06',
        ])->assertRedirect();

        // Edit payment fails (403)
        $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.update-payment', $payment->id), [
            'amount_paid'  => 2000,
            'payment_date' => '2026-03-06',
        ])->assertStatus(403);

        // Edit loan fails (403)
        $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.update-loan', $this->activeLoan->id), [
            'principal_amount' => 20000,
        ])->assertStatus(403);

        // Scenario B: HR has loan_payment_edit ONLY
        $this->setModuleAccess('loan_record_payment', false);
        $this->setModuleAccess('loan_payment_edit', true);

        // Record payment fails (403)
        $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.record-payment'), [
            'emp_loan_id'  => $this->activeLoan->id,
            'amount_paid'  => 500,
            'payment_date' => '2026-03-07',
        ])->assertStatus(403);

        // Edit payment succeeds
        $this->actingAs($this->hrUser)->post(route('admin.loan-monitor.update-payment', $payment->id), [
            'amount_paid'  => 2000,
            'payment_date' => '2026-03-06',
        ])->assertRedirect();
        $this->assertEquals(2000, (float) $payment->fresh()->amount_paid);
    }
}
