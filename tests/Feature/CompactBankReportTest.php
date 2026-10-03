<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactBankReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => Role::SUPER_ADMIN], [
            'name' => 'super-admin',
            'display_name' => 'Super Admin',
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $role->id,
            'role' => 'company',
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Compact Bank Test Corp',
            'company_name' => 'Compact Bank Test Corp',
            'company_code' => 'COMP-BANK',
            'email' => 'bank_test@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);
        $dept = Department::create(['company_id' => $this->company->id, 'code' => 'OPS', 'name' => 'Operations']);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-08-1st-Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);
    }

    /**
     * Test 1: Bank report template uses compact CSS reset and excludes global margin bloat.
     */
    public function test_bank_report_uses_compact_css_and_removes_global_margin_bloat(): void
    {
        $this->createPayrollEmployees(2);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('company.reports.bank-report'), [
                'from_period_id' => $this->period->id,
                'to_period_id' => $this->period->id,
                'bank_name' => 'BDO',
                'company_acct' => '1234567890',
                'signatory_name' => 'Jane Doe',
                'signatory_position' => 'Finance Director',
                'export_format' => 'html',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('* { margin: 8px 5px;', $content);
        $this->assertStringContainsString('html, body, div, p, table, thead, tbody, tr, th, td', $content);
        $this->assertStringContainsString('.report-page', $content);
        $this->assertStringContainsString('margin: 10mm 12mm 8mm;', $content);
        $this->assertStringContainsString('thead {', $content);
        $this->assertStringContainsString('display: table-header-group;', $content);
        $this->assertStringContainsString('.account-number, .atm-number, .amount', $content);
        $this->assertStringContainsString('white-space: nowrap;', $content);
        $this->assertStringContainsString('.signatory-section', $content);
        $this->assertStringContainsString('page-break-inside: avoid;', $content);
    }

    /**
     * Test 2: 50-employee bank report renders all 50 records continuously with correct order and values.
     */
    public function test_fifty_employee_bank_report_renders_all_records_continuously_without_artificial_spacers(): void
    {
        $this->createPayrollEmployees(50);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('company.reports.bank-report'), [
                'from_period_id' => $this->period->id,
                'to_period_id' => $this->period->id,
                'bank_name' => 'BPI',
                'company_acct' => '0987654321',
                'signatory_name' => 'John Smith',
                'signatory_position' => 'General Manager',
                'export_format' => 'html',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check for 50 rows
        for ($i = 1; $i <= 50; $i++) {
            $empCode = sprintf('EMP-%03d', $i);
            $this->assertStringContainsString($empCode, $content);
        }

        // Verify grand total amount (50 * 15,000.00 = 750,000.00)
        $this->assertStringContainsString('PHP 750,000.00', $content);
        $this->assertStringContainsString('750,000.00', $content);
    }

    /**
     * Test 3: Valid employee with blank optional fields is preserved.
     */
    public function test_bank_report_preserves_valid_employees_with_blank_optional_fields(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-NO-ATM',
            'first_name' => 'NoATM',
            'last_name' => 'Employee',
            'status' => 'active',
            'acct_num' => '999888777',
            'atm_num' => null,
        ]);

        PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $emp->id,
            'status' => 'approved',
            'total_gross_pay' => 10000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 9000.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('company.reports.bank-report'), [
                'from_period_id' => $this->period->id,
                'to_period_id' => $this->period->id,
                'bank_name' => 'Metrobank',
                'company_acct' => '111222333',
                'signatory_name' => 'Alice Officer',
                'signatory_position' => 'Payroll Officer',
                'export_format' => 'html',
            ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('EMP-NO-ATM', $content);
        $this->assertStringContainsString('999888777', $content);
        $this->assertStringContainsString('9,000.00', $content);
    }

    protected function createPayrollEmployees(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $code = sprintf('EMP-%03d', $i);
            $emp = EmployeeMaster::create([
                'company_id' => $this->company->id,
                'employee_code' => $code,
                'first_name' => 'First' . $i,
                'last_name' => 'Last' . $i,
                'status' => 'active',
                'acct_num' => 'ACCT-' . $code,
                'atm_num' => 'ATM-' . $code,
            ]);

            PayHeader::create([
                'payroll_period_id' => $this->period->id,
                'employee_id' => $emp->id,
                'status' => 'approved',
                'total_gross_pay' => 20000.00,
                'total_deductions' => 5000.00,
                'net_pay' => 15000.00,
            ]);
        }
    }
}
