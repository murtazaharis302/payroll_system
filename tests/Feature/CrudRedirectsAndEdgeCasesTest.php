<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use App\Models\SubDepartment;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrudRedirectsAndEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company1;
    protected Company $company2;
    protected User $superAdmin;
    protected User $companyAdmin;
    protected User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);
        $this->seed(MenusTableSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super_crud@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'comp_crud@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company1 = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Alpha Corp',
            'company_code' => 'ALPHA',
            'email' => 'alpha@corp.com',
            'status' => '1',
        ]);
        $this->companyAdmin->update(['company_id' => $this->company1->id]);

        $this->company2 = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Beta Corp',
            'company_code' => 'BETA',
            'email' => 'beta@corp.com',
            'status' => '1',
        ]);

        $this->hrUser = User::create([
            'name' => 'HR User Alpha',
            'email' => 'hr_crud@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company1->id,
        ]);
    }

    public function test_income_type_crud_redirects_work_for_both_panels(): void
    {
        // 1. Super Admin Create Income Type
        $this->actingAs($this->superAdmin);
        $res = $this->post(route('income-types.store'), [
            'name' => 'Admin Special Income',
            'code' => 'ADM_SPEC',
            'description' => 'Created by admin',
            'is_taxable' => 1,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('income-types.index'));
        $res->assertSessionHas('statusSuccess');

        $incomeType = IncomeType::where('code', 'ADM_SPEC')->firstOrFail();

        // Super Admin Update
        $res = $this->put(route('income-types.update', $incomeType->id), [
            'name' => 'Admin Special Income Updated',
            'code' => 'ADM_SPEC',
            'is_taxable' => 1,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('income-types.index'));

        // Super Admin Delete
        $res = $this->delete(route('income-types.destroy', $incomeType->id));
        $res->assertRedirect(route('income-types.index'));

        // 2. Company Admin Create Income Type
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);
        $res = $this->post(route('company.income-types.store'), [
            'name' => 'Company Income Type',
            'code' => 'COMP_INC',
            'description' => 'Created by company',
            'is_taxable' => 0,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('company.income-types.index'));
        $res->assertSessionHas('statusSuccess');

        $compInc = IncomeType::where('code', 'COMP_INC')->firstOrFail();

        // Company Admin Update
        $res = $this->put(route('company.income-types.update', $compInc->id), [
            'name' => 'Company Income Type Updated',
            'code' => 'COMP_INC',
            'is_taxable' => 0,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('company.income-types.index'));

        // Company Admin Delete
        $res = $this->delete(route('company.income-types.destroy', $compInc->id));
        $res->assertRedirect(route('company.income-types.index'));
    }

    public function test_deduction_type_crud_redirects_work_for_both_panels(): void
    {
        // 1. Super Admin Create Deduction Type
        $this->actingAs($this->superAdmin);
        $res = $this->post(route('deduction-types.store'), [
            'name' => 'Admin Special Deduction',
            'code' => 'ADM_DED',
            'type' => 'other',
            'is_taxable' => 0,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('deduction-types.index'));
        $res->assertSessionHas('statusSuccess');

        $dedType = DeductionType::where('code', 'ADM_DED')->firstOrFail();

        // Super Admin Update
        $res = $this->put(route('deduction-types.update', $dedType->id), [
            'name' => 'Admin Special Deduction Updated',
            'code' => 'ADM_DED',
            'type' => 'other',
            'is_taxable' => 0,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('deduction-types.index'));

        // Super Admin Delete
        $res = $this->delete(route('deduction-types.destroy', $dedType->id));
        $res->assertRedirect(route('deduction-types.index'));

        // 2. Company Admin Create Deduction Type
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);
        $res = $this->post(route('company.deduction-types.store'), [
            'name' => 'Company Deduction',
            'code' => 'COMP_DED',
            'type' => 'other',
            'is_taxable' => 0,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('company.deduction-types.index'));

        $compDed = DeductionType::where('code', 'COMP_DED')->firstOrFail();

        // Company Admin Update
        $res = $this->put(route('company.deduction-types.update', $compDed->id), [
            'name' => 'Company Deduction Updated',
            'code' => 'COMP_DED',
            'type' => 'other',
            'is_taxable' => 0,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('company.deduction-types.index'));

        // Company Admin Delete
        $res = $this->delete(route('company.deduction-types.destroy', $compDed->id));
        $res->assertRedirect(route('company.deduction-types.index'));
    }

    public function test_loan_types_crud_and_update_endpoint_for_super_admin_and_company_admin(): void
    {
        $deductionType = DeductionType::firstOrCreate([
            'code' => 'LOAN_GEN',
            'name' => 'General Loan Deduction',
            'type' => 'loan',
            'is_active' => true,
        ]);

        // 1. Super Admin access and CRUD
        $this->actingAs($this->superAdmin);
        $res = $this->get(route('loan-types.index'));
        $res->assertStatus(200);

        $res = $this->post(route('loan-types.store'), [
            'name' => 'Admin Loan Category',
            'deduction_type_id' => $deductionType->id,
            'default_amortization_per_cutoff' => 500.00,
            'default_installments_count' => 6,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('loan-types.index'));

        $loanType = LoanType::where('name', 'Admin Loan Category')->firstOrFail();

        // PUT update endpoint resolves without 404
        $res = $this->put(route('loan-types.update', $loanType->id), [
            'name' => 'Admin Loan Category Updated',
            'deduction_type_id' => $deductionType->id,
            'default_amortization_per_cutoff' => 600.00,
            'default_installments_count' => 8,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('loan-types.index'));
        $loanType->refresh();
        $this->assertEquals('Admin Loan Category Updated', $loanType->name);

        // 2. Company Admin access and CRUD
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);
        $res = $this->get(route('company.loan-types.index'));
        $res->assertStatus(200);

        $res = $this->post(route('company.loan-types.store'), [
            'name' => 'Company Loan Category',
            'deduction_type_id' => $deductionType->id,
            'default_amortization_per_cutoff' => 350.00,
            'default_installments_count' => 4,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('company.loan-types.index'));

        $compLoanType = LoanType::where('name', 'Company Loan Category')->firstOrFail();

        // PUT update endpoint for company resolves
        $res = $this->put(route('company.loan-types.update', $compLoanType->id), [
            'name' => 'Company Loan Category Updated',
            'deduction_type_id' => $deductionType->id,
            'default_amortization_per_cutoff' => 400.00,
            'default_installments_count' => 5,
            'is_active' => 1,
        ]);
        $res->assertRedirect(route('company.loan-types.index'));
        $compLoanType->refresh();
        $this->assertEquals('Company Loan Category Updated', $compLoanType->name);
    }

    public function test_graceful_duplicate_department_and_sub_department_validation(): void
    {
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);

        // First department creates successfully
        $res = $this->post(panelRoute('departments.store'), [
            'company_id' => $this->company1->id,
            'code' => 'DEP_DUP',
            'name' => 'Department Duplicate Test',
        ]);
        $res->assertSessionHas('status', 'Department added successfully.');

        $dept = Department::where('company_id', $this->company1->id)->where('code', 'DEP_DUP')->firstOrFail();

        // Duplicate code in same company returns validation error without SQLSTATE exception
        $res = $this->post(panelRoute('departments.store'), [
            'company_id' => $this->company1->id,
            'code' => 'DEP_DUP',
            'name' => 'Another Department with Same Code',
        ]);
        $res->assertSessionHasErrors(['code']);

        // Same code in another company is allowed
        $this->actingAs($this->superAdmin);
        $res = $this->post(panelRoute('departments.store'), [
            'company_id' => $this->company2->id,
            'code' => 'DEP_DUP',
            'name' => 'Beta Department with Same Code',
        ]);
        $res->assertSessionHas('status', 'Department added successfully.');

        // SubDepartment duplicate code validation within same department
        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);
        $res = $this->post(panelRoute('sub-departments.store'), [
            'company_id' => $this->company1->id,
            'department_id' => $dept->id,
            'code' => 'SUB_DUP',
            'name' => 'Sub Department 1',
        ]);
        $res->assertSessionHas('status', 'Sub-department added successfully.');

        // Duplicate sub-department code in same parent department
        $res = $this->post(panelRoute('sub-departments.store'), [
            'company_id' => $this->company1->id,
            'department_id' => $dept->id,
            'code' => 'SUB_DUP',
            'name' => 'Sub Department 2',
        ]);
        $res->assertSessionHasErrors(['code']);
    }

    public function test_cash_loan_success_flow_and_print_link(): void
    {
        $dept = Department::create([
            'company_id' => $this->company1->id,
            'code' => 'LN_DEPT',
            'name' => 'Loan Dept',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-CASH-PRINT',
            'first_name' => 'Cash',
            'last_name' => 'Borrower',
            'status' => 'active',
        ]);

        $deductionType = DeductionType::firstOrCreate(
            ['code' => 'CASH_LOAN_DED', 'company_id' => $this->company1->id],
            ['name' => 'Cash Loan Deduction', 'type' => 'loan', 'is_loan_enabled' => true, 'is_active' => true]
        );
        $deductionType->update(['is_loan_enabled' => true]);

        $loanType = LoanType::create([
            'company_id' => $this->company1->id,
            'name' => 'Emergency Cash Loan',
            'deduction_type_id' => $deductionType->id,
            'default_amortization_per_cutoff' => 500,
            'default_installments_count' => 6,
            'is_active' => true,
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company1->id]);

        $res = $this->post(panelRoute('loan-monitor.store-loan'), [
            'employee_id' => $employee->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 5000,
            'monthly_interest_rate' => 2,
            'terms_months' => 3,
            'start_date' => '2026-08-01',
            'purpose' => 'Medical Assistance',
        ]);

        $loan = EmpLoan::where('employee_id', $employee->id)->firstOrFail();
        $expectedPrintUrl = panelRoute('loan-monitor.print-form', $loan->id);

        $res->assertSessionHas('statusSuccess', 'New cash loan added successfully.');
        $res->assertSessionHas('loanPrintUrl', $expectedPrintUrl);

        // Verify the print endpoint returns 200 and the loan form
        $printRes = $this->get($expectedPrintUrl);
        $printRes->assertStatus(200);
        $printRes->assertSee('Cash Loan Application & Summary', false);
        $printRes->assertSee('EMP-CASH-PRINT');
    }

    public function test_hr_users_list_null_relation_safe_rendering(): void
    {
        // Give HR permission to users/employees module
        $empMenu = Menu::where('module', 'employees')->firstOrFail();
        MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company1->id, 'role_id' => Role::HR, 'menu_id' => $empMenu->id],
            ['is_enabled' => true]
        );

        // Create a standalone user without linked legacy Employee or EmployeeMaster record
        $targetUser = User::create([
            'name' => 'Standalone Employee User',
            'email' => 'standalone_user@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'role_id' => 4,
            'company_id' => $this->company1->id,
        ]);

        $this->actingAs($this->hrUser)->withSession(['active_company_id' => $this->company1->id]);

        $res = $this->get(panelRoute('employee'));
        $res->assertStatus(200);
        $res->assertSee('Standalone Employee User');
        $res->assertSee('standalone_user@test.com');
    }
}
