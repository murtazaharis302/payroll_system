<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Models\EmployeeMaster;
use App\Models\CompanyBankSetting;
use App\Models\PayHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyBankSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic settings/roles if needed
        $role = Role::create([
            'id' => Role::SUPER_ADMIN,
            'name' => 'super-admin',
            'display_name' => 'Super Admin',
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $role->id,
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'company_code' => 'COMP-001',
            'name' => 'Acme Corporation',
            'email' => 'acme@example.com',
            'phone' => '123-456-7890',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-07-A',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-15',
            'status' => 'completed',
        ]);

        // Add employee and pay transaction
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'middle_name' => 'A',
            'email' => 'john@example.com',
            'status' => 'active',
            'acct_num' => '987654321',
        ]);

        PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'basic_pay' => 20000.00,
            'net_pay' => 19500.00,
            'gross_income' => 20000.00,
            'status' => 'approved',
        ]);
    }

    /**
     * Test generating bank report transmittal letter.
     */
    public function test_can_generate_bank_report_letter(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('reports.bank-report'), [
            'payroll_period_id' => $this->period->id,
            'bank_name' => 'Acme Mutual Bank',
            'branch_name' => 'Acme Corporate Center',
            'manager_name' => 'Mr. Manager',
            'company_acct' => '1122334455',
            'signatory_name' => 'Jane CEO',
            'signatory_position' => 'CEO',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test saving bank setting profile dynamically when generating report.
     */
    public function test_saves_bank_settings_profile_dynamically(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('reports.bank-report'), [
            'payroll_period_id' => $this->period->id,
            'bank_name' => 'Acme Mutual Bank',
            'branch_name' => 'Acme Corporate Center',
            'manager_name' => 'Mr. Manager',
            'company_acct' => '1122334455',
            'signatory_name' => 'Jane CEO',
            'signatory_position' => 'CEO',
            'save_bank_settings' => '1',
        ]);

        $this->assertDatabaseHas('company_bank_settings', [
            'company_id' => $this->company->id,
            'bank_name' => 'Acme Mutual Bank',
            'account_number' => '1122334455',
            'corporate_signee_1' => 'Jane CEO',
        ]);
    }

    /**
     * Test saving bank settings via inline AJAX endpoint.
     */
    public function test_can_save_bank_settings_ajax(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('reports.save-bank-settings'), [
            'company_id' => $this->company->id,
            'bank_name' => 'BDO Unibank',
            'account_number' => '9988776655',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('company_bank_settings', [
            'company_id' => $this->company->id,
            'bank_name' => 'BDO Unibank',
            'account_number' => '9988776655',
        ]);
    }

    /**
     * Test export payroll bank CSV generation using BankUploadExport.
     */
    public function test_can_export_bank_upload_csv(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('reports.export-payroll-bank'), [
            'payroll_period_id' => $this->period->id,
        ]);

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains((string)$response->headers->get('content-disposition'), '.csv') ||
            str_contains((string)$response->headers->get('content-type'), 'text/csv')
        );
    }

    /**
     * Test creating bank setting with minimal routing data (nullable fields).
     */
    public function test_migration_supports_nullable_fields(): void
    {
        $setting = CompanyBankSetting::create([
            'company_id' => $this->company->id,
            'bank_name' => 'Minimal Bank',
            'account_number' => '12345678',
            'branch_name' => null,
            'manager_name' => null,
            'corporate_signee_1' => null,
            'corporate_signee_1_title' => null,
        ]);

        $this->assertNotNull($setting->id);
        $this->assertDatabaseHas('company_bank_settings', [
            'id' => $setting->id,
            'bank_name' => 'Minimal Bank',
            'manager_name' => null,
        ]);
    }

    /**
     * Test generating bank report PDF without explicit bank settings inputs fallback gracefully.
     */
    public function test_bank_report_pdf_gracefully_falls_back_when_bank_settings_missing(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('reports.bank-report'), [
            'payroll_period_id' => $this->period->id,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test BankUploadExport collection returns pay headers in draft or non-approved status.
     */
    public function test_bank_upload_export_includes_pay_headers_in_draft_status(): void
    {
        $draftPeriod = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-07-B',
            'period_start' => '2026-07-16',
            'period_end' => '2026-07-31',
            'pay_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'status' => 'active',
            'acct_num' => '123456789',
        ]);

        PayHeader::create([
            'payroll_period_id' => $draftPeriod->id,
            'employee_id' => $emp->id,
            'basic_pay' => 15000.00,
            'net_pay' => 14000.00,
            'gross_income' => 15000.00,
            'status' => 'draft',
        ]);

        $export = new \App\Exports\BankUploadExport($draftPeriod->id, $this->company->id);
        $collection = $export->collection();

        $this->assertCount(1, $collection);
        $mapped = $export->map($collection->first());
        $this->assertEquals('Smith', $mapped[0]);
        $this->assertEquals('123456789', $mapped[3]);
        $this->assertEquals('14000.00', $mapped[4]);
    }
}

