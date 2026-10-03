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

class NegativeNetPayValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
    }

    public function test_cannot_save_payroll_draft_with_negative_net_pay()
    {
        $adminUser = User::create([
            'name' => 'Admin Negative Test',
            'email' => 'admin_neg@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Company Neg Test',
            'email' => 'comp_neg@test.com',
            'status' => '1',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-NEG-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_hired' => '2026-01-01',
            'monthly_rate' => 10000.00,
            'daily_rate' => 384.62,
            'hourly_rate' => 48.08,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-01-A',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'payout_date' => '2026-01-20',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'days_worked' => 10.0,
            'total_gross_pay' => 3846.20,
            'total_deductions' => 1000.00,
            'net_pay' => 2846.20,
            'status' => 'draft',
        ]);

        // Attempt update with deductions exceeding gross pay (Gross = 1,000, Deductions = 5,000 -> Net = -4,000)
        $response = $this->actingAs($adminUser)
            ->withSession(['active_company_id' => $company->id])
            ->from(route('payroll-drafts.edit', $draft->id))
            ->put(route('payroll-drafts.update', $draft->id), [
                'days_worked' => 1,
                'basic_pay' => 1000.00,
                'overtime_pay' => 0.00,
                'total_allowances' => 0.00,
                'total_bonuses' => 0.00,
                'other_income' => 0.00,
                'sss_contribution' => 1000.00,
                'philhealth_contribution' => 1000.00,
                'pagibig_contribution' => 1000.00,
                'withholding_tax' => 1000.00,
                'vale_loan_deduction' => 1000.00,
            ]);

        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('negative net income', session('statusDanger'));

        $draft->refresh();
        $this->assertGreaterThanOrEqual(0, (float) $draft->net_pay);
    }

    public function test_cannot_approve_payroll_draft_with_negative_net_pay()
    {
        $adminUser = User::create([
            'name' => 'Owner Approval Test',
            'email' => 'owner_appr@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Company Appr Test',
            'email' => 'comp_appr@test.com',
            'status' => '1',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-NEG-002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_hired' => '2026-01-01',
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-01-B',
            'period_start' => '2026-01-16',
            'period_end' => '2026-01-31',
            'payout_date' => '2026-02-05',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'days_worked' => 5.0,
            'total_gross_pay' => 2000.00,
            'total_deductions' => 3500.00,
            'net_pay' => -1500.00,
            'status' => 'pending_approval',
            'posted_at' => now(),
        ]);

        $response = $this->actingAs($adminUser)
            ->withSession(['active_company_id' => $company->id])
            ->from(route('payroll-approvals.index'))
            ->post(route('payroll-approvals.approve', $draft->id));

        $response->assertSessionHas('statusDanger');
        $this->assertStringContainsString('negative net income', session('statusDanger'));

        $draft->refresh();
        $this->assertEquals('pending_approval', $draft->status);
    }

    public function test_positive_net_pay_saves_successfully()
    {
        $adminUser = User::create([
            'name' => 'Admin Pos Test',
            'email' => 'admin_pos@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Company Pos Test',
            'email' => 'comp_pos@test.com',
            'status' => '1',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-POS-003',
            'first_name' => 'Alice',
            'last_name' => 'Wong',
            'date_hired' => '2026-01-01',
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-02-A',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-15',
            'payout_date' => '2026-02-20',
            'status' => 'open',
        ]);

        $draft = PayHeader::create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'days_worked' => 10.0,
            'total_gross_pay' => 10000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 9000.00,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($adminUser)
            ->withSession(['active_company_id' => $company->id])
            ->from(route('payroll-drafts.edit', $draft->id))
            ->put(route('payroll-drafts.update', $draft->id), [
                'days_worked' => 10,
                'basic_pay' => 10000.00,
                'overtime_pay' => 0.00,
                'total_allowances' => 0.00,
                'total_bonuses' => 0.00,
                'other_income' => 0.00,
                'sss_contribution' => 500.00,
                'philhealth_contribution' => 250.00,
                'pagibig_contribution' => 100.00,
                'withholding_tax' => 150.00,
            ]);

        $response->assertSessionHas('status');
        $draft->refresh();
        $this->assertEquals(9000.00, (float) $draft->net_pay);
    }
}
