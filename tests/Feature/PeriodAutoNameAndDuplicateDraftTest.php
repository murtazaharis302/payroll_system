<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodAutoNameAndDuplicateDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        foreach (['BASIC', 'OVERTIME', 'ALLOWANCE', 'BONUS', 'OTHER'] as $code) {
            \App\Models\IncomeType::firstOrCreate(['code' => $code], ['name' => $code, 'description' => $code, 'is_taxable' => false]);
        }
        foreach (['SSS', 'PHILHEALTH', 'PAGIBIG', 'TAX', 'SSS_LOAN', 'PAGIBIG_LOAN', 'VALE', 'GOODS', 'TARDINESS', 'ABSENCES', 'UNDERTIME', 'OTHER'] as $code) {
            \App\Models\DeductionType::firstOrCreate(['code' => $code], ['name' => $code, 'description' => $code]);
        }
    }

    public function test_period_name_is_auto_generated_from_date_range_if_empty(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Test Auto Name Co',
            'company_code' => 'TAN-001',
            'email' => 'tan@test.com',
            'status' => 1,
        ]);

        $this->actingAs($admin);

        $response = $this->post(panelRoute('payroll-periods.store'), [
            'company_id' => $company->id,
            'period_start' => '2026-07-15',
            'period_end' => '2026-07-31',
            'pay_date' => '2026-08-05',
            'status' => 'open',
        ]);

        $response->assertRedirect(panelRoute('payroll-periods.index'));

        $this->assertDatabaseHas('payroll_periods', [
            'company_id' => $company->id,
            'period_name' => '07/15/2026 to 07/31/2026',
        ]);
    }

    public function test_duplicate_draft_generation_redirects_to_existing_draft(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Dup Draft Co',
            'company_code' => 'DDC-001',
            'email' => 'ddc@test.com',
            'status' => 1,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '07/01/2026 to 07/15/2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
            'status' => 'open',
        ]);

        $emp = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-DUP-01',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'janesmith@test.com',
            'monthly_rate' => 25000,
            'date_hired' => '2026-01-01',
            'status' => 'active',
        ]);

        $draft = PayHeader::create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $emp->id,
            'status' => 'draft',
            'days_worked' => 11,
            'basic_pay' => 12500,
            'gross_pay' => 12500,
            'net_pay' => 12500,
        ]);

        $this->actingAs($admin);

        // Access create page for period with existing draft -> should redirect to edit
        $createResponse = $this->get(route('payroll-drafts.create', ['payroll_period_id' => $period->id]));
        $createResponse->assertRedirect(route('payroll-drafts.edit', $draft->id));

        // Submit generate form for period with existing draft -> should redirect to edit
        $generateResponse = $this->post(route('payroll-drafts.generate'), [
            'payroll_period_id' => $period->id,
        ]);
        $generateResponse->assertRedirect(route('payroll-drafts.edit', $draft->id));
    }
}
