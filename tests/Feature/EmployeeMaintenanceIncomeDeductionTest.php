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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeMaintenanceIncomeDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminCompanyA;
    protected User $adminCompanyB;
    protected Company $companyA;
    protected Company $companyB;
    protected EmployeeMaster $employeeA;
    protected IncomeType $incomeTypeA;
    protected IncomeType $incomeTypeB;
    protected DeductionType $deductionTypeA;
    protected DeductionType $deductionTypeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminCompanyA = User::create([
            'name' => 'Admin Comp A',
            'email' => 'admin_a@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->adminCompanyA->id,
            'name' => 'Company A',
            'email' => 'comp_a@test.com',
            'status' => '1',
        ]);
        $this->adminCompanyA->update(['company_id' => $this->companyA->id]);

        $this->adminCompanyB = User::create([
            'name' => 'Admin Comp B',
            'email' => 'admin_b@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->companyB = Company::create([
            'user_id' => $this->adminCompanyB->id,
            'name' => 'Company B',
            'email' => 'comp_b@test.com',
            'status' => '1',
        ]);
        $this->adminCompanyB->update(['company_id' => $this->companyB->id]);

        $this->employeeA = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'employee_code' => 'EMP-A-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'monthly_rate' => 30000.00,
            'status' => 'active',
            'employment_type' => 'REGULAR',
        ]);

        $this->incomeTypeGlobal = IncomeType::create([
            'company_id' => null,
            'name' => 'Global Basic Salary',
            'code' => 'BASIC_GLOBAL',
            'type' => 'taxable',
            'is_active' => true,
        ]);

        $this->incomeTypeA = IncomeType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company A Allowance',
            'code' => 'ALLOWANCE_A',
            'type' => 'taxable',
            'is_active' => true,
        ]);

        $this->incomeTypeB = IncomeType::create([
            'company_id' => $this->companyB->id,
            'name' => 'Company B Custom Bonus',
            'code' => 'BONUS_B',
            'type' => 'taxable',
            'is_active' => true,
        ]);

        $this->deductionTypeGlobal = DeductionType::create([
            'company_id' => null,
            'name' => 'Global SSS Deduction',
            'code' => 'SSS_GLOBAL',
            'type' => 'statutory',
            'is_active' => true,
        ]);

        $this->deductionTypeA = DeductionType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company A Union Fee',
            'code' => 'UNION_A',
            'type' => 'other',
            'is_active' => true,
        ]);

        $this->deductionTypeB = DeductionType::create([
            'company_id' => $this->companyB->id,
            'name' => 'Company B Parking Fee',
            'code' => 'PARKING_B',
            'type' => 'other',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: An authorized admin can add, update, and remove a default income assignment (EmpIncome).
     */
    public function test_authorized_admin_can_add_update_and_remove_default_income_assignment(): void
    {
        // 1. Add default income
        $responseAdd = $this->actingAs($this->adminCompanyA)->post(route('emp-incomes.store'), [
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeA->id,
            'amount' => 1500.00,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $responseAdd->assertRedirect(route('emp-incomes.index'));
        $empInc = EmpIncome::where('employee_id', $this->employeeA->id)
            ->where('income_type_id', $this->incomeTypeA->id)
            ->first();
        $this->assertNotNull($empInc);
        $this->assertEquals(1500.00, (float) $empInc->amount);

        // 2. Update default income
        $responseUpdate = $this->actingAs($this->adminCompanyA)->put(route('emp-incomes.update', $empInc->id), [
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeA->id,
            'amount' => 2000.00,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $responseUpdate->assertRedirect(route('emp-incomes.index'));
        $empInc->refresh();
        $this->assertEquals(2000.00, (float) $empInc->amount);

        // 3. Remove default income
        $responseRemove = $this->actingAs($this->adminCompanyA)->delete(route('emp-incomes.destroy', $empInc->id));
        $responseRemove->assertRedirect(route('emp-incomes.index'));
        $this->assertDatabaseMissing('emp_income', ['id' => $empInc->id]);
    }

    /**
     * Test 2: An authorized admin can add, update, and remove a default deduction assignment (EmpDeduction).
     */
    public function test_authorized_admin_can_add_update_and_remove_default_deduction_assignment(): void
    {
        // 1. Add default deduction
        $responseAdd = $this->actingAs($this->adminCompanyA)->post(route('emp-deductions.store'), [
            'employee_id' => $this->employeeA->id,
            'deduction_type_id' => $this->deductionTypeA->id,
            'amount' => 300.00,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $responseAdd->assertRedirect(route('emp-deductions.index'));
        $empDec = EmpDeduction::where('employee_id', $this->employeeA->id)
            ->where('deduction_type_id', $this->deductionTypeA->id)
            ->first();
        $this->assertNotNull($empDec);
        $this->assertEquals(300.00, (float) $empDec->amount);

        // 2. Update default deduction
        $responseUpdate = $this->actingAs($this->adminCompanyA)->put(route('emp-deductions.update', $empDec->id), [
            'employee_id' => $this->employeeA->id,
            'deduction_type_id' => $this->deductionTypeA->id,
            'amount' => 450.00,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $responseUpdate->assertRedirect(route('emp-deductions.index'));
        $empDec->refresh();
        $this->assertEquals(450.00, (float) $empDec->amount);

        // 3. Remove default deduction
        $responseRemove = $this->actingAs($this->adminCompanyA)->delete(route('emp-deductions.destroy', $empDec->id));
        $responseRemove->assertRedirect(route('emp-deductions.index'));
        $this->assertDatabaseMissing('emp_deductions', ['id' => $empDec->id]);
    }

    /**
     * Test 3: Duplicate type assignment for the same employee is rejected or updated deterministically.
     */
    public function test_duplicate_type_assignment_for_same_employee_is_rejected_or_updated_deterministically(): void
    {
        // Standalone controller store rejects duplicates with statusDanger
        $this->actingAs($this->adminCompanyA)->post(route('emp-incomes.store'), [
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeA->id,
            'amount' => 1000.00,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $responseDuplicate = $this->actingAs($this->adminCompanyA)->post(route('emp-incomes.store'), [
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeA->id,
            'amount' => 2000.00,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $responseDuplicate->assertSessionHas('statusDanger');
        $this->assertEquals(1, EmpIncome::where('employee_id', $this->employeeA->id)->where('income_type_id', $this->incomeTypeA->id)->count());

        // Quick record maintenance payload with duplicate income_type_id entries is deduplicated deterministically
        $payload = [
            'first_name' => $this->employeeA->first_name,
            'last_name' => $this->employeeA->last_name,
            'status' => 'active',
            'incomes' => [
                ['income_type_id' => $this->incomeTypeA->id, 'amount' => 1000.00],
                ['income_type_id' => $this->incomeTypeA->id, 'amount' => 1800.00],
            ],
            'deductions' => [
                ['deduction_type_id' => $this->deductionTypeA->id, 'amount' => 250.00],
                ['deduction_type_id' => $this->deductionTypeA->id, 'amount' => 350.00],
            ],
        ];

        $quickRoute = route('company.payroll-employees.update-record', $this->employeeA->id);
        $responseQuick = $this->actingAs($this->adminCompanyA)->postJson($quickRoute, $payload);
        $responseQuick->assertStatus(200);

        // Verify exact single deduplicated entry per type ID
        $this->assertEquals(1, EmpIncome::where('employee_id', $this->employeeA->id)->where('income_type_id', $this->incomeTypeA->id)->count());
        $this->assertEquals(1800.00, (float) EmpIncome::where('employee_id', $this->employeeA->id)->where('income_type_id', $this->incomeTypeA->id)->value('rate'));

        $this->assertEquals(1, EmpDeduction::where('employee_id', $this->employeeA->id)->where('deduction_type_id', $this->deductionTypeA->id)->count());
        $this->assertEquals(350.00, (float) EmpDeduction::where('employee_id', $this->employeeA->id)->where('deduction_type_id', $this->deductionTypeA->id)->value('rate'));
    }

    /**
     * Test 4: Removing a default does not remove existing PayIn or PayOut records.
     */
    public function test_removing_default_does_not_remove_existing_payin_or_payout_records(): void
    {
        // 1. Create default income & deduction
        $empInc = EmpIncome::create([
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeA->id,
            'amount' => 2000.00,
            'rate' => 2000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $empDec = EmpDeduction::create([
            'employee_id' => $this->employeeA->id,
            'deduction_type_id' => $this->deductionTypeA->id,
            'amount' => 500.00,
            'rate' => 500.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // 2. Create a payroll period and draft with PayIn and PayOut lines
        $period = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'Historical Line Preservation Period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $payHeader = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
            'status' => 'draft',
        ]);

        $payIn = PayIn::create([
            'pay_id' => $payHeader->id,
            'income_type_id' => $this->incomeTypeA->id,
            'number_input' => 1.00,
            'rate' => 2000.00,
        ]);

        $payOut = PayOut::create([
            'pay_id' => $payHeader->id,
            'deduction_type_id' => $this->deductionTypeA->id,
            'number_input' => 1.00,
            'rate' => 500.00,
        ]);

        // 3. Remove defaults via maintenance update (empty array)
        $payload = [
            'first_name' => $this->employeeA->first_name,
            'last_name' => $this->employeeA->last_name,
            'status' => 'active',
            'incomes' => [],
            'deductions' => [],
        ];

        $quickRoute = route('company.payroll-employees.update-record', $this->employeeA->id);
        $this->actingAs($this->adminCompanyA)->postJson($quickRoute, $payload)->assertStatus(200);

        // Verify default assignments were deleted
        $this->assertDatabaseMissing('emp_income', ['id' => $empInc->id]);
        $this->assertDatabaseMissing('emp_deductions', ['id' => $empDec->id]);

        // Verify historical PayIn and PayOut lines REMAIN INTACT
        $this->assertDatabaseHas('pay_ins', ['id' => $payIn->id, 'rate' => 2000.00]);
        $this->assertDatabaseHas('pay_outs', ['id' => $payOut->id, 'rate' => 500.00]);
    }

    /**
     * Test 5: Company A cannot use Company B’s custom income or deduction types.
     */
    public function test_company_a_cannot_use_company_b_custom_income_or_deduction_types(): void
    {
        // 1. Attempt standalone store with Company B's income type for Company A employee
        $responseInc = $this->actingAs($this->adminCompanyA)->post(route('emp-incomes.store'), [
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeB->id,
            'amount' => 1000.00,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $responseInc->assertSessionHas('statusDanger');
        $this->assertEquals(0, EmpIncome::where('employee_id', $this->employeeA->id)->where('income_type_id', $this->incomeTypeB->id)->count());

        // 2. Attempt standalone store with Company B's deduction type for Company A employee
        $responseDec = $this->actingAs($this->adminCompanyA)->post(route('emp-deductions.store'), [
            'employee_id' => $this->employeeA->id,
            'deduction_type_id' => $this->deductionTypeB->id,
            'amount' => 500.00,
            'frequency' => 'monthly',
            'is_active' => 1,
        ]);

        $responseDec->assertSessionHas('statusDanger');
        $this->assertEquals(0, EmpDeduction::where('employee_id', $this->employeeA->id)->where('deduction_type_id', $this->deductionTypeB->id)->count());

        // 3. Attempt quick maintenance update with Company B's types
        $payload = [
            'first_name' => $this->employeeA->first_name,
            'last_name' => $this->employeeA->last_name,
            'status' => 'active',
            'incomes' => [
                ['income_type_id' => $this->incomeTypeB->id, 'amount' => 1000.00],
            ],
            'deductions' => [
                ['deduction_type_id' => $this->deductionTypeB->id, 'amount' => 500.00],
            ],
        ];

        $quickRoute = route('company.payroll-employees.update-record', $this->employeeA->id);
        $responseQuick = $this->actingAs($this->adminCompanyA)->postJson($quickRoute, $payload);

        $responseQuick->assertStatus(422);
        $this->assertEquals(0, EmpIncome::where('employee_id', $this->employeeA->id)->where('income_type_id', $this->incomeTypeB->id)->count());
        $this->assertEquals(0, EmpDeduction::where('employee_id', $this->employeeA->id)->where('deduction_type_id', $this->deductionTypeB->id)->count());
    }

    /**
     * Test 6: Full details AJAX returns company-scoped income and deduction types list.
     */
    public function test_full_details_ajax_returns_company_scoped_income_and_deduction_types_list(): void
    {
        $route = route('company.payroll-employees.full-details', $this->employeeA->id);
        $response = $this->actingAs($this->adminCompanyA)->getJson($route);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        $incomeTypes = $response->json('income_types');
        $deductionTypes = $response->json('deduction_types');

        $this->assertNotEmpty($incomeTypes);
        $this->assertNotEmpty($deductionTypes);

        $incomeTypeIds = collect($incomeTypes)->pluck('id')->all();
        $this->assertContains($this->incomeTypeGlobal->id, $incomeTypeIds);
        $this->assertContains($this->incomeTypeA->id, $incomeTypeIds);
        $this->assertNotContains($this->incomeTypeB->id, $incomeTypeIds);

        $deductionTypeIds = collect($deductionTypes)->pluck('id')->all();
        $this->assertContains($this->deductionTypeGlobal->id, $deductionTypeIds);
        $this->assertContains($this->deductionTypeA->id, $deductionTypeIds);
        $this->assertNotContains($this->deductionTypeB->id, $deductionTypeIds);
    }

    /**
     * Test 7: Standard global Income and Deduction Types are created by setup migration and returned in AJAX and view.
     */
    public function test_standard_global_income_and_deduction_types_are_created_and_returned_in_ajax_and_view(): void
    {
        // 1. Verify DB contains standard global records with company_id = NULL and is_active = 1
        $this->assertDatabaseHas('income_types', [
            'code' => 'BASIC',
            'company_id' => null,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('income_types', [
            'code' => 'ALLOWANCE',
            'company_id' => null,
            'is_active' => 1,
        ]);

        // 2. Verify employee-details AJAX response returns non-empty income_types containing BASIC and ALLOWANCE
        $route = route('company.payroll-employees.full-details', $this->employeeA->id);
        $response = $this->actingAs($this->adminCompanyA)->getJson($route);

        $response->assertStatus(200);
        $incomeTypes = $response->json('income_types');
        $this->assertNotEmpty($incomeTypes);

        $incomeCodes = collect($incomeTypes)->pluck('code')->all();
        $this->assertContains('BASIC', $incomeCodes);
        $this->assertContains('ALLOWANCE', $incomeCodes);

        // 3. Verify maintenance page view renders incomeTypes in Blade variable
        $indexRoute = route('company.payroll-employees.index', ['employee_id' => $this->employeeA->id]);
        $viewResponse = $this->actingAs($this->adminCompanyA)->get($indexRoute);

        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('BASIC');
        $viewResponse->assertSee('ALLOWANCE');
    }

    /**
     * Test 8: Non-destructive update preserves assignment record IDs in-place.
     */
    public function test_non_destructive_update_preserves_assignment_record_ids(): void
    {
        $empInc = EmpIncome::create([
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeA->id,
            'amount' => 1200.00,
            'rate' => 1200.00,
            'is_active' => true,
        ]);

        $empDec = EmpDeduction::create([
            'employee_id' => $this->employeeA->id,
            'deduction_type_id' => $this->deductionTypeA->id,
            'amount' => 300.00,
            'rate' => 300.00,
            'is_active' => true,
        ]);

        $originalIncId = $empInc->id;
        $originalDecId = $empDec->id;

        $payload = [
            'first_name' => $this->employeeA->first_name,
            'last_name' => $this->employeeA->last_name,
            'status' => 'active',
            'incomes' => [
                ['id' => $originalIncId, 'income_type_id' => $this->incomeTypeA->id, 'amount' => 1500.00],
            ],
            'deductions' => [
                ['id' => $originalDecId, 'deduction_type_id' => $this->deductionTypeA->id, 'amount' => 400.00],
            ],
        ];

        $quickRoute = route('company.payroll-employees.update-record', $this->employeeA->id);
        $response = $this->actingAs($this->adminCompanyA)->postJson($quickRoute, $payload);
        $response->assertStatus(200);

        // Verify ID identity was preserved
        $updatedInc = EmpIncome::find($originalIncId);
        $this->assertNotNull($updatedInc);
        $this->assertEquals(1500.00, (float) $updatedInc->amount);

        $updatedDec = EmpDeduction::find($originalDecId);
        $this->assertNotNull($updatedDec);
        $this->assertEquals(400.00, (float) $updatedDec->amount);
    }

    /**
     * Test 9: Forged assignment ID belonging to another employee/company is rejected.
     */
    public function test_forged_assignment_id_is_rejected(): void
    {
        $employeeB = EmployeeMaster::create([
            'company_id' => $this->companyB->id,
            'employee_code' => 'EMP-B-001',
            'first_name' => 'Foreign',
            'last_name' => 'User',
            'status' => 'active',
        ]);

        $foreignInc = EmpIncome::create([
            'employee_id' => $employeeB->id,
            'income_type_id' => $this->incomeTypeB->id,
            'amount' => 5000.00,
            'rate' => 5000.00,
            'is_active' => true,
        ]);

        $payload = [
            'first_name' => $this->employeeA->first_name,
            'last_name' => $this->employeeA->last_name,
            'status' => 'active',
            'incomes' => [
                ['id' => $foreignInc->id, 'income_type_id' => $this->incomeTypeA->id, 'amount' => 1500.00],
            ],
        ];

        $quickRoute = route('company.payroll-employees.update-record', $this->employeeA->id);
        $response = $this->actingAs($this->adminCompanyA)->postJson($quickRoute, $payload);
        $response->assertStatus(422);
    }

    /**
     * Test 10: Failed update rolls back entire transaction atomically.
     */
    public function test_failed_update_rolls_back_entire_transaction(): void
    {
        $originalLastName = $this->employeeA->last_name;

        $payload = [
            'first_name' => 'ModifiedFirstName',
            'last_name' => 'ModifiedLastName',
            'status' => 'active',
            'incomes' => [
                ['income_type_id' => 999999, 'amount' => 1500.00], // Invalid type ID
            ],
        ];

        $quickRoute = route('company.payroll-employees.update-record', $this->employeeA->id);
        $response = $this->actingAs($this->adminCompanyA)->postJson($quickRoute, $payload);
        $response->assertStatus(422);

        // Verify employee record was NOT changed due to atomic rollback
        $this->employeeA->refresh();
        $this->assertEquals($originalLastName, $this->employeeA->last_name);
    }

    /**
     * Test 11: HR user is strictly blocked (403) from ERM pages and AJAX endpoints.
     */
    public function test_hr_user_blocked_from_erm_pages_and_ajax(): void
    {
        $hrUser = User::create([
            'company_id' => $this->companyA->id,
            'name' => 'HR User Blocked',
            'email' => 'hr_blocked@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $this->actingAs($hrUser)->get(route('company.payroll-employees.index'))->assertStatus(403);
        $this->actingAs($hrUser)->get(route('company.payroll-employees.company-master'))->assertStatus(403);
        $this->actingAs($hrUser)->get(route('company.payroll-employees.full-details', $this->employeeA->id))->assertStatus(403);

        $quickRoute = route('company.payroll-employees.update-record', $this->employeeA->id);
        $this->actingAs($hrUser)->postJson($quickRoute, [
            'first_name' => 'Test',
            'last_name' => 'HR',
            'status' => 'active',
        ])->assertStatus(403);
    }

    /**
     * Test 12: Submitting an existing assignment ID with a mismatched type ID is rejected.
     */
    public function test_assignment_id_type_mismatch_is_rejected(): void
    {
        $incomeType2 = IncomeType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company A Overtime',
            'code' => 'OVERTIME_A',
            'type' => 'taxable',
            'is_active' => true,
        ]);

        $empInc = EmpIncome::create([
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeA->id,
            'amount' => 1000.00,
            'rate' => 1000.00,
            'is_active' => true,
        ]);

        // Attempt to submit empInc->id with incomeType2->id
        $payloadInc = [
            'first_name' => $this->employeeA->first_name,
            'last_name' => $this->employeeA->last_name,
            'status' => 'active',
            'incomes' => [
                ['id' => $empInc->id, 'income_type_id' => $incomeType2->id, 'amount' => 1500.00],
            ],
        ];

        $quickRoute = route('company.payroll-employees.update-record', $this->employeeA->id);
        $responseInc = $this->actingAs($this->adminCompanyA)->postJson($quickRoute, $payloadInc);
        $responseInc->assertStatus(422);
        $responseInc->assertJsonPath('message', 'Income assignment ID does not match the specified income type.');

        $deductionType2 = DeductionType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company A Health Fee',
            'code' => 'HEALTH_A',
            'type' => 'other',
            'is_active' => true,
        ]);

        $empDec = EmpDeduction::create([
            'employee_id' => $this->employeeA->id,
            'deduction_type_id' => $this->deductionTypeA->id,
            'amount' => 200.00,
            'rate' => 200.00,
            'is_active' => true,
        ]);

        // Attempt to submit empDec->id with deductionType2->id
        $payloadDec = [
            'first_name' => $this->employeeA->first_name,
            'last_name' => $this->employeeA->last_name,
            'status' => 'active',
            'deductions' => [
                ['id' => $empDec->id, 'deduction_type_id' => $deductionType2->id, 'amount' => 300.00],
            ],
        ];

        $responseDec = $this->actingAs($this->adminCompanyA)->postJson($quickRoute, $payloadDec);
        $responseDec->assertStatus(422);
        $responseDec->assertJsonPath('message', 'Deduction assignment ID does not match the specified deduction type.');
    }
}
