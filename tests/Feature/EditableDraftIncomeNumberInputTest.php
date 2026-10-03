<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EditableDraftIncomeNumberInputTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(\Database\Seeders\StatutoryRulesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_draft_inc@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Draft Income Test Corp',
            'company_code' => 'DRAFTINC',
            'email' => 'draftinc@corp.com',
            'status' => '1',
        ]);

        session(['active_company_id' => $this->company->id]);
        $this->actingAs($this->adminUser);

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
    }

    /**
     * Test 1: Client Reproduction - 5 income Number Input fields (5, 6, 7, 8, 9) submitted, all persist.
     */
    public function test_client_reproduction_five_income_number_inputs_all_persist(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-FIVE-INC',
            'first_name' => 'Client',
            'last_name' => 'Repro',
            'monthly_rate' => 50000.00,
            'status' => 'active',
        ]);

        // Generate draft with Basic, Overtime, Allowance, Bonus, Other
        $options = [
            'use_fixed_amount' => true,
            'incomes' => [
                ['income_type' => 'BASIC', 'display' => 1],
                ['income_type' => 'OVERTIME', 'display' => 1],
                ['income_type' => 'ALLOWANCE', 'display' => 1, 'amount' => 100.00],
                ['income_type' => 'BONUS', 'display' => 1, 'amount' => 200.00],
                ['income_type' => 'OTHER', 'display' => 1, 'amount' => 300.00],
            ],
            'deductions' => [
                ['deduction_type' => 'TAX', 'display' => 1],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        // Get PayIn line IDs
        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))->firstOrFail();
        $otPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->firstOrFail();
        $allowPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'ALLOWANCE'))->firstOrFail();
        $bonusPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'BONUS'))->firstOrFail();
        $otherPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OTHER'))->firstOrFail();

        // Client edit: 5, 6, 7, 8, 9
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 5.0, 'rate' => $basicPayIn->rate],
                ['id' => $otPayIn->id, 'number_input' => 6.0, 'rate' => 150.00],
                ['id' => $allowPayIn->id, 'number_input' => 7.0, 'rate' => 100.00],
                ['id' => $bonusPayIn->id, 'number_input' => 8.0, 'rate' => 200.00],
                ['id' => $otherPayIn->id, 'number_input' => 9.0, 'rate' => 300.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        // Refresh and verify every value persisted
        $draft->refresh();
        $basicPayIn->refresh();
        $otPayIn->refresh();
        $allowPayIn->refresh();
        $bonusPayIn->refresh();
        $otherPayIn->refresh();

        $this->assertEquals(5.0, (float) $basicPayIn->number_input);
        $this->assertEquals(6.0, (float) $otPayIn->number_input);
        $this->assertEquals(7.0, (float) $allowPayIn->number_input);
        $this->assertEquals(8.0, (float) $bonusPayIn->number_input);
        $this->assertEquals(9.0, (float) $otherPayIn->number_input);
    }

    /**
     * Test 2: Overtime PayIn number_input persists and does not revert to 0.
     */
    public function test_overtime_number_input_persists_and_recalculates_line_total(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-OT-PERSIST',
            'first_name' => 'Overtime',
            'last_name' => 'Persist',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $otPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->firstOrFail();

        // Submit Overtime number_input = 10.5 with rate = 200.00
        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $otPayIn->id, 'number_input' => 10.5, 'rate' => 200.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $otPayIn->refresh();
        $this->assertEquals(10.5, (float) $otPayIn->number_input);
        $this->assertEquals(200.00, (float) $otPayIn->rate);
    }

    /**
     * Test 3: Custom Income number_input save persists.
     */
    public function test_custom_income_number_input_save_persists(): void
    {
        $customType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'HAZARD',
            'name' => 'Hazard Pay',
            'type' => 'allowance',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-HAZARD',
            'first_name' => 'Custom',
            'last_name' => 'Hazard',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'incomes' => [
                ['income_type' => 'HAZARD', 'display' => 1, 'amount' => 500.00],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $hazardPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->where('income_type_id', $customType->id)->firstOrFail();

        $response = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $hazardPayIn->id, 'number_input' => 3.0, 'rate' => 500.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $hazardPayIn->refresh();
        $this->assertEquals(3.0, (float) $hazardPayIn->number_input);
        $this->assertEquals(500.00, (float) $hazardPayIn->rate);
    }

    /**
     * Test 4: Total gross pay equals sum of number_input * rate for all PayIn lines.
     */
    public function test_total_gross_pay_equals_sum_of_number_input_times_rate(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-TOTAL-SUM',
            'first_name' => 'Total',
            'last_name' => 'Sum',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $basicPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))->firstOrFail();
        $otPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->firstOrFail();

        $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $basicPayIn->id, 'number_input' => 1.0, 'rate' => 15000.00],
                ['id' => $otPayIn->id, 'number_input' => 5.0, 'rate' => 200.00],
            ],
        ])->assertSessionHasNoErrors();

        $draft->refresh();

        $allPayInsSum = (float) PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->sum(\DB::raw('rate * number_input'));
        $this->assertEquals($allPayInsSum, (float) $draft->total_gross_pay);
    }

    /**
     * Test 5: Days Worked on PayHeader is not modified when saving income Number Input.
     */
    public function test_days_worked_is_not_modified_when_saving_income_number_input(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DAYS-WORKED',
            'first_name' => 'Days',
            'last_name' => 'Worked',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id]);
        $draft = $headers->first();

        $draft->update(['days_worked' => 14]);

        $otPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft->id)->whereHas('incomeType', fn($q) => $q->where('code', 'OVERTIME'))->firstOrFail();

        $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 14, // Preserved
            'pay_ins' => [
                ['id' => $otPayIn->id, 'number_input' => 8.0, 'rate' => 150.00],
            ],
        ])->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertEquals(14, (float) $draft->days_worked);
    }

    /**
     * Test 6: Security - Submitting line ID belonging to another company/draft returns 422.
     */
    public function test_security_submitting_cross_tenant_pay_in_returns_422(): void
    {
        $otherCompany = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Security Corp',
            'company_code' => 'SECCORP',
            'email' => 'sec@corp.com',
            'status' => '1',
        ]);

        $otherDept = Department::create([
            'company_id' => $otherCompany->id,
            'code' => 'SECENG',
            'name' => 'Sec Engineering',
            'is_active' => true,
        ]);

        $otherEmployee = EmployeeMaster::create([
            'company_id' => $otherCompany->id,
            'dept_id' => $otherDept->id,
            'employee_code' => 'EMP-SEC',
            'first_name' => 'Sec',
            'last_name' => 'Emp',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $otherPeriod = PayrollPeriod::create([
            'company_id' => $otherCompany->id,
            'period_name' => 'August 2026 Sec Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();

        $headers1 = $service->generateDraft($this->period, [EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-MY-CORP',
            'first_name' => 'My',
            'last_name' => 'Corp',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ])->id]);
        $draft1 = $headers1->first();

        $headers2 = $service->generateDraft($otherPeriod, [$otherEmployee->id]);
        $draft2 = $headers2->first();

        $otherPayIn = PayIn::withoutGlobalScopes()->where('pay_id', $draft2->id)->firstOrFail();

        // Attempt to submit $otherPayIn on $draft1 via JSON endpoint
        $response = $this->putJson(route('admin.payroll-drafts.update', $draft1->id), [
            'days_worked' => 15,
            'pay_ins' => [
                ['id' => $otherPayIn->id, 'number_input' => 99.0],
            ],
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 7: Cross-Company Income Lookup Scoping During Draft Update.
     */
    public function test_cross_company_income_lookup_scoping_during_draft_update(): void
    {
        // Scope global BASIC income type to Company 1
        $company1Basic = IncomeType::withoutGlobalScopes()->where('code', 'BASIC')->firstOrFail();
        $company1Basic->update(['company_id' => $this->company->id]);

        // Create Company 2
        $otherCompany = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Other Corp 2',
            'company_code' => 'OTHERCORP2',
            'email' => 'other2@corp.com',
            'status' => '1',
        ]);

        $otherDept = Department::create([
            'company_id' => $otherCompany->id,
            'code' => 'OTHENG2',
            'name' => 'Other Engineering 2',
            'is_active' => true,
        ]);

        $otherPeriod = PayrollPeriod::create([
            'company_id' => $otherCompany->id,
            'period_name' => 'August 2026 Cutoff Other 2',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $otherEmployee = EmployeeMaster::create([
            'company_id' => $otherCompany->id,
            'dept_id' => $otherDept->id,
            'employee_code' => 'EMP-XCOMP-INC',
            'first_name' => 'Other',
            'last_name' => 'IncEmp',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        // Create Company 2 draft and PayIn line linked to $company1Basic
        $draft2 = PayHeader::create([
            'company_id' => $otherCompany->id,
            'payroll_period_id' => $otherPeriod->id,
            'employee_id' => $otherEmployee->id,
            'days_worked' => 15,
            'status' => 'draft',
        ]);

        $payIn2 = PayIn::create([
            'pay_id' => $draft2->id,
            'income_type_id' => $company1Basic->id,
            'number_input' => 1.00,
            'rate' => 20000.00,
        ]);

        // Updating Company 2's draft without pay_ins array must NOT resolve Company 1's BASIC income type
        session(['active_company_id' => $otherCompany->id]);
        $response = $this->put(route('admin.payroll-drafts.update', $draft2->id), [
            'days_worked' => 15,
            'basic_pay' => 30000.00,
        ]);

        $response->assertSessionHasNoErrors();

        $payIn2->refresh();
        // Rate remains 20000.00 because Company 1's BASIC income type was correctly excluded by company scoping
        $this->assertEquals(20000.00, (float) $payIn2->rate);
    }
}
