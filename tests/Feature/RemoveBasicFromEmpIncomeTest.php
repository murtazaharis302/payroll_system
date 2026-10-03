<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
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

class RemoveBasicFromEmpIncomeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected IncomeType $basicType;
    protected IncomeType $allowanceType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_nobasic@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'No Basic EmpIncome Corp',
            'company_code' => 'NOBASIC',
            'email' => 'nobasic@test.com',
            'status' => '1',
        ]);
        $this->adminUser->update(['company_id' => $this->company->id]);

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
        $this->allowanceType = IncomeType::where('code', 'ALLOWANCE')->firstOrFail();
    }

    private function makeEmployee(string $code, float $monthlyRate = 40000.00): EmployeeMaster
    {
        return EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => $code,
            'first_name' => 'Emp',
            'last_name' => $code,
            'monthly_rate' => $monthlyRate,
            'status' => 'active',
        ]);
    }

    public function test_creating_employee_does_not_generate_basic_emp_income_record(): void
    {
        $employee = $this->makeEmployee('EMP-NOBASIC-01');
        $hasBasic = EmpIncome::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('income_type_id', $this->basicType->id)
            ->exists();
        $this->assertFalse($hasBasic);
    }

    public function test_emp_income_controller_rejects_basic_assignment_and_excludes_from_views(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('emp-incomes.create'));
        $response->assertStatus(200);
        $this->assertFalse(collect($response->viewData('incomeTypes'))->contains('code', 'BASIC'));

        $employee = $this->makeEmployee('EMP-NOBASIC-02', 20000.00);
        $storeResponse = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('emp-incomes.store'), [
                'employee_id' => $employee->id,
                'income_type_id' => $this->basicType->id,
                'amount' => 25000.00,
                'rate' => 25000.00,
                'frequency' => 'monthly',
                'is_active' => 1,
            ]);
        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHas('statusDanger');

        $this->assertFalse(EmpIncome::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('income_type_id', $this->basicType->id)
            ->exists());
    }

    public function test_rogue_legacy_basic_emp_income_does_not_override_employee_master_rates(): void
    {
        $employee = $this->makeEmployee('EMP-NOBASIC-03', 30000.00);
        EmpIncome::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 45000.00,
            'amount' => 45000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], ['use_fixed_income_amount' => false]);
        $draft = $headers->first();

        $basicPayIns = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->basicType->id)
            ->get();
        $this->assertCount(1, $basicPayIns);

        $basicLine = $basicPayIns->first();
        $this->assertEquals(30000.00, (float) $basicLine->rate);
        $this->assertEquals(15000.00, (float) ($basicLine->rate * $basicLine->number_input));
    }

    public function test_payroll_draft_calculates_basic_pay_without_basic_emp_income(): void
    {
        $employee = $this->makeEmployee('EMP-NOBASIC-04', 50000.00);
        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $basicPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->basicType->id)
            ->firstOrFail();
        $this->assertEquals(50000.00, (float) $basicPayIn->rate);
        $this->assertEquals(25000.00, (float) $basicPayIn->amount);
    }

    public function test_reintroduced_cleanup_migration_deletes_basic_emp_income_and_preserves_incometype_and_historical_payin(): void
    {
        $employee = $this->makeEmployee('EMP-NOBASIC-05', 60000.00);
        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $historicalPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $this->basicType->id)
            ->firstOrFail();

        $legacyEmpIncome = EmpIncome::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 60000.00,
            'amount' => 60000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $duplicateCustomType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'BASIC_SALARY',
            'name' => 'Basic Salary',
            'is_active' => true,
        ]);

        $duplicateEmpIncome = EmpIncome::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'income_type_id' => $duplicateCustomType->id,
            'rate' => 60000.00,
            'amount' => 60000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_08_22_000001_cleanup_reintroduced_basic_emp_incomes.php');
        $migration->up();

        $this->assertDatabaseMissing('emp_income', ['id' => $legacyEmpIncome->id]);
        $this->assertDatabaseMissing('emp_income', ['id' => $duplicateEmpIncome->id]);
        $this->assertDatabaseHas('income_types', ['id' => $this->basicType->id, 'code' => 'BASIC', 'is_active' => true]);
        $this->assertDatabaseHas('income_types', ['id' => $duplicateCustomType->id, 'is_active' => false]);
        $this->assertDatabaseHas('pay_ins', ['id' => $historicalPayIn->id, 'income_type_id' => $this->basicType->id]);
    }

    public function test_company_master_and_erm_ajax_exclude_basic_and_do_not_render_legacy_basic_rows(): void
    {
        $employee = $this->makeEmployee('EMP-NOBASIC-06', 45000.00);
        EmpIncome::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->basicType->id,
            'rate' => 45000.00,
            'amount' => 45000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
        EmpIncome::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'income_type_id' => $this->allowanceType->id,
            'rate' => 500.00,
            'amount' => 500.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->getJson(route('payroll-employees.full-details', $employee->id));
        $response->assertStatus(200);

        $incomeTypes = collect($response->json('income_types'));
        $incomes = collect($response->json('incomes'));
        $this->assertFalse($incomeTypes->contains('code', 'BASIC'));
        $this->assertTrue($incomeTypes->contains('code', 'ALLOWANCE'));
        $this->assertFalse($incomes->contains('code', 'BASIC'));
        $this->assertTrue($incomes->contains('code', 'ALLOWANCE'));
    }

    public function test_company_master_ajax_saving_endpoint_rejects_basic_income(): void
    {
        $employee = $this->makeEmployee('EMP-NOBASIC-07', 40000.00);
        $payload = [
            'first_name' => 'Reject',
            'last_name' => 'Saver',
            'dept_id' => $this->dept->id,
            'status' => 'active',
            'incomes' => [
                ['income_type_id' => $this->basicType->id, 'amount' => 40000.00],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->postJson(route('payroll-employees.update-record', $employee->id), $payload);
        $response->assertStatus(422);

        $this->assertFalse(EmpIncome::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('income_type_id', $this->basicType->id)
            ->exists());
    }

    public function test_single_add_income_and_batch_changes_exclude_and_reject_basic(): void
    {
        $employee = $this->makeEmployee('EMP-NOBASIC-08', 30000.00);
        $draft = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'days_worked' => 10,
            'lock_version' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('company.payroll-drafts.edit', $draft->id));
        $response->assertOk();
        $this->assertFalse(collect($response->viewData('availableIncomeTypes'))->contains('code', 'BASIC'));

        $addResponse = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.add-income', $draft->id), [
                'income_type_id' => $this->basicType->id,
                'number_input' => 1,
                'rate' => 5000,
            ]);
        $addResponse->assertSessionHasErrors(['income_type_id']);

        $batchGet = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('company.payroll-drafts.batch-change.create', ['payroll_period_id' => $this->period->id]));
        $batchGet->assertOk();
        $this->assertFalse(collect($batchGet->viewData('allCompanyIncomeTypes'))->contains('code', 'BASIC'));

        $batchPost = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->basicType->id],
                'draft_ids' => [$draft->id],
                'income_type_configs' => [
                    $this->basicType->id => ['number_input' => 1, 'rate' => 5000],
                ],
            ]);
        $batchPost->assertRedirect();
        $batchPost->assertSessionHas('generation_errors');
    }

    public function test_income_types_management_screen_hides_and_protects_canonical_basic(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('company.income-types.index'));
        $response->assertOk();
        $response->assertDontSee('>BASIC<');

        $postRes = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.income-types.store'), [
                'name' => 'Another Basic Attempt',
                'code' => 'BASIC',
                'is_taxable' => 1,
                'is_active' => 1,
            ]);
        $postRes->assertRedirect();
        $postRes->assertSessionHasErrors(['code']);

        $editRes = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('company.income-types.edit', $this->basicType->id));
        $editRes->assertStatus(403);
    }

    public function test_custom_income_types_can_be_named_basic_and_are_saved_successfully(): void
    {
        $variants = [
            ['name' => 'Custom 1', 'code' => 'BASIC_SALARY'],
            ['name' => 'Custom 2', 'code' => 'BASIC-SALARY'],
            ['name' => 'Basic Salary', 'code' => 'CUSTOM_BS_01'],
            ['name' => 'Basic Pay', 'code' => 'CUSTOM_BP_01'],
            ['name' => 'BASIC PAY', 'code' => 'BP_CUSTOM'],
            ['name' => '  basic   salary  ', 'code' => 'BS_CUSTOM'],
        ];

        foreach ($variants as $payload) {
            $postRes = $this->actingAs($this->adminUser)
                ->withSession(['active_company_id' => $this->company->id])
                ->post(route('company.income-types.store'), [
                    'name' => $payload['name'],
                    'code' => $payload['code'],
                    'is_taxable' => 1,
                    'is_active' => 1,
                ]);

            $postRes->assertRedirect(route('company.income-types.index'));
            $postRes->assertSessionHas('statusSuccess');
            $this->assertDatabaseHas('income_types', ['code' => $payload['code']]);
        }

        $existingType = IncomeType::where('code', 'CUSTOM_BS_01')->firstOrFail();

        $putRes = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('company.income-types.update', $existingType->id), [
                'name' => 'Updated Basic Pay Custom',
                'code' => 'CUSTOM_BS_01',
                'is_taxable' => 1,
                'is_active' => 1,
            ]);

        $putRes->assertRedirect(route('company.income-types.index'));
        $putRes->assertSessionHas('statusSuccess');
        $this->assertDatabaseHas('income_types', [
            'id' => $existingType->id,
            'name' => 'Updated Basic Pay Custom',
        ]);
    }
}
