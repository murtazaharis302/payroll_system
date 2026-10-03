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

class ReportFilenameSanitizationTest extends TestCase
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

    public function test_income_payroll_history_report_sanitizes_slashes_in_filename(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Sanitize Comp',
            'company_code' => 'SNC',
            'email' => 'snc@test.com',
            'status' => 1,
        ]);

        $period1 = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '01/01/2026 to 01/15/2026',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'status' => 'completed',
        ]);

        $period2 = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '01/16/2026 to 01/31/2026',
            'period_start' => '2026-01-16',
            'period_end' => '2026-01-31',
            'status' => 'completed',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-SNC-01',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'monthly_rate' => 35000.00,
            'status' => 'active',
        ]);

        PayHeader::create([
            'payroll_period_id' => $period1->id,
            'employee_id' => $employee->id,
            'gross_income' => 17500.00,
            'total_deductions' => 2000.00,
            'net_pay' => 15500.00,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->post(panelRoute('reports.income-payroll-history'), [
                'from_period_id' => $period1->id,
                'to_period_id' => $period2->id,
            ]);

        $response->assertStatus(200);
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertStringNotContainsString('\\', $disposition);
    }

    public function test_deduction_payroll_history_report_sanitizes_slashes_in_filename(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Sanitize Comp 2',
            'company_code' => 'SNC2',
            'email' => 'snc2@test.com',
            'status' => 1,
        ]);

        $period1 = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '02/01/2026 to 02/15/2026',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-15',
            'status' => 'completed',
        ]);

        $period2 = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '02/16/2026 to 02/28/2026',
            'period_start' => '2026-02-16',
            'period_end' => '2026-02-28',
            'status' => 'completed',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-SNC-02',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'monthly_rate' => 35000.00,
            'status' => 'active',
        ]);

        PayHeader::create([
            'payroll_period_id' => $period1->id,
            'employee_id' => $employee->id,
            'gross_income' => 17500.00,
            'total_deductions' => 2000.00,
            'net_pay' => 15500.00,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->post(panelRoute('reports.deduction-payroll-history'), [
                'from_period_id' => $period1->id,
                'to_period_id' => $period2->id,
            ]);

        $response->assertStatus(200);
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertStringNotContainsString('\\', $disposition);
    }
}
