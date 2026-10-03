<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollDraftSectionSubtotalsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected EmployeeMaster $employee;
    protected PayHeader $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin Subtotals',
            'email' => 'admin_subtotals@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Subtotals Corp',
            'company_code' => 'SUBTOT',
            'email' => 'subtotals@test.com',
            'status' => '1',
        ]);
        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 Cutoff 1',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-SUB-01',
            'first_name' => 'Subtotal',
            'last_name' => 'Tester',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$this->employee->id]);
        $this->draft = $headers->first();
    }

    /**
     * Test 1: Payroll Draft Edit screen renders section subtotals for Income and Deductions.
     */
    public function test_edit_screen_renders_income_and_deduction_section_subtotals(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.edit', $this->draft->id));
        $response->assertStatus(200);

        // Rendered HTML contains section_total_gross and section_total_deductions elements
        $response->assertSee('id="section_total_gross"', false);
        $response->assertSee('id="section_total_deductions"', false);

        // Section subtotals display the exact formatted amounts matching underlying draft values
        $formattedGross = '₱ ' . number_format($this->draft->total_gross_pay, 2);
        $formattedDeductions = '₱ ' . number_format($this->draft->total_deductions, 2);

        $response->assertSee($formattedGross);
        $response->assertSee($formattedDeductions);
    }

    /**
     * Test 2: Existing Payroll Summary box remains intact alongside new section subtotals.
     */
    public function test_existing_payroll_summary_box_remains_intact(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.edit', $this->draft->id));
        $response->assertStatus(200);

        // Original summary IDs are preserved
        $response->assertSee('id="summary_total_gross"', false);
        $response->assertSee('id="summary_total_deductions"', false);
        $response->assertSee('id="summary_net_pay"', false);
    }

    /**
     * Test 3: Section subtotals match the exact underlying draft database values.
     */
    public function test_section_subtotals_match_underlying_draft_values(): void
    {
        $expectedGrossFormatted = '₱ ' . number_format($this->draft->total_gross_pay, 2);

        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.edit', $this->draft->id));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Both section_total_gross and summary_total_gross contain the exact same string
        $this->assertStringContainsString('id="section_total_gross">' . $expectedGrossFormatted . '</h5>', $content);
        $this->assertStringContainsString('id="summary_total_gross">' . $expectedGrossFormatted . '</h4>', $content);
    }
}
