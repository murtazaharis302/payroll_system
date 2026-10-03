<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFilterMonthEmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $companyA;
    protected Company $companyB;
    protected Department $deptA;
    protected EmployeeMaster $empA1;
    protected EmployeeMaster $empA2;
    protected EmployeeMaster $empB;
    protected PayrollPeriod $periodJuly;
    protected PayrollPeriod $periodAug1;
    protected PayrollPeriod $periodAug2;
    protected PayrollPeriod $periodB;
    protected DeductionType $sssDeduction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\StatutoryRulesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->companyA = Company::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Company Alpha',
            'company_code' => 'ALPHA',
            'email'        => 'alpha@example.com',
            'status'       => 1,
        ]);

        $this->companyB = Company::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Company Beta',
            'company_code' => 'BETA',
            'email'        => 'beta@example.com',
            'status'       => 1,
        ]);

        $this->deptA = Department::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Accounting',
            'code'       => 'ACC',
            'is_active'  => true,
        ]);

        $this->empA1 = EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'dept_id'       => $this->deptA->id,
            'employee_code' => 'EMP-A1',
            'first_name'    => 'Juan',
            'last_name'     => 'Dela Cruz',
            'status'        => 'active',
            'date_hired'    => '2026-01-01',
            'monthly_rate'  => 20000.00,
        ]);

        $this->empA2 = EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'dept_id'       => $this->deptA->id,
            'employee_code' => 'EMP-A2',
            'first_name'    => 'Maria',
            'last_name'     => 'Santos',
            'status'        => 'active',
            'date_hired'    => '2026-01-01',
            'monthly_rate'  => 30000.00,
        ]);

        $this->empB = EmployeeMaster::create([
            'company_id'    => $this->companyB->id,
            'employee_code' => 'EMP-B1',
            'first_name'    => 'Pedro',
            'last_name'     => 'Penduko',
            'status'        => 'active',
            'date_hired'    => '2026-01-01',
            'monthly_rate'  => 25000.00,
        ]);

        $this->periodJuly = PayrollPeriod::create([
            'company_id'   => $this->companyA->id,
            'period_name'  => 'July 2026 Full Month',
            'period_start' => '2026-07-01',
            'period_end'   => '2026-07-31',
            'pay_date'     => '2026-07-31',
            'status'       => 'closed',
        ]);

        $this->periodAug1 = PayrollPeriod::create([
            'company_id'   => $this->companyA->id,
            'period_name'  => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-15',
            'pay_date'     => '2026-08-15',
            'status'       => 'closed',
        ]);

        $this->periodAug2 = PayrollPeriod::create([
            'company_id'   => $this->companyA->id,
            'period_name'  => 'August 2026 2nd Cutoff',
            'period_start' => '2026-08-16',
            'period_end'   => '2026-08-31',
            'pay_date'     => '2026-08-31',
            'status'       => 'open',
        ]);

        $this->periodB = PayrollPeriod::create([
            'company_id'   => $this->companyB->id,
            'period_name'  => 'September 2026 Full Month',
            'period_start' => '2026-09-01',
            'period_end'   => '2026-09-30',
            'pay_date'     => '2026-09-30',
            'status'       => 'closed',
        ]);

        $this->sssDeduction = DeductionType::where('code', 'SSS')->first()
            ?? DeductionType::create(['company_id' => $this->companyA->id, 'name' => 'SSS Contribution', 'code' => 'SSS', 'is_active' => true]);
    }

    public function test_reports_hub_month_dropdown_is_company_scoped_and_newest_first(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('reports.index', ['company_id' => $this->companyA->id]));

        $response->assertStatus(200);

        // Should see August 2026 and July 2026 for Company A
        $response->assertSee('value="2026-08"', false);
        $response->assertSee('August 2026');
        $response->assertSee('value="2026-07"', false);
        $response->assertSee('July 2026');

        // Should NOT see September 2026 (which belongs to Company B)
        $response->assertDontSee('value="2026-09"');

        // Check ordering: 2026-08 appears before 2026-07
        $content = $response->getContent();
        $posAug = strpos($content, 'value="2026-08"');
        $posJul = strpos($content, 'value="2026-07"');
        $this->assertTrue($posAug !== false && $posJul !== false && $posAug < $posJul, 'August 2026 must appear before July 2026.');
    }

    public function test_reports_hub_month_dropdown_shows_disabled_placeholder_when_company_has_no_periods(): void
    {
        $emptyCompany = Company::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Empty Co',
            'company_code' => 'EMPTY',
            'email'        => 'empty@example.com',
            'status'       => 1,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('reports.index', ['company_id' => $emptyCompany->id]));

        $response->assertStatus(200);
        $response->assertSee('No payroll periods available');
    }

    public function test_statutory_reports_excel_download_succeeds_with_derived_month(): void
    {
        $resSSS = $this->actingAs($this->adminUser)
            ->post(route('reports.sss'), [
                'company_id' => $this->companyA->id,
                'month'      => '2026-08',
            ]);
        $resSSS->assertStatus(200);
        $this->assertStringContainsString('SSS_Report_2026_08.xlsx', (string) $resSSS->headers->get('content-disposition'));

        $resPH = $this->actingAs($this->adminUser)
            ->post(route('reports.philhealth'), [
                'company_id' => $this->companyA->id,
                'month'      => '2026-08',
            ]);
        $resPH->assertStatus(200);
        $this->assertStringContainsString('PhilHealth_Report_2026_08.xlsx', (string) $resPH->headers->get('content-disposition'));
    }

    public function test_deduction_ledger_employee_filter_and_tenant_boundary(): void
    {
        // Header for EmpA1
        $hA1 = PayHeader::create([
            'payroll_period_id' => $this->periodAug1->id,
            'employee_id'       => $this->empA1->id,
            'total_gross_pay'   => 10000.00,
            'total_deductions'  => 450.00,
            'net_pay'           => 9550.00,
            'status'            => 'approved',
        ]);

        PayOut::create([
            'pay_id'            => $hA1->id,
            'deduction_type_id' => $this->sssDeduction->id,
            'rate'              => 450.00,
            'number_input'      => 1.00,
        ]);

        // Header for EmpA2
        $hA2 = PayHeader::create([
            'payroll_period_id' => $this->periodAug1->id,
            'employee_id'       => $this->empA2->id,
            'total_gross_pay'   => 15000.00,
            'total_deductions'  => 675.00,
            'net_pay'           => 14325.00,
            'status'            => 'approved',
        ]);

        PayOut::create([
            'pay_id'            => $hA2->id,
            'deduction_type_id' => $this->sssDeduction->id,
            'rate'              => 675.00,
            'number_input'      => 1.00,
        ]);

        // 1. Filter by specific employee_id (EmpA1)
        $resEmpA1 = $this->actingAs($this->adminUser)
            ->post(route('reports.deduction-ledger'), [
                'company_id'        => $this->companyA->id,
                'from_period_id'    => $this->periodAug1->id,
                'to_period_id'      => $this->periodAug1->id,
                'deduction_type_id' => 'sss_contribution',
                'employee_id'       => $this->empA1->id,
                'export_format'     => 'pdf',
            ]);
        $resEmpA1->assertStatus(200);
        $resEmpA1->assertHeader('content-type', 'application/pdf');

        // 2. All Employees (employee_id = 'all') in xlsx format
        $resAllXlsx = $this->actingAs($this->adminUser)
            ->post(route('reports.deduction-ledger'), [
                'company_id'        => $this->companyA->id,
                'from_period_id'    => $this->periodAug1->id,
                'to_period_id'      => $this->periodAug1->id,
                'deduction_type_id' => 'sss_contribution',
                'employee_id'       => 'all',
                'export_format'     => 'xlsx',
            ]);
        $resAllXlsx->assertStatus(200);

        // 3. All Employees (employee_id empty) in csv format
        $resAllCsv = $this->actingAs($this->adminUser)
            ->post(route('reports.deduction-ledger'), [
                'company_id'        => $this->companyA->id,
                'from_period_id'    => $this->periodAug1->id,
                'to_period_id'      => $this->periodAug1->id,
                'deduction_type_id' => 'sss_contribution',
                'employee_id'       => '',
                'export_format'     => 'csv',
            ]);
        $resAllCsv->assertStatus(200);

        // 4. Cross-company employee ID rejection
        $resCross = $this->actingAs($this->adminUser)
            ->post(route('reports.deduction-ledger'), [
                'company_id'        => $this->companyA->id,
                'from_period_id'    => $this->periodAug1->id,
                'to_period_id'      => $this->periodAug1->id,
                'deduction_type_id' => 'sss_contribution',
                'employee_id'       => $this->empB->id, // belongs to Company B
                'export_format'     => 'pdf',
            ]);
        $resCross->assertSessionHas('error', 'No payroll transactions found for the selected criteria.');
    }
}
