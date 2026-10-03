<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HRRateRestrictionUiAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;
    protected User $companyAdmin;
    protected Company $company;
    protected PayrollPeriod $period;
    protected EmployeeMaster $employee;
    protected PayHeader $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $adminUser = User::create([
            'name' => 'Owner System',
            'email' => 'ownersys@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'HR Audit Company',
            'email' => 'hraudit@test.com',
            'status' => '1',
        ]);

        $this->companyAdmin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Admin User',
            'email' => 'adminuser@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->hrUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'HR User',
            'email' => 'hruser@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-08-A',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-HR-UI-01',
            'first_name' => 'Alice',
            'last_name' => 'Audit',
            'date_hired' => '2026-01-01',
            'position' => 'Staff',
            'monthly_rate' => 35000.00,
            'daily_rate' => 1590.91,
            'hourly_rate' => 198.86,
            'status' => 'active',
        ]);

        $this->draft = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'days_worked' => 10.0,
            'total_gross_pay' => 15909.10,
            'total_deductions' => 500.00,
            'net_pay' => 15409.10,
            'status' => 'draft',
        ]);
    }

    public function test_hr_views_have_no_rate_columns_or_placeholder_badges(): void
    {
        // 1. Index View for HR
        $indexResponse = $this->actingAs($this->hrUser)->get(route('payroll-drafts.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('***');
        $indexResponse->assertDontSee('Gross Pay');
        $indexResponse->assertDontSee('Net Pay');
        $indexResponse->assertSee('Days');
        $indexResponse->assertSee('Deductions');

        // 2. Create View for HR
        $createResponse = $this->actingAs($this->hrUser)->get(route('payroll-drafts.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertDontSee('Income Configuration & Source Mode');
        $createResponse->assertDontSee('Deduction Configuration');

        // 3. Edit View for HR
        $editResponse = $this->actingAs($this->hrUser)->get(route('payroll-drafts.edit', $this->draft->id));
        $editResponse->assertStatus(200);
        $editResponse->assertDontSee('***');
        $editResponse->assertDontSee('Income / Earnings');
        $editResponse->assertDontSee('Payroll Summary');
        $editResponse->assertDontSee('Employee income and rate details are restricted');
        $editResponse->assertSee('Days Worked');
        $editResponse->assertSee('Overtime Hours');
        $editResponse->assertSee('Deductions');

        // 4. Company Master is not an HR-accessible employee-management view.
        $masterResponse = $this->actingAs($this->hrUser)->get(route('payroll-employees.company-master'));
        $masterResponse->assertStatus(403);
    }



    public function test_company_admin_views_retain_full_rates_and_payroll_summaries(): void
    {
        $indexResponse = $this->actingAs($this->companyAdmin)->get(route('payroll-drafts.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Gross Pay');
        $indexResponse->assertSee('Net Pay');

        $editResponse = $this->actingAs($this->companyAdmin)->get(route('payroll-drafts.edit', $this->draft->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Income / Earnings');
        $editResponse->assertSee('Payroll Summary');
        $editResponse->assertSee('Gross Pay');
        $editResponse->assertSee('Net Pay');
    }
}
