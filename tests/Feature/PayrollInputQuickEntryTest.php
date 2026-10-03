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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollInputQuickEntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $companyUserB;
    protected Company $companyA;
    protected Company $companyB;
    protected PayrollPeriod $periodA;
    protected PayrollPeriod $periodB;
    protected EmployeeMaster $employeeA1;
    protected EmployeeMaster $employeeA2;
    protected EmployeeMaster $employeeNoDraft;
    protected EmployeeMaster $employeeB;
    protected PayHeader $draftA1;
    protected PayHeader $draftA2;
    protected PayHeader $draftB;
    protected IncomeType $incomeTypeRegular;
    protected DeductionType $deductionTypeRegular;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin User A',
            'email' => 'admin_a@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->companyUserB = User::create([
            'name' => 'User B',
            'email' => 'user_b@example.com',
            'password' => bcrypt('password'),
            'role' => 'company',
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company A',
            'company_code' => 'COMP-A',
            'email' => 'comp_a@example.com',
            'status' => '1',
        ]);

        $this->companyB = Company::create([
            'user_id' => $this->companyUserB->id,
            'name' => 'Company B',
            'company_code' => 'COMP-B',
            'email' => 'comp_b@example.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->companyA->id]);
        $this->companyUserB->update(['company_id' => $this->companyB->id]);

        $this->periodA = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'Period A 2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $this->periodB = PayrollPeriod::create([
            'company_id' => $this->companyB->id,
            'period_name' => 'Period B 2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $this->employeeA1 = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'employee_code' => 'EMP-A1',
            'monthly_rate' => 30000.00,
            'employment_status' => 'regular',
        ]);

        $this->employeeA2 = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'employee_code' => 'EMP-A2',
            'monthly_rate' => 35000.00,
            'employment_status' => 'regular',
        ]);

        $this->employeeNoDraft = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'first_name' => 'NoDraft',
            'last_name' => 'User',
            'employee_code' => 'EMP-NODRAFT',
            'monthly_rate' => 20000.00,
            'employment_status' => 'regular',
        ]);

        $this->employeeB = EmployeeMaster::create([
            'company_id' => $this->companyB->id,
            'first_name' => 'Bob',
            'last_name' => 'CrossCompany',
            'employee_code' => 'EMP-B',
            'monthly_rate' => 40000.00,
            'employment_status' => 'regular',
        ]);

        $this->draftA1 = PayHeader::create([
            'company_id' => $this->companyA->id,
            'employee_id' => $this->employeeA1->id,
            'payroll_period_id' => $this->periodA->id,
            'status' => 'draft',
            'days_worked' => 2.00,
            'days_absent' => 0.00,
            'overtime_hours' => 0.00,
            'late_hours' => 0.00,
            'total_gross_pay' => 1000.00,
            'total_deductions' => 100.00,
            'net_pay' => 900.00,
        ]);

        $this->draftA2 = PayHeader::create([
            'company_id' => $this->companyA->id,
            'employee_id' => $this->employeeA2->id,
            'payroll_period_id' => $this->periodA->id,
            'status' => 'draft',
            'days_worked' => 3.00,
            'days_absent' => 0.00,
            'overtime_hours' => 0.00,
            'late_hours' => 0.00,
            'total_gross_pay' => 1500.00,
            'total_deductions' => 200.00,
            'net_pay' => 1300.00,
        ]);

        $this->draftB = PayHeader::create([
            'company_id' => $this->companyB->id,
            'employee_id' => $this->employeeB->id,
            'payroll_period_id' => $this->periodB->id,
            'status' => 'draft',
            'days_worked' => 4.00,
            'days_absent' => 0.00,
            'overtime_hours' => 0.00,
            'late_hours' => 0.00,
            'total_gross_pay' => 2000.00,
            'total_deductions' => 300.00,
            'net_pay' => 1700.00,
        ]);

        $this->incomeTypeRegular = IncomeType::firstOrCreate([
            'code' => 'BASIC',
        ], [
            'company_id' => null,
            'name' => 'Basic Pay',
            'is_active' => true,
        ]);

        $this->deductionTypeRegular = DeductionType::firstOrCreate([
            'code' => 'TAX',
        ], [
            'company_id' => null,
            'name' => 'Withholding Tax',
            'type' => 'statutory',
            'is_active' => true,
        ]);

        PayIn::create([
            'pay_id' => $this->draftA1->id,
            'income_type_id' => $this->incomeTypeRegular->id,
            'number_input' => 2.00,
            'rate' => 500.00,
            'amount' => 1000.00,
        ]);

        PayOut::create([
            'pay_id' => $this->draftA1->id,
            'deduction_type_id' => $this->deductionTypeRegular->id,
            'number_input' => 1.00,
            'rate' => 100.00,
            'amount' => 100.00,
        ]);

        PayIn::create([
            'pay_id' => $this->draftA2->id,
            'income_type_id' => $this->incomeTypeRegular->id,
            'number_input' => 3.00,
            'rate' => 500.00,
            'amount' => 1500.00,
        ]);

        PayOut::create([
            'pay_id' => $this->draftA2->id,
            'deduction_type_id' => $this->deductionTypeRegular->id,
            'number_input' => 1.00,
            'rate' => 200.00,
            'amount' => 200.00,
        ]);

        EmpIncome::create([
            'employee_id' => $this->employeeA1->id,
            'income_type_id' => $this->incomeTypeRegular->id,
            'amount' => 888.00,
            'is_active' => true,
        ]);

        EmpDeduction::create([
            'employee_id' => $this->employeeA1->id,
            'deduction_type_id' => $this->deductionTypeRegular->id,
            'amount' => 77.00,
            'is_active' => true,
        ]);
    }

    public function test_only_employees_with_drafts_for_selected_period_appear()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA1->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('Jane Smith');
    }

    public function test_employees_without_draft_do_not_appear()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA1->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertDontSee('EMP-NODRAFT');
    }

    public function test_switching_employees_keeps_selected_payroll_period()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA2->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertSee('Period A 2026');
        $response->assertSee('Jane Smith');
    }

    public function test_selecting_employee_loads_correct_pay_header()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA2->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertSee('EMP-A2');
    }

    public function test_income_grid_loads_rows_from_pay_in()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA1->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertSee('Basic Pay');
        $response->assertSee('500.00');
    }

    public function test_deduction_grid_loads_rows_from_pay_out()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA1->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertSee('Withholding Tax');
        $response->assertSee('100.00');
    }

    public function test_pay_in_number_input_is_editable()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA1->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $payIn = $this->draftA1->payIns->first();
        $response->assertSee('name="pay_ins[' . $payIn->id . '][number_input]"', false);
    }

    public function test_pay_in_rate_is_read_only()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA1->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertSee('Rate (Fixed)');
        $payIn = $this->draftA1->payIns->first();
        $response->assertDontSee('name="pay_ins[' . $payIn->id . '][rate]"', false);
    }

    public function test_calculated_income_total_is_read_only()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA1->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertSee('Total (Calculated)');
        $payIn = $this->draftA1->payIns->first();
        $response->assertDontSee('name="pay_ins[' . $payIn->id . '][amount]"', false);
    }

    public function test_changing_number_input_recalculates_amount()
    {
        $payIn = $this->draftA1->payIns->first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 4.00,
                    ]
                ]
            ]);

        $response->assertRedirect();
        $payIn->refresh();
        $this->assertEquals(4.00, (float) $payIn->number_input);
        $this->assertEquals(2000.00, (float) $payIn->amount);
    }

    public function test_saving_updates_pay_in_number_input()
    {
        $payIn = $this->draftA1->payIns->first();

        $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 8.00,
                    ]
                ]
            ]);

        $this->assertDatabaseHas('pay_ins', [
            'id' => $payIn->id,
            'number_input' => 8.00,
        ]);
    }

    public function test_saving_does_not_change_pay_in_rate()
    {
        $payIn = $this->draftA1->payIns->first();

        $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 8.00,
                        'rate' => 9999.00, // Should be ignored
                    ]
                ]
            ]);

        $payIn->refresh();
        $this->assertEquals(500.00, (float) $payIn->rate);
    }

    public function test_saving_does_not_accept_submitted_total_as_authoritative()
    {
        $payIn = $this->draftA1->payIns->first();

        $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 2.00,
                        'amount' => 99999.00, // Should be ignored; calculated from 2 * 500 = 1000
                    ]
                ]
            ]);

        $payIn->refresh();
        $this->assertEquals(1000.00, (float) $payIn->amount);
    }

    public function test_saving_does_not_modify_emp_income()
    {
        $payIn = $this->draftA1->payIns->first();

        $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 10.00,
                    ]
                ]
            ]);

        $empIncome = EmpIncome::where('employee_id', $this->employeeA1->id)->first();
        $this->assertEquals(888.00, (float) $empIncome->amount);
    }

    public function test_saving_does_not_modify_emp_deduction()
    {
        $payIn = $this->draftA1->payIns->first();

        $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 10.00,
                    ]
                ]
            ]);

        $empDeduction = EmpDeduction::where('employee_id', $this->employeeA1->id)->first();
        $this->assertEquals(77.00, (float) $empDeduction->amount);
    }

    public function test_approved_payroll_cannot_be_edited_through_ui()
    {
        $this->draftA1->update(['status' => 'approved']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.edit', ['id' => $this->draftA1->id, 'payroll_period_id' => $this->periodA->id]));

        $response->assertStatus(200);
        $response->assertSee('Payroll draft is locked');
        $response->assertSee('disabled', false);
    }

    public function test_approved_payroll_cannot_be_edited_through_direct_request()
    {
        $this->draftA1->update(['status' => 'approved']);
        $payIn = $this->draftA1->payIns->first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 10.00,
                    ]
                ]
            ]);

        $response->assertSessionHas('statusDanger');
        $payIn->refresh();
        $this->assertEquals(2.00, (float) $payIn->number_input);
    }

    public function test_pay_in_belonging_to_another_pay_header_is_rejected()
    {
        $payInA2 = $this->draftA2->payIns->first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payInA2->id,
                        'number_input' => 10.00,
                    ]
                ]
            ]);

        $response->assertSessionHas('statusDanger');
    }

    public function test_pay_out_belonging_to_another_pay_header_is_rejected()
    {
        $payOutA2 = $this->draftA2->payOuts->first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_outs' => [
                    [
                        'id' => $payOutA2->id,
                        'amount' => 500.00,
                    ]
                ]
            ]);

        $response->assertSessionHas('statusDanger');
    }

    public function test_cross_company_payroll_draft_access_is_rejected()
    {
        $companyAdminB = User::create([
            'name' => 'Company Admin B',
            'email' => 'company_b_admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'company',
            'company_id' => $this->companyB->id,
        ]);

        $response = $this->actingAs($companyAdminB)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
            ]);

        $response->assertStatus(403);
    }

    public function test_negative_net_pay_remains_blocked()
    {
        $payIn = $this->draftA1->payIns->first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 0,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 0.00, // 0 gross pay - 100 deduction = -100 net pay
                    ]
                ]
            ]);

        $response->assertSessionHas('statusDanger');
        $payIn->refresh();
        $this->assertEquals(2.00, (float) $payIn->number_input);
    }

    public function test_after_saving_same_period_and_employee_remain_selected()
    {
        $payIn = $this->draftA1->payIns->first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('payroll-drafts.update', $this->draftA1->id), [
                'days_worked' => 5,
                'pay_ins' => [
                    [
                        'id' => $payIn->id,
                        'number_input' => 5.00,
                    ]
                ]
            ]);

        $response->assertRedirect(route('payroll-drafts.edit', [
            'id' => $this->draftA1->id,
            'payroll_period_id' => $this->periodA->id,
        ]));
    }
}
