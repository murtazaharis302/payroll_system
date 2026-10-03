<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\PayrollMasterSheetExport;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollSummaryHeadersCodeTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyAdmin;
    protected EmployeeMaster $employee;
    protected Department $department;
    protected PayrollPeriod $period;
    protected PayHeader $payHeader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_hdr_test@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::SUPER_ADMIN,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Header Test Corp',
            'email' => 'hdr_corp@test.com',
            'status' => 1,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin_hdr_corp@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'company_id' => $this->company->id,
            'status' => 1,
        ]);

        $this->department = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Technology',
            'code' => 'TECH',
            'is_active' => true,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-HDR-01',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'dept_id' => $this->department->id,
            'status' => 'active',
            'monthly_rate' => 50000,
            'date_hired' => '2025-01-01',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Sept 1-15 Cutoff',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'pay_date' => '2026-09-20',
            'status' => 'completed',
        ]);

        $this->payHeader = PayHeader::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'status' => 'approved',
            'total_gross_pay' => 25000.00,
            'total_deductions' => 3000.00,
            'net_pay' => 22000.00,
        ]);

        $basicType = IncomeType::where('code', 'BASIC')->first();
        if ($basicType) {
            PayIn::create([
                'pay_id' => $this->payHeader->id,
                'income_type_id' => $basicType->id,
                'rate' => 25000.00,
                'number_input' => 1,
            ]);
        }
    }

    /**
     * Test that Payroll Summary 1 HTML output displays income and deduction codes in headers.
     */
    public function test_payroll_summary_1_renders_income_and_deduction_codes(): void
    {
        $this->actingAs($this->companyAdmin);

        $customIncome = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Transportation Allowance',
            'code' => 'TRANS_ALLW',
            'is_active' => true,
        ]);

        $customDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Uniform Fee Deduction',
            'code' => 'UNIFORM_FEE',
            'is_active' => true,
        ]);

        PayIn::create([
            'pay_id' => $this->payHeader->id,
            'income_type_id' => $customIncome->id,
            'rate' => 500.00,
            'number_input' => 1,
        ]);

        PayOut::create([
            'pay_id' => $this->payHeader->id,
            'deduction_type_id' => $customDeduction->id,
            'rate' => 200.00,
            'number_input' => 1,
        ]);

        $response = $this->post('/company/reports/payroll-summary-1', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'html',
        ]);

        $response->assertOk();
        $content = $response->getContent();

        // Income Code in header
        $this->assertStringContainsString('TRANS_ALLW Qty', $content);
        $this->assertStringContainsString('TRANS_ALLW Amt', $content);

        // Deduction Code in header
        $this->assertStringContainsString('UNIFORM_FEE', $content);
    }

    /**
     * Test that Payroll Summary 2 HTML output displays income and deduction codes in headers.
     */
    public function test_payroll_summary_2_renders_income_and_deduction_codes(): void
    {
        $this->actingAs($this->companyAdmin);

        $customIncome = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Meal Allowance Extra',
            'code' => 'MEAL_ALLW',
            'is_active' => true,
        ]);

        $customDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Cooperative Contribution',
            'code' => 'COOP_DED',
            'is_active' => true,
        ]);

        PayIn::create([
            'pay_id' => $this->payHeader->id,
            'income_type_id' => $customIncome->id,
            'rate' => 750.00,
            'number_input' => 1,
        ]);

        PayOut::create([
            'pay_id' => $this->payHeader->id,
            'deduction_type_id' => $customDeduction->id,
            'rate' => 350.00,
            'number_input' => 1,
        ]);

        $response = $this->post('/company/reports/payroll-summary-2', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'html',
        ]);

        $response->assertOk();
        $content = $response->getContent();

        // Income Code in header
        $this->assertStringContainsString('MEAL_ALLW Qty', $content);
        $this->assertStringContainsString('MEAL_ALLW Amt', $content);

        // Deduction Code in header
        $this->assertStringContainsString('COOP_DED', $content);
    }

    /**
     * Test fallback to name when code is empty or null.
     */
    public function test_headers_fallback_to_name_when_code_is_empty(): void
    {
        $this->actingAs($this->companyAdmin);

        $noCodeIncome = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Special Project Stipend',
            'code' => '',
            'is_active' => true,
        ]);

        $noCodeDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Emergency Fund Levy',
            'code' => '',
            'is_active' => true,
        ]);

        PayIn::create([
            'pay_id' => $this->payHeader->id,
            'income_type_id' => $noCodeIncome->id,
            'rate' => 1200.00,
            'number_input' => 1,
        ]);

        PayOut::create([
            'pay_id' => $this->payHeader->id,
            'deduction_type_id' => $noCodeDeduction->id,
            'rate' => 150.00,
            'number_input' => 1,
        ]);

        $response = $this->post('/company/reports/payroll-summary-1', [
            'payroll_period_id' => $this->period->id,
            'export_format' => 'html',
        ]);

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Special Project Stipend Qty', $content);
        $this->assertStringContainsString('Special Project Stipend Amt', $content);
        $this->assertStringContainsString('Emergency Fund Levy', $content);
    }

    /**
     * Test PayrollMasterSheetExport headings include codes and fallbacks.
     */
    public function test_payroll_master_sheet_export_headings_use_codes(): void
    {
        $incomeCode = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Night Differential',
            'code' => 'NIGHT_DIFF',
            'is_active' => true,
        ]);

        $deductionCode = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Union Dues Assessment',
            'code' => 'UNION_DUES',
            'is_active' => true,
        ]);

        PayIn::create([
            'pay_id' => $this->payHeader->id,
            'income_type_id' => $incomeCode->id,
            'rate' => 600.00,
            'number_input' => 1,
        ]);

        PayOut::create([
            'pay_id' => $this->payHeader->id,
            'deduction_type_id' => $deductionCode->id,
            'rate' => 100.00,
            'number_input' => 1,
        ]);

        $export = new PayrollMasterSheetExport($this->period->id, $this->company->id);
        $headings = $export->headings();

        $this->assertContains('NIGHT_DIFF Qty', $headings);
        $this->assertContains('NIGHT_DIFF Amount', $headings);
        $this->assertContains('UNION_DUES', $headings);
    }
}
