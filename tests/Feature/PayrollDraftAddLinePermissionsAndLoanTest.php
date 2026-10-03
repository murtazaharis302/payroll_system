<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpDeduction;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollDraftAddLinePermissionsAndLoanTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $hr;
    protected Company $company;
    protected PayrollPeriod $period;
    protected EmployeeMaster $employee;
    protected PayHeader $draft;
    protected PayIn $basicPayIn;
    protected PayOut $existingPayOut;
    protected IncomeType $missingIncomeType;
    protected DeductionType $missingDeductionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([MenusTableSeeder::class, IncomeTypesSeeder::class, DeductionTypesSeeder::class, StatutoryRulesSeeder::class]);

        Role::firstOrCreate(['id' => Role::COMPANY_ADMIN, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => Role::HR, 'name' => 'HR']);

        $this->admin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin_add_perm@example.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Add Line Permission Corp',
            'company_code' => 'ALPC',
            'email' => 'alpc@example.com',
            'status' => '1',
        ]);
        $this->admin->update(['company_id' => $this->company->id]);

        $this->hr = User::create([
            'name' => 'HR Staff',
            'email' => 'hr_add_perm@example.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => Role::HR,
            'company_id' => $this->company->id,
        ]);

        $dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'HRD',
            'name' => 'Human Resources',
            'is_active' => true,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-ALPC-01',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'monthly_rate' => 30000,
            'status' => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 First Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->draft = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'status' => 'draft',
            'days_worked' => 11,
            'lock_version' => 1,
        ]);

        $basicType = IncomeType::where('code', 'BASIC')->firstOrFail();
        $this->basicPayIn = PayIn::create([
            'pay_id' => $this->draft->id,
            'income_type_id' => $basicType->id,
            'number_input' => 1,
            'rate' => 15000,
        ]);

        $sss = DeductionType::where('code', 'SSS')->firstOrFail();
        $this->existingPayOut = PayOut::create([
            'pay_id' => $this->draft->id,
            'deduction_type_id' => $sss->id,
            'number_input' => 1,
            'rate' => 100,
        ]);

        $this->missingIncomeType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'ONE_TIME',
            'name' => 'One Time Income',
            'is_active' => true,
        ]);
        $this->missingDeductionType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'ONE_TIME_DED',
            'name' => 'One Time Deduction',
            'type' => 'other',
            'is_active' => true,
        ]);

        $payrollDraftsMenu = Menu::where('module', 'payroll_drafts')->first();
        if ($payrollDraftsMenu) {
            MenuRoleAccess::firstOrCreate([
                'company_id' => $this->company->id,
                'role_id' => Role::HR,
                'menu_id' => $payrollDraftsMenu->id,
            ], [
                'is_enabled' => true,
            ]);
        }
    }

    public function test_monthly_deduction_assignment_is_prorated_only_without_an_explicit_override(): void
    {
        $explicitType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'MONTHLY_EXPLICIT',
            'name' => 'Monthly Explicit Deduction',
            'type' => 'other',
            'is_active' => true,
        ]);
        $derivedType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'MONTHLY_DERIVED',
            'name' => 'Monthly Derived Deduction',
            'type' => 'other',
            'is_active' => true,
        ]);
        foreach ([$explicitType, $derivedType] as $type) {
            EmpDeduction::create([
                'employee_id' => $this->employee->id,
                'deduction_type_id' => $type->id,
                'rate' => 1000,
                'frequency' => 'monthly',
                'is_active' => true,
            ]);
        }

        $admin = $this->actingAs($this->admin)->withSession(['active_company_id' => $this->company->id]);
        $admin->postJson(route('company.payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $explicitType->id,
            'number_input' => 1,
            'rate' => 1000,
        ])->assertCreated();
        $admin->postJson(route('company.payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $derivedType->id,
            'number_input' => 1,
        ])->assertCreated();

        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft->id,
            'deduction_type_id' => $explicitType->id,
            'rate' => 1000,
        ]);
        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft->id,
            'deduction_type_id' => $derivedType->id,
            'rate' => 500,
        ]);
    }

    public function test_admin_adds_missing_deduction_without_altering_existing_draft_data(): void
    {
        $original = $this->draft->fresh()->only(['days_worked', 'status']);
        $basic = $this->basicPayIn->only(['number_input', 'rate']);

        $response = $this->actingAs($this->admin)->withSession(['active_company_id' => $this->company->id])
            ->postJson(route('company.payroll-drafts.add-deduction', $this->draft->id), [
                'deduction_type_id' => $this->missingDeductionType->id,
                'number_input' => 1,
                'rate' => 80,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft->id,
            'deduction_type_id' => $this->missingDeductionType->id,
            'number_input' => 1,
            'rate' => 80,
        ]);
        $this->assertSame($basic, $this->basicPayIn->fresh()->only(['number_input', 'rate']));
        $this->assertSame($original, $this->draft->fresh()->only(['days_worked', 'status']));
        $this->assertDatabaseMissing('emp_deductions', ['employee_id' => $this->employee->id, 'deduction_type_id' => $this->missingDeductionType->id]);
    }

    public function test_hr_can_add_any_catalog_income_without_rate_exposure_or_submission(): void
    {
        $incomeMenu = Menu::where('module', 'payroll_income_inputs')->firstOrFail();
        MenuRoleAccess::create([
            'company_id' => $this->company->id,
            'role_id' => Role::HR,
            'menu_id' => $incomeMenu->id,
            'is_enabled' => true,
        ]);

        $view = $this->actingAs($this->hr)->withSession(['active_company_id' => $this->company->id])
            ->get(route('admin.payroll-drafts.edit', $this->draft->id));
        $view->assertOk()
            ->assertSee('Add Income')
            ->assertSee('One Time Income')
            ->assertDontSee('add_income_rate')
            ->assertDontSee('data-rate');

        $this->postJson(route('admin.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $this->missingIncomeType->id,
            'number_input' => 3,
        ])->assertCreated();
        $this->assertDatabaseHas('pay_ins', [
            'pay_id' => $this->draft->id,
            'income_type_id' => $this->missingIncomeType->id,
            'number_input' => 3,
            'rate' => 0,
        ]);

        $this->postJson(route('admin.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => IncomeType::where('code', 'OVERTIME')->firstOrFail()->id,
            'number_input' => 1,
            'rate' => 50,
        ])->assertForbidden();
    }

    public function test_hr_deduction_permission_and_rate_visibility_are_independent_from_income_addition(): void
    {
        $deductionMenu = Menu::where('module', 'payroll_deduction_inputs')->firstOrFail();
        $rateVisibilityMenu = Menu::where('module', 'payroll_income_rate_visibility')->firstOrFail();

        $this->actingAs($this->hr)->withSession(['active_company_id' => $this->company->id]);

        $this->postJson(route('admin.payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $this->missingDeductionType->id,
        ])->assertForbidden();

        MenuRoleAccess::create([
            'company_id' => $this->company->id,
            'role_id' => Role::HR,
            'menu_id' => $rateVisibilityMenu->id,
            'is_enabled' => true,
        ]);

        // Viewing income rates does not authorize adding an income line.
        $this->postJson(route('admin.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $this->missingIncomeType->id,
            'number_input' => 1,
        ])->assertForbidden();

        MenuRoleAccess::create([
            'company_id' => $this->company->id,
            'role_id' => Role::HR,
            'menu_id' => $deductionMenu->id,
            'is_enabled' => true,
        ]);

        $this->get(route('admin.payroll-drafts.edit', $this->draft->id))
            ->assertOk()
            ->assertSee('Add Deduction')
            ->assertSee('One Time Deduction');

        $this->postJson(route('admin.payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $this->missingDeductionType->id,
            'number_input' => 2,
            'rate' => 40,
        ])->assertCreated();

        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft->id,
            'deduction_type_id' => $this->missingDeductionType->id,
            'number_input' => 2,
            'rate' => 40,
        ]);
    }

    public function test_adding_statutory_deduction_initializes_only_the_new_line(): void
    {
        $tax = DeductionType::where('code', 'TAX')->firstOrFail();
        $originalSss = $this->existingPayOut->only(['number_input', 'rate']);

        $this->actingAs($this->admin)->withSession(['active_company_id' => $this->company->id])
            ->postJson(route('company.payroll-drafts.add-deduction', $this->draft->id), [
                'deduction_type_id' => $tax->id,
                'rate' => 9999,
            ])->assertCreated();

        $taxLine = PayOut::withoutGlobalScopes()
            ->where('pay_id', $this->draft->id)
            ->where('deduction_type_id', $tax->id)
            ->firstOrFail();

        $this->assertSame(1.0, (float) $taxLine->number_input);
        $this->assertNotSame(9999.0, (float) $taxLine->rate);
        $this->assertSame($originalSss, $this->existingPayOut->fresh()->only(['number_input', 'rate']));
    }

    public function test_duplicate_lines_and_locked_drafts_are_rejected(): void
    {
        $admin = $this->actingAs($this->admin)->withSession(['active_company_id' => $this->company->id]);
        $admin->postJson(route('company.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $this->basicPayIn->income_type_id,
            'number_input' => 1,
        ])->assertUnprocessable();

        $admin->postJson(route('company.payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $this->existingPayOut->deduction_type_id,
            'rate' => 20,
        ])->assertUnprocessable();

        $this->draft->update(['status' => 'approved']);
        $admin->postJson(route('company.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $this->missingIncomeType->id,
            'number_input' => 1,
        ])->assertStatus(422);
    }

    public function test_each_active_loan_can_add_one_separate_deduction_line(): void
    {
        $loanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $this->missingDeductionType->id,
            'name' => 'Test Loan',
            'default_amortization_per_cutoff' => 75,
            'is_active' => true,
        ]);
        $loanOne = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 300,
            'total_amount' => 300,
            'current_balance' => 300,
            'per_cutoff_amount' => 75,
            'status' => 'ACTIVE',
        ]);
        $loanTwo = EmpLoan::create([
            'employee_id' => $this->employee->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 200,
            'total_amount' => 200,
            'current_balance' => 200,
            'per_cutoff_amount' => 50,
            'status' => 'ACTIVE',
        ]);

        $admin = $this->actingAs($this->admin)->withSession(['active_company_id' => $this->company->id]);
        $admin->postJson(route('company.payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $this->missingDeductionType->id,
            'loan_id' => $loanOne->id,
        ])->assertCreated();
        $admin->postJson(route('company.payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $this->missingDeductionType->id,
            'loan_id' => $loanTwo->id,
        ])->assertCreated();
        $admin->postJson(route('company.payroll-drafts.add-deduction', $this->draft->id), [
            'deduction_type_id' => $this->missingDeductionType->id,
            'loan_id' => $loanOne->id,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('pay_outs', ['pay_id' => $this->draft->id, 'loan_id' => $loanOne->id, 'rate' => 75]);
        $this->assertDatabaseHas('pay_outs', ['pay_id' => $this->draft->id, 'loan_id' => $loanTwo->id, 'rate' => 50]);
    }
}
