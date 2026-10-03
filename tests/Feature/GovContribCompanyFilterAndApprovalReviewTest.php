<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovContribCompanyFilterAndApprovalReviewTest extends TestCase
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

    public function test_super_admin_can_filter_contributions_by_company(): void
    {
        $superAdmin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        
        $companyA = Company::create([
            'user_id' => $superAdmin->id,
            'name' => 'Gov Comp A',
            'company_code' => 'GCA',
            'email' => 'gca@test.com',
            'status' => 1,
        ]);

        $companyB = Company::create([
            'user_id' => $superAdmin->id,
            'name' => 'Gov Comp B',
            'company_code' => 'GCB',
            'email' => 'gcb@test.com',
            'status' => 1,
        ]);

        $empA = EmployeeMaster::create([
            'company_id' => $companyA->id,
            'employee_code' => 'EMP-GCA-01',
            'first_name' => 'Alpha',
            'last_name' => 'User',
            'email' => 'alpha@test.com',
            'status' => 'active',
        ]);

        $empB = EmployeeMaster::create([
            'company_id' => $companyB->id,
            'employee_code' => 'EMP-GCB-01',
            'first_name' => 'Beta',
            'last_name' => 'User',
            'email' => 'beta@test.com',
            'status' => 'active',
        ]);

        GovernmentContribution::create([
            'company_id' => $companyA->id,
            'employee_id' => $empA->id,
            'type' => 'sss',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'employee_share' => 500,
            'employer_share' => 1000,
            'total_contribution' => 1500,
            'status' => 'pending',
        ]);

        GovernmentContribution::create([
            'company_id' => $companyB->id,
            'employee_id' => $empB->id,
            'type' => 'philhealth',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'employee_share' => 300,
            'employer_share' => 300,
            'total_contribution' => 600,
            'status' => 'pending',
        ]);

        $this->actingAs($superAdmin);

        // Access without company filter: should show both
        $response = $this->get(route('government-contributions.index'));
        $response->assertStatus(200);
        $response->assertSee('Alpha');
        $response->assertSee('Beta');
        $response->assertSee('Company');

        // Access with company filter = Company A
        $responseA = $this->get(route('government-contributions.index', ['company_id' => $companyA->id]));
        $responseA->assertStatus(200);
        $responseA->assertSee('Alpha');
        $responseA->assertDontSee('Beta');
    }

    public function test_pay_header_dynamic_calculation_accessors(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Calc Test Co',
            'company_code' => 'CTC',
            'email' => 'ctc@test.com',
            'status' => 1,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-CTC-01',
            'first_name' => 'Calc',
            'last_name' => 'Tester',
            'email' => 'calc@test.com',
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '07/01/2026 to 07/15/2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
            'status' => 'open',
        ]);

        $payHeader = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 10000.00,
            'total_deductions' => 2000.00,
            'net_pay' => 8000.00,
            'posted_at' => now(),
        ]);

        $this->assertEquals(10000.00, (float)$payHeader->gross_pay);
        $this->assertEquals(2000.00, (float)$payHeader->total_deductions);
        $this->assertEquals(8000.00, (float)$payHeader->net_pay);

        $this->actingAs($admin);
        $reviewResponse = $this->get(route('payroll-approvals.review', $payHeader->id));
        $reviewResponse->assertStatus(200);
        $reviewResponse->assertSee('10,000.00');
        $reviewResponse->assertSee('2,000.00');
        $reviewResponse->assertSee('8,000.00');
    }
}
