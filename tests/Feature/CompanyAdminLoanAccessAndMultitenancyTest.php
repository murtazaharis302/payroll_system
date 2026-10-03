<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\EmpLoan;
use App\Models\LoanType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAdminLoanAccessAndMultitenancyTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $companyAdminA;
    protected User $superAdmin;
    protected EmployeeMaster $employeeA;
    protected EmployeeMaster $employeeB;
    protected LoanType $loanTypeA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyAdminA = User::create([
            'name'     => 'Company Admin A',
            'email'    => 'admin.a@alpha.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $ownerB = User::create([
            'name'     => 'Company Admin B',
            'email'    => 'admin.b@beta.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $this->companyA = Company::create([
            'name'         => 'Alpha Company',
            'email'        => 'alpha@company.test',
            'user_id'      => $this->companyAdminA->id,
            'company_code' => 'ALPHA01',
        ]);

        $this->companyB = Company::create([
            'name'         => 'Beta Company',
            'email'        => 'beta@company.test',
            'user_id'      => $ownerB->id,
            'company_code' => 'BETA01',
        ]);

        $this->companyAdminA->update(['company_id' => $this->companyA->id]);
        $ownerB->update(['company_id' => $this->companyB->id]);

        $this->superAdmin = User::create([
            'name'       => 'Super Admin',
            'email'      => 'super@admin.test',
            'password'   => bcrypt('password'),
            'role_id'    => Role::SUPER_ADMIN,
            'role'       => 'admin',
            'company_id' => null,
            'status'     => 1,
        ]);

        $this->employeeA = EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'employee_code' => 'EMP-A101',
            'first_name'    => 'John',
            'middle_name'   => '',
            'last_name'     => 'Alpha',
            'status'        => 'active',
        ]);

        $this->employeeB = EmployeeMaster::create([
            'company_id'    => $this->companyB->id,
            'employee_code' => 'EMP-B202',
            'first_name'    => 'Bob',
            'middle_name'   => '',
            'last_name'     => 'Beta',
            'status'        => 'active',
        ]);

        $deductionA = \App\Models\DeductionType::withoutGlobalScopes()->where('code', 'VALE')->first();
        if (!$deductionA) {
            $deductionA = \App\Models\DeductionType::create([
                'company_id' => $this->companyA->id,
                'name' => 'Cash Advance',
                'code' => 'VALE',
                'type' => 'loan',
                'is_active' => true,
                'is_loan_enabled' => true,
            ]);
        } else {
            $deductionA->update(['is_loan_enabled' => true]);
        }

        $this->loanTypeA = LoanType::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'name'       => 'Cash Advance',
            'deduction_type_id' => $deductionA->id,
        ]);
    }

    public function test_company_admin_can_access_loan_monitor_and_see_only_own_company_employees(): void
    {
        $response = $this->actingAs($this->companyAdminA)->get(route('company.loan-monitor.index'));

        $response->assertStatus(200);
        $response->assertSee('Add New Loan');
        $response->assertSee('John Alpha');
        $response->assertDontSee('Bob Beta');
    }

    public function test_company_admin_can_successfully_create_loan_for_own_employee(): void
    {
        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.store-loan'), [
            'employee_id'           => $this->employeeA->id,
            'loan_type_id'          => $this->loanTypeA->id,
            'principal_amount'      => 5000.00,
            'start_date'            => '2026-08-01',
            'purpose'               => 'Multitenancy Test Loan',
            'terms_months'          => 5,
            'monthly_interest_rate' => 0.00,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('statusSuccess');

        $this->assertDatabaseHas('emp_loans', [
            'employee_id'      => $this->employeeA->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 5000.00,
            'current_balance'  => 5000.00,
        ]);
    }

    public function test_company_admin_cannot_create_loan_for_another_company_employee(): void
    {
        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.store-loan'), [
            'employee_id'           => $this->employeeB->id,
            'loan_type_id'          => $this->loanTypeA->id,
            'principal_amount'      => 3000.00,
            'start_date'            => '2026-08-01',
            'purpose'               => 'Cross Company Test',
            'terms_months'          => 5,
            'monthly_interest_rate' => 0.00,
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_company_admin_can_record_payment_for_own_company_loan(): void
    {
        $loan = EmpLoan::create([
            'employee_id'      => $this->employeeA->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 1000.00,
            'current_balance'  => 1000.00,
        ]);

        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.record-payment'), [
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 250.00,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('statusSuccess');

        $this->assertDatabaseHas('emp_loans', [
            'id'              => $loan->id,
            'current_balance' => 750.00,
        ]);

        $this->assertDatabaseHas('loan_payments', [
            'emp_loan_id' => $loan->id,
            'amount_paid' => 250.00,
        ]);
    }

    public function test_company_admin_cannot_record_payment_for_another_company_loan(): void
    {
        $loanB = EmpLoan::create([
            'employee_id'      => $this->employeeB->id,
            'loan_type_id'     => $this->loanTypeA->id,
            'principal_amount' => 1000.00,
            'current_balance'  => 1000.00,
        ]);

        $response = $this->actingAs($this->companyAdminA)->post(route('company.loan-monitor.record-payment'), [
            'emp_loan_id'  => $loanB->id,
            'amount_paid'  => 250.00,
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_super_admin_has_full_access_to_create_loans_globally(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.loan-monitor.store-loan'), [
            'employee_id'           => $this->employeeB->id,
            'loan_type_id'          => $this->loanTypeA->id,
            'principal_amount'      => 8000.00,
            'start_date'            => '2026-08-01',
            'purpose'               => 'Super Admin Test',
            'terms_months'          => 5,
            'monthly_interest_rate' => 0.00,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('statusSuccess');

        $this->assertDatabaseHas('emp_loans', [
            'employee_id'      => $this->employeeB->id,
            'principal_amount' => 8000.00,
        ]);
    }

    public function test_ajax_get_employee_loans_succeeds_when_employee_has_loans(): void
    {
        EmpLoan::create([
            'employee_id'           => $this->employeeA->id,
            'loan_type_id'          => $this->loanTypeA->id,
            'principal_amount'      => 5000.00,
            'current_balance'       => 2500.00,
            'total_amount'          => 5000.00,
            'per_cutoff_amount'     => 500.00,
            'start_date'            => '2026-08-01',
            'purpose'               => 'Medical Expense',
            'terms_months'          => 5,
            'monthly_interest_rate' => 0.00,
            'interest_amount'       => 0.00,
        ]);

        $response = $this->actingAs($this->companyAdminA)
            ->get(route('company.loan-monitor.employee-loans', $this->employeeA->id));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'summary' => [
                'total'  => 1,
                'active' => 1,
            ],
        ]);
        $data = $response->json();
        $this->assertCount(1, $data['loans']);
        $this->assertEquals('Aug 01, 2026', $data['loans'][0]['start_date']);
    }

    public function test_ajax_get_employee_loans_blocks_cross_company(): void
    {
        $response = $this->actingAs($this->companyAdminA)
            ->get(route('company.loan-monitor.employee-loans', $this->employeeB->id));

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }
}
