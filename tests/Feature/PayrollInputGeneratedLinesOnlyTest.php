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

class PayrollInputGeneratedLinesOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $adminCompanyA;
    protected User $adminCompanyB;
    protected User $hrCompanyA;
    protected EmployeeMaster $employeeA;
    protected EmployeeMaster $employeeB;
    protected PayrollPeriod $periodA;
    protected PayrollPeriod $periodB;
    protected PayHeader $draftA;
    protected PayHeader $draftB;
    protected IncomeType $incomeTypeBasic;
    protected DeductionType $deductionTypeSss;
    protected PayIn $payInBasicA;
    protected PayOut $payOutSssA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminCompanyA = User::create([
            'name' => 'Admin Company A',
            'email' => 'admin_a@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->adminCompanyA->id,
            'name' => 'Company A',
            'email' => 'comp_a@test.com',
            'status' => '1',
        ]);
        $this->adminCompanyA->update(['company_id' => $this->companyA->id]);

        $this->adminCompanyB = User::create([
            'name' => 'Admin Company B',
            'email' => 'admin_b@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->companyB = Company::create([
            'user_id' => $this->adminCompanyB->id,
            'name' => 'Company B',
            'email' => 'comp_b@test.com',
            'status' => '1',
        ]);
        $this->adminCompanyB->update(['company_id' => $this->companyB->id]);

        $this->hrCompanyA = User::create([
            'company_id' => $this->companyA->id,
            'name' => 'HR Company A',
            'email' => 'hr_a@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $this->employeeA = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'employee_code' => 'EMP-A-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active',
            'monthly_rate' => 20000.00,
            'daily_rate' => 800.00,
            'hourly_rate' => 100.00,
        ]);

        $this->employeeB = EmployeeMaster::create([
            'company_id' => $this->companyB->id,
            'employee_code' => 'EMP-B-001',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status' => 'active',
            'monthly_rate' => 25000.00,
            'daily_rate' => 1000.00,
            'hourly_rate' => 125.00,
        ]);

        $this->periodA = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'Period A 2026-08',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $this->periodB = PayrollPeriod::create([
            'company_id' => $this->companyB->id,
            'period_name' => 'Period B 2026-08',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $this->draftA = PayHeader::create([
            'company_id' => $this->companyA->id,
            'employee_id' => $this->employeeA->id,
            'payroll_period_id' => $this->periodA->id,
            'status' => 'draft',
            'days_worked' => 10.0,
            'total_gross_pay' => 8000.00,
            'total_deductions' => 500.00,
            'net_pay' => 7500.00,
        ]);

        $this->draftB = PayHeader::create([
            'company_id' => $this->companyB->id,
            'employee_id' => $this->employeeB->id,
            'payroll_period_id' => $this->periodB->id,
            'status' => 'draft',
            'days_worked' => 10.0,
            'total_gross_pay' => 10000.00,
            'total_deductions' => 600.00,
            'net_pay' => 9400.00,
        ]);

        $this->incomeTypeBasic = IncomeType::firstOrCreate(
            ['code' => 'BASIC'],
            [
                'company_id' => $this->companyA->id,
                'name' => 'Basic Salary',
                'type' => 'taxable',
                'is_active' => true,
            ]
        );

        $this->deductionTypeSss = DeductionType::firstOrCreate(
            ['code' => 'SSS'],
            [
                'company_id' => $this->companyA->id,
                'name' => 'SSS Contribution',
                'type' => 'statutory',
                'is_active' => true,
            ]
        );

        $this->payInBasicA = PayIn::create([
            'pay_id' => $this->draftA->id,
            'income_type_id' => $this->incomeTypeBasic->id,
            'number_input' => 10.00,
            'rate' => 800.00,
        ]);

        $this->payOutSssA = PayOut::create([
            'pay_id' => $this->draftA->id,
            'deduction_type_id' => $this->deductionTypeSss->id,
            'number_input' => 1.00,
            'rate' => 500.00,
        ]);
    }

    /**
     * Test 1: The draft exposes separate add actions for active catalog types
     * that are not already represented by a generated line.
     */
    public function test_payroll_input_ui_shows_add_actions_for_available_catalog_lines(): void
    {
        $response = $this->actingAs($this->adminCompanyA)->get(route('payroll-drafts.edit', $this->draftA->id));

        $response->assertStatus(200);
        $response->assertSee('Add Income');
        $response->assertSee('Add Deduction');
        $response->assertSee('add_income_type_id');
        $response->assertSee('add_deduction_type_id');
    }

    /**
     * Test 2: Existing generated PayIn can be updated by authorized user.
     */
    public function test_existing_generated_payin_can_be_updated_by_authorized_user(): void
    {
        $payload = [
            'days_worked' => 12.0,
            'pay_ins' => [
                [
                    'id' => $this->payInBasicA->id,
                    'number_input' => 12.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(200);
        $this->payInBasicA->refresh();
        $this->assertEquals(12.00, (float) $this->payInBasicA->number_input);
    }

    /**
     * Test 3: Existing generated PayOut can be updated where permitted.
     */
    public function test_existing_generated_payout_can_be_updated_where_permitted(): void
    {
        $payload = [
            'days_worked' => 10.0,
            'pay_outs' => [
                [
                    'id' => $this->payOutSssA->id,
                    'number_input' => 2.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(200);
        $this->payOutSssA->refresh();
        $this->assertEquals(2.00, (float) $this->payOutSssA->number_input);
    }

    /**
     * Test 4: Request without a line ID cannot create a row (422).
     */
    public function test_request_without_line_id_cannot_create_a_row(): void
    {
        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                [
                    'number_input' => 5.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Every submitted income line item must have an existing line ID.');
    }

    /**
     * Test 5: Submitting income_type_id key is rejected (422).
     */
    public function test_submitting_income_type_id_key_is_rejected(): void
    {
        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                [
                    'id' => $this->payInBasicA->id,
                    'income_type_id' => $this->incomeTypeBasic->id,
                    'number_input' => 10.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Submitting income_type_id or deduction_type_id in payroll input is not allowed.');
    }

    /**
     * Test 6: Submitting deduction_type_id key is rejected (422).
     */
    public function test_submitting_deduction_type_id_key_is_rejected(): void
    {
        $payload = [
            'days_worked' => 10.0,
            'pay_outs' => [
                [
                    'id' => $this->payOutSssA->id,
                    'deduction_type_id' => $this->deductionTypeSss->id,
                    'number_input' => 1.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Submitting income_type_id or deduction_type_id in payroll input is not allowed.');
    }

    /**
     * Test 7: PayIn from another PayHeader is rejected (422).
     */
    public function test_payin_from_another_payheader_is_rejected(): void
    {
        $payInB = PayIn::create([
            'pay_id' => $this->draftB->id,
            'income_type_id' => $this->incomeTypeBasic->id,
            'number_input' => 10.00,
            'rate' => 1000.00,
        ]);

        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                [
                    'id' => $payInB->id,
                    'number_input' => 15.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Submitted PayIn line item does not belong to this payroll draft.');
    }

    /**
     * Test 8: PayOut from another PayHeader is rejected (422).
     */
    public function test_payout_from_another_payheader_is_rejected(): void
    {
        $payOutB = PayOut::create([
            'pay_id' => $this->draftB->id,
            'deduction_type_id' => $this->deductionTypeSss->id,
            'number_input' => 1.00,
            'rate' => 600.00,
        ]);

        $payload = [
            'days_worked' => 10.0,
            'pay_outs' => [
                [
                    'id' => $payOutB->id,
                    'number_input' => 2.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Submitted PayOut line item does not belong to this payroll draft.');
    }

    /**
     * Test 9: Line from another employee or company is rejected (422).
     */
    public function test_line_from_another_employee_or_company_is_rejected(): void
    {
        $payInB = PayIn::create([
            'pay_id' => $this->draftB->id,
            'income_type_id' => $this->incomeTypeBasic->id,
            'number_input' => 10.00,
            'rate' => 1000.00,
        ]);

        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                ['id' => $payInB->id, 'number_input' => 20.00],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(422);
    }

    /**
     * Test 10: Omitted generated lines remain unchanged.
     */
    public function test_omitted_generated_lines_remain_unchanged(): void
    {
        $allowanceType = IncomeType::firstOrCreate(
            ['code' => 'ALLOWANCE'],
            [
                'company_id' => $this->companyA->id,
                'name' => 'Allowance',
                'type' => 'non_taxable',
                'is_active' => true,
            ]
        );

        $payInAllowance = PayIn::create([
            'pay_id' => $this->draftA->id,
            'income_type_id' => $allowanceType->id,
            'number_input' => 1.00,
            'rate' => 2000.00,
        ]);

        // Submit payload containing ONLY payInBasicA, omitting payInAllowance
        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                [
                    'id' => $this->payInBasicA->id,
                    'number_input' => 11.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(200);

        // Omitted payInAllowance remains completely unchanged
        $payInAllowance->refresh();
        $this->assertEquals(1.00, (float) $payInAllowance->number_input);
        $this->assertEquals(2000.00, (float) $payInAllowance->rate);
    }

    /**
     * Test 11: A request containing one valid and one forged line makes zero changes (atomic rollback).
     */
    public function test_request_containing_one_valid_and_one_forged_line_makes_zero_changes(): void
    {
        $originalNumberInput = $this->payInBasicA->number_input;

        $payInB = PayIn::create([
            'pay_id' => $this->draftB->id,
            'income_type_id' => $this->incomeTypeBasic->id,
            'number_input' => 10.00,
            'rate' => 1000.00,
        ]);

        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                ['id' => $this->payInBasicA->id, 'number_input' => 99.00], // Valid
                ['id' => $payInB->id, 'number_input' => 99.00],            // Forged
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(422);

        // Verify valid line was NOT updated due to complete rollback
        $this->payInBasicA->refresh();
        $this->assertEquals($originalNumberInput, $this->payInBasicA->number_input);
    }

    /**
     * Test 12: Approved and locked drafts reject direct requests (422).
     */
    public function test_approved_and_locked_drafts_reject_direct_requests(): void
    {
        $this->draftA->update(['status' => 'approved']);

        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                ['id' => $this->payInBasicA->id, 'number_input' => 12.00],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This payroll record is locked and cannot be edited.');
    }

    /**
     * Test 13: HR cannot submit rates or protected compensation fields (403).
     */
    public function test_hr_cannot_submit_rates_or_protected_compensation_fields(): void
    {
        $payload = [
            'days_worked' => 10.0,
            'pay_ins' => [
                ['id' => $this->payInBasicA->id, 'number_input' => 12.00],
            ],
        ];

        $response = $this->actingAs($this->hrCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(403);
    }

    /**
     * Test 14: Employee defaults remain unchanged after Payroll Input updates.
     */
    public function test_employee_defaults_remain_unchanged(): void
    {
        $empIncome = EmpIncome::create([
            'employee_id' => $this->employeeA->id,
            'income_type_id' => $this->incomeTypeBasic->id,
            'amount' => 20000.00,
            'rate' => 20000.00,
            'is_active' => true,
        ]);

        $empDeduction = EmpDeduction::create([
            'employee_id' => $this->employeeA->id,
            'deduction_type_id' => $this->deductionTypeSss->id,
            'amount' => 500.00,
            'rate' => 500.00,
            'is_active' => true,
        ]);

        $payload = [
            'days_worked' => 12.0,
            'pay_ins' => [
                ['id' => $this->payInBasicA->id, 'number_input' => 12.00],
            ],
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(200);

        // Employee master defaults remain unchanged
        $empIncome->refresh();
        $empDeduction->refresh();

        $this->assertEquals(20000.00, (float) $empIncome->amount);
        $this->assertEquals(500.00, (float) $empDeduction->amount);
    }

    /**
     * Test 15: Updating Payroll Input cannot create new PayIn or PayOut rows via statutory or custom fields.
     */
    public function test_updating_payroll_input_cannot_create_new_payin_or_payout_rows_via_custom_or_statutory_fields(): void
    {
        $initialPayInCount = PayIn::where('pay_id', $this->draftA->id)->count();
        $initialPayOutCount = PayOut::where('pay_id', $this->draftA->id)->count();

        $payload = [
            'days_worked' => 10.0,
            'philhealth_contribution' => 400.00,
            'pagibig_contribution' => 200.00,
            'withholding_tax' => 1000.00,
        ];

        $response = $this->actingAs($this->adminCompanyA)->putJson(route('payroll-drafts.update', $this->draftA->id), $payload);

        $response->assertStatus(200);

        // PayIn and PayOut row counts for draftA must remain identical (zero new rows created)
        $this->assertEquals($initialPayInCount, PayIn::where('pay_id', $this->draftA->id)->count());
        $this->assertEquals($initialPayOutCount, PayOut::where('pay_id', $this->draftA->id)->count());
    }
}
