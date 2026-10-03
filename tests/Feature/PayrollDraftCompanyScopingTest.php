<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDraftCompanyScopingTest extends TestCase
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

    public function test_super_admin_period_selection_is_scoped_to_selected_company(): void
    {
        $superAdmin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);

        $owner1 = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company1 = Company::create([
            'user_id' => $owner1->id,
            'name' => 'Company One',
            'company_code' => 'C1-001',
            'email' => 'c1@test.com',
            'status' => 1,
        ]);

        $owner2 = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company2 = Company::create([
            'user_id' => $owner2->id,
            'name' => 'Company Two',
            'company_code' => 'C2-002',
            'email' => 'c2@test.com',
            'status' => 1,
        ]);

        $periodCompany2 = PayrollPeriod::create([
            'company_id' => $company2->id,
            'period_name' => 'Company Two Period A',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'cut_off_date' => '2026-07-15',
            'payout_date' => '2026-07-20',
            'status' => 'open',
        ]);

        $this->actingAs($superAdmin);

        // When viewing Company 1 (which has no periods yet)
        $response = $this->get(route('payroll-drafts.create', ['company_id' => $company1->id]));
        $response->assertStatus(200);
        // Should NOT display Company Two's period in Company One's period list
        $response->assertDontSee('Company Two Period A');
        $response->assertSee('No payroll periods found');

        // When switching to Company 2
        $responseComp2 = $this->get(route('payroll-drafts.create', ['company_id' => $company2->id]));
        $responseComp2->assertStatus(200);
        $responseComp2->assertSee('Company Two Period A');
    }

    public function test_can_open_payroll_draft_edit_view_without_column_code_error(): void
    {
        $superAdmin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $superAdmin->id,
            'name' => 'Loan Test Co',
            'company_code' => 'LOAN-001',
            'email' => 'loan@test.com',
            'status' => 1,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'July 2026 1st Half',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'cut_off_date' => '2026-07-15',
            'payout_date' => '2026-07-20',
            'status' => 'open',
        ]);

        $empMaster = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-LOAN-01',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@test.com',
            'monthly_rate' => 30000,
            'date_hired' => '2026-01-01',
            'status' => 'active',
        ]);

        $draft = \App\Models\PayHeader::create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $empMaster->id,
            'status' => 'draft',
            'days_worked' => 11,
            'basic_pay' => 15000,
            'gross_pay' => 15000,
            'net_pay' => 15000,
        ]);

        $this->actingAs($superAdmin);

        $response = $this->get(route('payroll-drafts.edit', $draft->id));
        $response->assertStatus(200);
        $response->assertSee('John');
    }

    public function test_submitted_payroll_draft_can_be_edited(): void
    {
        $superAdmin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $superAdmin->id,
            'name' => 'Submitted Draft Co',
            'company_code' => 'SDC-01',
            'email' => 'sdc@test.com',
            'status' => 1,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'July 2026 2nd Half',
            'period_start' => '2026-07-16',
            'period_end' => '2026-07-31',
            'cut_off_date' => '2026-07-31',
            'payout_date' => '2026-08-05',
            'status' => 'open',
        ]);

        $empMaster = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-SUB-01',
            'first_name' => 'Mary',
            'last_name' => 'Jane',
            'email' => 'maryjane@test.com',
            'monthly_rate' => 40000,
            'date_hired' => '2026-01-01',
            'status' => 'active',
        ]);

        $draft = \App\Models\PayHeader::create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $empMaster->id,
            'status' => 'pending_approval',
            'posted_at' => now(),
            'total_gross_pay' => 20000,
            'total_deductions' => 2000,
            'net_pay' => 18000,
        ]);

        $this->actingAs($superAdmin);

        // Submitted draft edit page redirects with warning
        $response = $this->get(route('payroll-drafts.edit', $draft->id));
        $response->assertRedirect();

        // Updating submitted draft returns 409
        $updateResp = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 11,
            'basic_pay' => 20000,
        ]);
        $updateResp->assertStatus(409);
    }

    public function test_approved_payroll_draft_cannot_be_edited(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'Approved Edit Co',
            'company_code' => 'AEC-01',
            'email' => 'aec@test.com',
            'status' => 1,
        ]);

        $hrUser = User::factory()->create([
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'August 2026 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'cut_off_date' => '2026-08-15',
            'payout_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $empMaster = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-APP-01',
            'first_name' => 'Robert',
            'last_name' => 'Paulson',
            'email' => 'robert@test.com',
            'monthly_rate' => 50000,
            'date_hired' => '2026-01-01',
            'status' => 'active',
        ]);

        $approvedDraft = \App\Models\PayHeader::create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $empMaster->id,
            'status' => 'approved',
            'posted_at' => now(),
            'approved_by' => $companyOwner->id,
            'total_gross_pay' => 25000,
            'total_deductions' => 3000,
            'net_pay' => 22000,
        ]);

        // HR user cannot edit approved draft
        $this->actingAs($hrUser);
        $editResp = $this->get(panelRoute('payroll-drafts.edit', $approvedDraft->id));
        $editResp->assertRedirect();

        $updateResp = $this->put(panelRoute('payroll-drafts.update', $approvedDraft->id), [
            'days_worked' => 11,
            'basic_pay' => 25000,
        ]);
        $updateResp->assertStatus(409);

        $approvedDraft->refresh();
        $this->assertEquals('approved', $approvedDraft->status);
        $this->assertEquals($companyOwner->id, $approvedDraft->approved_by);
    }

    public function test_unlinked_company_employee_can_be_selected_and_linked_to_payroll_profile(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'Auto Link Co',
            'company_code' => 'ALC-01',
            'email' => 'alc@test.com',
            'status' => 1,
        ]);

        $unlinkedUser = User::factory()->create([
            'name' => 'Unlinked Worker',
            'email' => 'unlinked@test.com',
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $company->id,
            'emp_id' => null,
        ]);

        $this->actingAs($companyOwner);

        // Access create form and ensure unlinked user is listed
        $createResp = $this->get(route('payroll-employees.create'));
        $createResp->assertStatus(200);
        $createResp->assertSee('Unlinked Worker');

        // Store payroll employee profile for unlinked user
        $storeResp = $this->post(route('payroll-employees.store'), [
            'user_id' => $unlinkedUser->id,
            'company_id' => $company->id,
            'employee_code' => 'EMP-LINK-01',
            'first_name' => 'Unlinked',
            'last_name' => 'Worker',
            'email' => 'unlinked@test.com',
            'date_hired' => '2026-01-01',
            'position' => 'Staff',
            'status' => 'active',
        ]);
        $storeResp->assertRedirect(route('payroll-employees.index'));

        // Assert user emp_id is now updated and linked to new EmployeeMaster
        $unlinkedUser->refresh();
        $this->assertNotNull($unlinkedUser->emp_id);

        $master = \App\Models\EmployeeMaster::find($unlinkedUser->emp_id);
        $this->assertNotNull($master);
        $this->assertEquals('unlinked@test.com', $master->email);
    }
}
