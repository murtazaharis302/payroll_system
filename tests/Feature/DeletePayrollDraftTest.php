<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletePayrollDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);
    }

    public function test_can_delete_single_payroll_draft(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Delete Test Comp',
            'company_code' => 'DTC',
            'email' => 'dtc@test.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-DTC-01',
            'first_name' => 'Mark',
            'last_name' => 'Delete',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Delete Period',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'status' => 'open',
        ]);

        $header = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_income' => 15000.00,
            'total_deductions' => 1500.00,
            'net_pay' => 13500.00,
            'status' => 'draft',
        ]);

        $incomeType = \App\Models\IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Pay', 'category' => 'basic']);
        $deductionType = \App\Models\DeductionType::firstOrCreate(['code' => 'SSS'], ['name' => 'SSS Contribution', 'category' => 'statutory']);

        PayIn::create([
            'pay_id' => $header->id,
            'income_type_id' => $incomeType->id,
            'number_input' => 1.00,
            'rate' => 15000.00,
        ]);

        PayOut::create([
            'pay_id' => $header->id,
            'deduction_type_id' => $deductionType->id,
            'number_input' => 1.00,
            'rate' => 1500.00,
        ]);

        GovernmentContribution::create([
            'type' => 'sss',
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'amount_ee' => 500.00,
            'amount_er' => 500.00,
        ]);

        $response = $this->actingAs($admin)
            ->delete(panelRoute('payroll-drafts.destroy', $header->id));

        $response->assertRedirect(route('payroll-drafts.index'));
        $response->assertSessionHas('status', 'Payroll draft deleted successfully.');

        $this->assertDatabaseMissing('pay_headers', ['id' => $header->id]);
        $this->assertDatabaseMissing('pay_ins', ['pay_id' => $header->id]);
        $this->assertDatabaseMissing('pay_outs', ['pay_id' => $header->id]);
        $this->assertDatabaseMissing('government_contributions', [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_cannot_delete_approved_payroll_draft(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Approved Test Comp',
            'company_code' => 'ATC',
            'email' => 'atc@test.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-ATC-01',
            'first_name' => 'Jane',
            'last_name' => 'Approved',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Approved Period',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'status' => 'open',
        ]);

        $header = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_income' => 15000.00,
            'total_deductions' => 1500.00,
            'net_pay' => 13500.00,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->delete(panelRoute('payroll-drafts.destroy', $header->id));

        $response->assertSessionHas('statusDanger', 'Only draft or rejected payroll records can be deleted.');
        $this->assertDatabaseHas('pay_headers', ['id' => $header->id]);
    }

    public function test_can_batch_delete_payroll_drafts(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Batch Comp',
            'company_code' => 'BTC',
            'email' => 'btc@test.com',
            'status' => 1,
        ]);

        $emp1 = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-BTC-01',
            'first_name' => 'Emp',
            'last_name' => 'One',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $emp2 = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-BTC-02',
            'first_name' => 'Emp',
            'last_name' => 'Two',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Batch Period',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'status' => 'open',
        ]);

        $header1 = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $emp1->id,
            'gross_income' => 10000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 9000.00,
            'status' => 'draft',
        ]);

        $header2 = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $emp2->id,
            'gross_income' => 12500.00,
            'total_deductions' => 1200.00,
            'net_pay' => 11300.00,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)
            ->post(panelRoute('payroll-drafts.batch-destroy'), [
                'draft_ids' => [$header1->id, $header2->id],
            ]);

        $response->assertRedirect(route('payroll-drafts.index'));
        $response->assertSessionHas('status', 'Deleted 2 payroll draft(s) successfully.');

        $this->assertDatabaseMissing('pay_headers', ['id' => $header1->id]);
        $this->assertDatabaseMissing('pay_headers', ['id' => $header2->id]);
    }
}
