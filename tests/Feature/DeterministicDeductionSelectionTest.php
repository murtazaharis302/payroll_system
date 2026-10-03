<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeterministicDeductionSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period;
    protected DeductionType $sssDeduction;
    protected DeductionType $taxDeduction;
    protected DeductionType $sssLoanDeduction;
    protected DeductionType $tardinessDeduction;
    protected DeductionType $otherDeduction;
    protected DeductionType $customDeduction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(\Database\Seeders\StatutoryRulesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_det_dec@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Deterministic Deduction Corp',
            'company_code' => 'DETDEC',
            'email' => 'detdec@corp.com',
            'status' => '1',
        ]);

        session(['active_company_id' => $this->company->id]);
        $this->actingAs($this->adminUser);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $this->sssDeduction = DeductionType::where('code', 'SSS')->firstOrFail();
        $this->taxDeduction = DeductionType::where('code', 'TAX')->firstOrFail();
        $this->sssLoanDeduction = DeductionType::where('code', 'SSS_LOAN')->firstOrFail();
        $this->tardinessDeduction = DeductionType::where('code', 'TARDINESS')->firstOrFail();
        $this->otherDeduction = DeductionType::where('code', 'OTHER')->firstOrFail();

        $this->customDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'UNIFORM',
            'name' => 'Uniform Deduction',
            'type' => 'other',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Client Reproduction - 5 selected deductions all appear in draft when valid.
     */
    public function test_five_selected_deductions_all_appear_in_draft(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DEC-05',
            'first_name' => 'Selected',
            'last_name' => 'Five',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        // Create loan type and active loan for SSS_LOAN
        $loanType = LoanType::create([
            'company_id' => $this->company->id,
            'name' => 'SSS Salary Loan',
            'deduction_type_id' => $this->sssLoanDeduction->id,
        ]);

        EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 10000.00,
            'current_balance' => 5000.00,
            'monthly_amortization' => 500.00,
            'status' => 'active',
            'approved_at' => now(),
        ]);

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'SSS', 'display' => 1, 'amount' => 500.00],
                ['deduction_type' => 'SSS_LOAN', 'display' => 1, 'amount' => 200.00],
                ['deduction_type' => 'TARDINESS', 'display' => 1, 'amount' => 50.00],
                ['deduction_type' => 'OTHER', 'display' => 1, 'amount' => 100.00],
                ['deduction_type' => 'UNIFORM', 'display' => 1, 'amount' => 150.00],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payOuts = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        // Verify all 5 selected deductions generated PayOut lines
        $this->assertGreaterThanOrEqual(5, $payOuts->count());
        $this->assertNotNull($payOuts->firstWhere('deduction_type_id', $this->sssDeduction->id));
        $this->assertNotNull($payOuts->firstWhere('deduction_type_id', $this->sssLoanDeduction->id));
        $this->assertNotNull($payOuts->firstWhere('deduction_type_id', $this->tardinessDeduction->id));
        $this->assertNotNull($payOuts->firstWhere('deduction_type_id', $this->otherDeduction->id));
        $this->assertNotNull($payOuts->firstWhere('deduction_type_id', $this->customDeduction->id));
    }

    /**
     * Test 2: Zero-value selected deduction remains available as editable PayOut line.
     */
    public function test_zero_value_selected_deduction_remains_available(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ZERO-DEC',
            'first_name' => 'ZeroVal',
            'last_name' => 'Deduction',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'TARDINESS', 'display' => 1, 'amount' => 0.00],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payOuts = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        $tardinessPayOut = $payOuts->firstWhere('deduction_type_id', $this->tardinessDeduction->id);
        $this->assertNotNull($tardinessPayOut);
        $this->assertEquals(0.00, (float) $tardinessPayOut->rate);
    }

    /**
     * Test 3: Unselected deduction does not appear in draft.
     */
    public function test_unselected_deduction_does_not_appear(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-UNSEL-DEC',
            'first_name' => 'Unselected',
            'last_name' => 'Deduction',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'OTHER', 'display' => 0, 'amount' => 100.00], // Unselected
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payOuts = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        $this->assertNull($payOuts->firstWhere('deduction_type_id', $this->otherDeduction->id));
    }

    /**
     * Test 4: Custom master catalog deduction works.
     */
    public function test_custom_catalog_deduction_works(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-CUST-DEC',
            'first_name' => 'Custom',
            'last_name' => 'Deduction',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'UNIFORM', 'display' => 1, 'amount' => 250.00],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payOuts = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        $customPayOut = $payOuts->firstWhere('deduction_type_id', $this->customDeduction->id);
        $this->assertNotNull($customPayOut);
        $this->assertEquals(250.00, (float) $customPayOut->rate);
    }

    /**
     * Test 5: Invalid loan-backed deduction produces explicit review feedback instead of silently disappearing.
     */
    public function test_invalid_loan_backed_deduction_produces_explicit_review_reason(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-NOLOAN-DEC',
            'first_name' => 'NoActive',
            'last_name' => 'Loan',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        // Employee has NO active loan for SSS_LOAN

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'SSS_LOAN', 'display' => 1, 'amount' => 500.00],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $payOuts = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->get();

        // Verifies PayOut is skipped for loan without active liability
        $this->assertNull($payOuts->firstWhere('deduction_type_id', $this->sssLoanDeduction->id));

        // Verifies requires_review is set and review_reason contains explicit feedback
        $this->assertTrue((bool) $draft->requires_review);
        $this->assertStringContainsString('Selected loan deductions skipped due to missing active loans', (string) $draft->review_reason);
    }

    /**
     * Test 6: No duplicate PayOut lines are generated for the same deduction type.
     */
    public function test_no_duplicate_payout_lines_generated(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DUP-DEC',
            'first_name' => 'NoDup',
            'last_name' => 'Check',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'OTHER', 'display' => 1, 'amount' => 100.00],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);

        $draft = $headers->first();
        $otherLines = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $this->otherDeduction->id)
            ->get();

        $this->assertCount(1, $otherLines);
    }

    /**
     * Test 7: Dynamic Deduction Recalculation After Income Changes.
     */
    public function test_dynamic_deduction_recalculation_after_income_changes(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-RECALC-TAX',
            'first_name' => 'Dynamic',
            'last_name' => 'Recalc',
            'monthly_rate' => 60000.00, // Initial high income
            'status' => 'active',
        ]);

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'TAX', 'display' => 1],
                ['deduction_type' => 'OTHER', 'display' => 1, 'amount' => 150.00], // Non-income-dependent fixed deduction
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        $basicType = IncomeType::where('code', 'BASIC')->firstOrFail();
        $basicPayIn = PayIn::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('income_type_id', $basicType->id)
            ->firstOrFail();

        $taxPayOut = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $this->taxDeduction->id)
            ->firstOrFail();

        $otherPayOut = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft->id)
            ->where('deduction_type_id', $this->otherDeduction->id)
            ->firstOrFail();

        $initialTaxRate = (float) $taxPayOut->rate;
        $this->assertGreaterThan(0.00, $initialTaxRate);
        $this->assertEquals(150.00, (float) $otherPayOut->rate);

        // 1. Reduce employee's income (Basic Pay) on draft via controller update
        $this->actingAs($this->adminUser);

        $response1 = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'basic_pay' => 15000.00, // Reduced Basic Pay from 30,000 to 15,000
        ]);

        $response1->assertSessionHasNoErrors();

        $draft->refresh();
        $taxPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('deduction_type_id', $this->taxDeduction->id)->firstOrFail();
        $otherPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('deduction_type_id', $this->otherDeduction->id)->firstOrFail();

        $reducedTaxRate = (float) $taxPayOut->rate;
        $this->assertLessThan($initialTaxRate, $reducedTaxRate);
        $this->assertEquals(150.00, (float) $otherPayOut->rate); // Fixed deduction remains unchanged!

        // 2. Increase employee's income (Basic Pay) on draft
        $response2 = $this->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 15,
            'basic_pay' => 45000.00, // Increased Basic Pay to 45,000
        ]);

        $response2->assertSessionHasNoErrors();

        $draft->refresh();
        $taxPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('deduction_type_id', $this->taxDeduction->id)->firstOrFail();
        $otherPayOut = PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('deduction_type_id', $this->otherDeduction->id)->firstOrFail();

        $increasedTaxRate = (float) $taxPayOut->rate;
        $this->assertGreaterThan($reducedTaxRate, $increasedTaxRate);
        $this->assertEquals(150.00, (float) $otherPayOut->rate); // Fixed deduction remains unchanged!
    }

    /**
     * Test 8: Cross-Company TAX Deduction Scoping During Recalculation.
     */
    public function test_cross_company_tax_deduction_scoping_during_recalculation(): void
    {
        // Scope the TAX deduction type specifically to company 1
        $this->taxDeduction->update(['company_id' => $this->company->id]);

        // Create Company 2 and its own custom TAX deduction type
        $otherCompany = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Other Corp',
            'company_code' => 'OTHERCORP',
            'email' => 'other@corp.com',
            'status' => '1',
        ]);

        $otherDept = Department::create([
            'company_id' => $otherCompany->id,
            'code' => 'OTHENG',
            'name' => 'Other Engineering',
            'is_active' => true,
        ]);

        $otherPeriod = PayrollPeriod::create([
            'company_id' => $otherCompany->id,
            'period_name' => 'August 2026 1st Cutoff Other',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $otherEmployee = EmployeeMaster::create([
            'company_id' => $otherCompany->id,
            'dept_id' => $otherDept->id,
            'employee_code' => 'EMP-OTHER-TAX',
            'first_name' => 'Other',
            'last_name' => 'CompanyEmp',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        // 1. Recalculate for Company 1's draft
        $employee1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-COMP1-TAX',
            'first_name' => 'Comp1',
            'last_name' => 'Emp',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        $headers1 = $service->generateDraft($this->period, [$employee1->id]);
        $draft1 = $headers1->first();

        $service->recalculateDraftStatutoryDeductions($draft1);

        $taxPayOut1 = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft1->id)
            ->where('deduction_type_id', $this->taxDeduction->id)
            ->first();

        // Company 1 draft successfully finds and updates Company 1's TAX deduction
        $this->assertNotNull($taxPayOut1);

        // 2. Recalculate for Company 2's draft
        $draft2 = PayHeader::create([
            'payroll_period_id' => $otherPeriod->id,
            'employee_id' => $otherEmployee->id,
            'days_worked' => 15,
            'status' => 'draft',
        ]);

        PayOut::create([
            'pay_id' => $draft2->id,
            'deduction_type_id' => $this->taxDeduction->id,
            'number_input' => 1.00,
            'rate' => 100.00,
        ]);

        // Recalculating statutory deductions for Company 2 must NOT update Company 1's TAX deduction type
        $service->recalculateDraftStatutoryDeductions($draft2);

        $taxPayOut2 = PayOut::withoutGlobalScopes()
            ->where('pay_id', $draft2->id)
            ->where('deduction_type_id', $this->taxDeduction->id)
            ->first();

        // Rate remains unchanged (100.00) because Company 1's TAX deduction type was correctly excluded by company scoping
        $this->assertEquals(100.00, (float) $taxPayOut2->rate);
    }

    /**
     * Test 9: Multiple missing loan deductions produce concise review_reason that fits DB column capacity (<= 255 chars).
     */
    public function test_multiple_missing_loan_deductions_concise_review_reason_fits_db_column(): void
    {
        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-MULTI-LOAN-MISSING',
            'first_name' => 'MultiLoan',
            'last_name' => 'Missing',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $pagibigLoan = DeductionType::where('code', 'PAGIBIG_LOAN')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'code' => 'PAGIBIG_LOAN', 'name' => 'Pag-IBIG Loan', 'type' => 'loan']);
        $vale = DeductionType::where('code', 'VALE')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'code' => 'VALE', 'name' => 'Vale', 'type' => 'loan']);
        $goods = DeductionType::where('code', 'GOODS')->first()
            ?? DeductionType::create(['company_id' => $this->company->id, 'code' => 'GOODS', 'name' => 'Goods Purchase', 'type' => 'loan']);
        $genLoan = DeductionType::create(['company_id' => $this->company->id, 'code' => 'GEN_LOAN', 'name' => 'General Loan Deduction', 'type' => 'loan']);

        $options = [
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'SSS_LOAN', 'display' => 1, 'amount' => 500.00],
                ['deduction_type' => $pagibigLoan->code, 'display' => 1, 'amount' => 500.00],
                ['deduction_type' => $vale->code, 'display' => 1, 'amount' => 500.00],
                ['deduction_type' => $goods->code, 'display' => 1, 'amount' => 500.00],
                ['deduction_type' => $genLoan->code, 'display' => 1, 'amount' => 500.00],
            ],
        ];

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($this->period, [$employee->id], $options);
        $draft = $headers->first();

        // 1. Generation must succeed and draft exist
        $this->assertNotNull($draft);
        $this->assertTrue((bool) $draft->requires_review);

        // 2. review_reason must be non-empty and fit within 255 characters
        $reason = (string) $draft->review_reason;
        $this->assertNotEmpty($reason);
        $this->assertLessThanOrEqual(255, mb_strlen($reason));

        // 3. review_reason contains concise summary of skipped loan deductions
        $this->assertStringContainsString('Selected loan deductions skipped due to missing active loans:', $reason);
    }
}
