<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpDeduction;
use App\Models\EmpIncome;
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
use App\Models\PayrollPolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollLineCalculator;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollDraftAddLineTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $hr;

    private EmployeeMaster $employee;

    private PayrollPeriod $period;

    private PayHeader $draft;

    private PayIn $basicPayIn;

    private PayOut $existingPayOut;

    private IncomeType $missingIncomeType;

    private DeductionType $missingDeductionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);
        $this->seed(MenusTableSeeder::class);

        Role::firstOrCreate(['id' => Role::SUPER_ADMIN, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => Role::COMPANY_ADMIN, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => Role::HR, 'name' => 'HR']);

        $this->admin = User::create([
            'name' => 'Draft Add Admin',
            'email' => 'draft-add-admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);
        $this->company = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Draft Add Company',
            'company_code' => 'DRAFTADD',
            'email' => 'draft-add@example.test',
            'status' => '1',
        ]);
        $this->admin->update(['company_id' => $this->company->id]);
        $this->hr = User::create([
            'name' => 'Draft Add HR',
            'email' => 'draft-add-hr@example.test',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => Role::HR,
            'company_id' => $this->company->id,
        ]);

        $department = Department::create([
            'company_id' => $this->company->id,
            'code' => 'DRAFTADD',
            'name' => 'Draft Add Department',
            'is_active' => true,
        ]);
        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $department->id,
            'employee_code' => 'DRAFT-ADD-01',
            'first_name' => 'Draft',
            'last_name' => 'Employee',
            'monthly_rate' => 24000,
            'status' => 'active',
        ]);
        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Draft Add Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);
        $this->draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'draft',
            'days_worked' => 10,
        ]);

        $basic = IncomeType::where('code', 'BASIC')->firstOrFail();
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
        $this->basicPayIn = PayIn::create([
            'pay_id' => $this->draft->id,
            'income_type_id' => $basic->id,
            'number_input' => 1,
            'rate' => 12000,
        ]);
        $existingDeductionType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'EXISTING_DED',
            'name' => 'Existing Deduction',
            'type' => 'other',
            'is_active' => true,
        ]);
        $this->existingPayOut = PayOut::create([
            'pay_id' => $this->draft->id,
            'deduction_type_id' => $existingDeductionType->id,
            'number_input' => 1,
            'rate' => 500,
        ]);
        $this->draft->recalculateTotals();
    }

    public function test_admin_adds_missing_income_without_altering_existing_draft_data(): void
    {
        $original = $this->draft->fresh()->only(['days_worked', 'status']);
        $basic = $this->basicPayIn->only(['number_input', 'rate']);
        $deduction = $this->existingPayOut->only(['number_input', 'rate']);

        $response = $this->actingAs($this->admin)->withSession(['active_company_id' => $this->company->id])
            ->postJson(route('company.payroll-drafts.add-income', $this->draft->id), [
                'income_type_id' => $this->missingIncomeType->id,
                'number_input' => 2,
                'rate' => 125.50,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('pay_ins', [
            'pay_id' => $this->draft->id,
            'income_type_id' => $this->missingIncomeType->id,
            'number_input' => 2,
            'rate' => 125.50,
        ]);
        $this->assertSame($basic, $this->basicPayIn->fresh()->only(['number_input', 'rate']));
        $this->assertSame($deduction, $this->existingPayOut->fresh()->only(['number_input', 'rate']));
        $this->assertSame($original, $this->draft->fresh()->only(['days_worked', 'status']));
        $this->assertDatabaseMissing('emp_income', ['employee_id' => $this->employee->id, 'income_type_id' => $this->missingIncomeType->id]);
    }

    public function test_admin_explicit_income_rates_override_every_income_source_for_this_draft_only(): void
    {
        $overtime = IncomeType::where('code', 'OVERTIME')->firstOrFail();
        $assignedIncomeType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'ASSIGNED_OVERRIDE',
            'name' => 'Assigned Override Income',
            'is_active' => true,
        ]);
        $customBonus = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'CUSTOM_BONUS',
            'name' => 'Custom Bonus',
            'is_active' => true,
        ]);
        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $assignedIncomeType->id,
            'rate' => 125,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $admin = $this->actingAs($this->admin)->withSession(['active_company_id' => $this->company->id]);
        $admin
            ->postJson(route('company.payroll-drafts.add-income', $this->draft->id), [
                'income_type_id' => $customBonus->id,
                'number_input' => 2,
                'rate' => 701.25,
            ])->assertCreated();
        $admin
            ->postJson(route('company.payroll-drafts.add-income', $this->draft->id), [
                'income_type_id' => $overtime->id,
                'number_input' => 2,
                'rate' => 702.25,
            ])->assertCreated();
        $admin
            ->postJson(route('company.payroll-drafts.add-income', $this->draft->id), [
                'income_type_id' => $assignedIncomeType->id,
                'number_input' => 3,
                'rate' => 703.25,
            ])->assertCreated();
        $admin
            ->postJson(route('company.payroll-drafts.add-income', $this->draft->id), [
                'income_type_id' => $this->missingIncomeType->id,
                'number_input' => 4,
                'rate' => 704.25,
            ])->assertCreated();

        foreach ([
            [$customBonus->id, 2, 701.25],
            [$overtime->id, 2, 702.25],
            [$assignedIncomeType->id, 3, 703.25],
            [$this->missingIncomeType->id, 4, 704.25],
        ] as [$incomeTypeId, $numberInput, $rate]) {
            $this->assertDatabaseHas('pay_ins', [
                'pay_id' => $this->draft->id,
                'income_type_id' => $incomeTypeId,
                'number_input' => $numberInput,
                'rate' => $rate,
            ]);
        }

        $this->assertDatabaseHas('emp_income', [
            'employee_id' => $this->employee->id,
            'income_type_id' => $assignedIncomeType->id,
            'rate' => 125,
        ]);
    }

    public function test_hr_income_additions_keep_server_rates_and_reject_rate_submission(): void
    {
        $this->employee->update(['hourly_rate' => 100]);
        $incomeMenu = Menu::where('module', 'payroll_income_inputs')->firstOrFail();
        $assignedIncomeType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'HR_ASSIGNED_RATE',
            'name' => 'HR Assigned Income',
            'is_active' => true,
        ]);
        $customAllowance = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'HR_ALLOWANCE',
            'name' => 'HR Allowance',
            'is_active' => true,
        ]);
        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $assignedIncomeType->id,
            'rate' => 88,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
        MenuRoleAccess::create([
            'company_id' => $this->company->id,
            'role_id' => Role::HR,
            'menu_id' => $incomeMenu->id,
            'is_enabled' => true,
        ]);

        $hr = $this->actingAs($this->hr)->withSession(['active_company_id' => $this->company->id]);
        $basic = IncomeType::where('code', 'BASIC')->firstOrFail();
        $overtime = IncomeType::where('code', 'OVERTIME')->firstOrFail();
        $hr->postJson(route('admin.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $customAllowance->id,
            'number_input' => 2,
            'rate' => 999,
        ])->assertForbidden();

        $hr->postJson(route('admin.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $customAllowance->id,
            'number_input' => 2,
        ])->assertCreated();
        $hr->postJson(route('admin.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $overtime->id,
            'number_input' => 2,
        ])->assertCreated();
        $hr->postJson(route('admin.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $assignedIncomeType->id,
            'number_input' => 3,
        ])->assertCreated();
        $hr->postJson(route('admin.payroll-drafts.add-income', $this->draft->id), [
            'income_type_id' => $this->missingIncomeType->id,
            'number_input' => 4,
        ])->assertCreated();

        $calculator = new PayrollLineCalculator(PayrollPolicy::forCompany(
            $this->company->id,
            (string) $this->period->period_start
        ));
        $expectedOvertime = $calculator->calculateOvertimePay($this->employee->fresh(), 2, null, 'regular');

        $this->assertEquals($expectedOvertime['rate'], (float) PayIn::withoutGlobalScopes()
            ->where('pay_id', $this->draft->id)
            ->where('income_type_id', $overtime->id)
            ->value('rate'));
        $this->assertDatabaseHas('pay_ins', [
            'pay_id' => $this->draft->id,
            'income_type_id' => $assignedIncomeType->id,
            'number_input' => 3,
            'rate' => 88,
        ]);
        $this->assertDatabaseHas('pay_ins', [
            'pay_id' => $this->draft->id,
            'income_type_id' => $this->missingIncomeType->id,
            'number_input' => 4,
            'rate' => 0,
        ]);
        $this->assertNotSame(999.0, (float) PayIn::withoutGlobalScopes()
            ->where('pay_id', $this->draft->id)
            ->where('income_type_id', $customAllowance->id)
            ->value('rate'));
        $this->assertNotSame(999.0, (float) PayIn::withoutGlobalScopes()
            ->where('pay_id', $this->draft->id)
            ->where('income_type_id', $overtime->id)
            ->value('rate'));
    }
}
