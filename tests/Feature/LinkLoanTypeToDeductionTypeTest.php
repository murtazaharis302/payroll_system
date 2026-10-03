<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\LoanType;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LinkLoanTypeToDeductionTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $company1Admin;
    protected User $company2Admin;
    protected Company $company1;
    protected Company $company2;
    protected EmployeeMaster $employee1;
    protected DeductionType $globalDeductionType;
    protected DeductionType $company1DeductionType;
    protected DeductionType $company2DeductionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_link_loan@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company1 = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Company 1',
            'email' => 'c1@test.com',
            'status' => '1',
        ]);

        $this->company2 = Company::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'Company 2',
            'email' => 'c2@test.com',
            'status' => '1',
        ]);

        $this->company1Admin = User::create([
            'company_id' => $this->company1->id,
            'name' => 'Company 1 Admin',
            'email' => 'admin1@test.com',
            'password' => Hash::make('password'),
            'role' => 'company_admin',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->company2Admin = User::create([
            'company_id' => $this->company2->id,
            'name' => 'Company 2 Admin',
            'email' => 'admin2@test.com',
            'password' => Hash::make('password'),
            'role' => 'company_admin',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $dept1 = \App\Models\Department::create([
            'company_id' => $this->company1->id,
            'department_name' => 'HR Department',
            'department_code' => 'HR',
            'code' => 'HR',
            'name' => 'HR Department',
        ]);

        $this->employee1 = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'department' => 'HR Department',
            'dept_id' => $dept1->id,
            'employee_code' => 'EMP-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'monthly_rate' => 30000.00,
            'daily_rate' => 1153.85,
            'hourly_rate' => 144.23,
            'status' => 'active',
            'employment_type' => 'REGULAR',
        ]);

        $this->globalDeductionType = DeductionType::withoutGlobalScopes()->where('code', 'VALE')->first();
        $this->globalDeductionType->update(['is_loan_enabled' => true]);

        $this->company1DeductionType = DeductionType::create([
            'company_id' => $this->company1->id,
            'name' => 'C1 Custom Loan Deduction',
            'code' => 'C1_CUSTOM_DED',
            'type' => 'loan',
            'is_active' => true,
            'is_loan_enabled' => true,
        ]);

        $this->company2DeductionType = DeductionType::create([
            'company_id' => $this->company2->id,
            'name' => 'C2 Custom Loan Deduction',
            'code' => 'C2_CUSTOM_DED',
            'type' => 'loan',
            'is_active' => true,
            'is_loan_enabled' => true,
        ]);
    }

    /**
     * Test 1: A loan cannot exist without an explicitly selected Deduction Type.
     */
    public function test_a_loan_cannot_exist_without_an_explicitly_selected_deduction_type(): void
    {
        $response = $this->actingAs($this->company1Admin)
            ->post(route('loan-types.store'), [
                'name' => 'Unmapped Loan Config',
                'default_amortization_per_cutoff' => 500,
            ]);

        $response->assertSessionHasErrors(['deduction_type_id']);
        $this->assertDatabaseMissing('loan_types', ['name' => 'Unmapped Loan Config']);
    }

    /**
     * Test 2: Creating a loan configuration does not create another Deduction Type.
     */
    public function test_creating_a_loan_configuration_does_not_create_another_deduction_type(): void
    {
        $initialCount = DeductionType::withoutGlobalScopes()->count();

        $response = $this->actingAs($this->company1Admin)
            ->post(route('loan-types.store'), [
                'name' => 'Emergency Assistance Config',
                'deduction_type_id' => $this->company1DeductionType->id,
                'default_amortization_per_cutoff' => 500,
            ]);

        $response->assertSessionHasNoErrors();

        $finalCount = DeductionType::withoutGlobalScopes()->count();
        $this->assertEquals($initialCount, $finalCount, 'Creating loan configuration metadata must NOT create a new DeductionType record.');
    }

    /**
     * Test 3: The Loan Type name has no effect on which payroll deduction is used.
     */
    public function test_the_loan_type_name_has_no_effect_on_which_payroll_deduction_is_used(): void
    {
        $loanType = LoanType::create([
            'company_id' => $this->company1->id,
            'name' => 'Random Completely Unrelated Title XYZ',
            'deduction_type_id' => $this->company1DeductionType->id,
            'default_amortization_per_cutoff' => 450.00,
            'is_active' => true,
        ]);

        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee1->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 4500.00,
            'current_balance' => 4500.00,
            'per_cutoff_amount' => 450.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company1->id,
            'period_name' => 'Test Period Unrelated Name',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($period, [$this->employee1->id]);
        $payHeader = $drafts->first();

        $payOut = $payHeader->payOuts->firstWhere('loan_id', $empLoan->id);
        $this->assertNotNull($payOut);
        $this->assertEquals($this->company1DeductionType->id, $payOut->deduction_type_id);
    }

    /**
     * Test 4: Renaming the loan configuration does not change installment posting.
     */
    public function test_renaming_the_loan_configuration_does_not_change_installment_posting(): void
    {
        $loanType = LoanType::create([
            'company_id' => $this->company1->id,
            'name' => 'Initial Config Name',
            'deduction_type_id' => $this->company1DeductionType->id,
            'default_amortization_per_cutoff' => 600.00,
            'is_active' => true,
        ]);

        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee1->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 6000.00,
            'current_balance' => 6000.00,
            'per_cutoff_amount' => 600.00,
            'status' => 'ACTIVE',
        ]);

        $loanType->update(['name' => 'Renamed Config Name Super Loan']);

        $period = PayrollPeriod::create([
            'company_id' => $this->company1->id,
            'period_name' => 'Test Period Rename Config',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($period, [$this->employee1->id]);
        $payHeader = $drafts->first();

        $payOut = $payHeader->payOuts->firstWhere('loan_id', $empLoan->id);
        $this->assertNotNull($payOut);
        $this->assertEquals($this->company1DeductionType->id, $payOut->deduction_type_id);
    }

    /**
     * Test 5: Renaming the linked Deduction Type preserves the relationship by ID.
     */
    public function test_renaming_the_linked_deduction_type_preserves_the_relationship_by_id(): void
    {
        $loanType = LoanType::create([
            'company_id' => $this->company1->id,
            'name' => 'Loan Config Standard',
            'deduction_type_id' => $this->company1DeductionType->id,
            'default_amortization_per_cutoff' => 800.00,
            'is_active' => true,
        ]);

        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee1->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 8000.00,
            'current_balance' => 8000.00,
            'per_cutoff_amount' => 800.00,
            'status' => 'ACTIVE',
        ]);

        $this->company1DeductionType->update(['name' => 'Completely Different Deduction Label']);

        $period = PayrollPeriod::create([
            'company_id' => $this->company1->id,
            'period_name' => 'Test Period Rename Deduction',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($period, [$this->employee1->id]);
        $payHeader = $drafts->first();

        $payOut = $payHeader->payOuts->firstWhere('loan_id', $empLoan->id);
        $this->assertNotNull($payOut);
        $this->assertEquals($this->company1DeductionType->id, $payOut->deduction_type_id);
    }

    /**
     * Test 6: Every installment posts through the linked Deduction Type.
     */
    public function test_every_installment_posts_through_the_linked_deduction_type(): void
    {
        $loanType = LoanType::create([
            'company_id' => $this->company1->id,
            'name' => 'Installment Test Loan',
            'deduction_type_id' => $this->company1DeductionType->id,
            'default_amortization_per_cutoff' => 1000.00,
            'is_active' => true,
        ]);

        $empLoan = EmpLoan::create([
            'employee_id' => $this->employee1->id,
            'loan_type_id' => $loanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'per_cutoff_amount' => 1000.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company1->id,
            'period_name' => 'Test Period Installment Posting',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $drafts = $service->generateDraft($period, [$this->employee1->id]);
        $payHeader = $drafts->first();

        $payOut = $payHeader->payOuts->firstWhere('loan_id', $empLoan->id);
        $this->assertNotNull($payOut);
        $this->assertEquals($this->company1DeductionType->id, $payOut->deduction_type_id);
        $this->assertEquals(1000.00, (float)$payOut->rate);
    }

    /**
     * Test 7: An ordinary Deduction Type without loan tracking cannot be used as a loan until explicitly enabled.
     */
    public function test_an_ordinary_deduction_type_without_loan_tracking_cannot_be_used_as_a_loan_until_explicitly_enabled(): void
    {
        $ordinaryDeduction = DeductionType::create([
            'company_id' => $this->company1->id,
            'name' => 'Ordinary Uniform Deduction',
            'code' => 'UNIFORM_DED',
            'type' => 'deduction',
            'is_active' => true,
            'is_loan_enabled' => false,
        ]);

        $disabledLoanType = LoanType::create([
            'company_id' => $this->company1->id,
            'deduction_type_id' => $ordinaryDeduction->id,
            'name' => 'Uniform Loan Config',
            'default_amortization_per_cutoff' => 200.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->company1Admin)
            ->post(route('loan-monitor.store-loan'), [
                'employee_id' => $this->employee1->id,
                'loan_type_id' => $disabledLoanType->id,
                'principal_amount' => 1000.00,
                'start_date' => '2026-08-01',
                'purpose' => 'Uniform Purchase',
                'terms_months' => 5,
                'monthly_interest_rate' => 0.00,
            ]);

        $response->assertSessionHasErrors(['loan_type_id'], null, 'addLoan');
        $this->assertDatabaseMissing('emp_loans', ['employee_id' => $this->employee1->id, 'principal_amount' => 1000.00]);

        $ordinaryDeduction->update(['is_loan_enabled' => true]);

        $responseSuccess = $this->actingAs($this->company1Admin)
            ->post(route('loan-monitor.store-loan'), [
                'employee_id' => $this->employee1->id,
                'loan_type_id' => $disabledLoanType->id,
                'principal_amount' => 1000.00,
                'start_date' => '2026-08-01',
                'purpose' => 'Uniform Purchase',
                'terms_months' => 5,
                'monthly_interest_rate' => 0.00,
            ]);

        $responseSuccess->assertSessionHasNoErrors();
        $this->assertDatabaseHas('emp_loans', ['employee_id' => $this->employee1->id, 'principal_amount' => 1000.00]);
    }

    /**
     * Test 8: Existing unmapped loan records are blocked and presented for manual mapping.
     */
    public function test_existing_unmapped_loan_records_are_blocked_and_presented_for_manual_mapping(): void
    {
        $legacyLoanType = LoanType::create([
            'company_id' => $this->company1->id,
            'name' => 'Legacy Unmapped Loan Record',
            'deduction_type_id' => $this->company1DeductionType->id,
            'default_amortization_per_cutoff' => 300.00,
            'is_active' => true,
        ]);
        DB::table('loan_types')->where('id', $legacyLoanType->id)->update(['deduction_type_id' => null]);

        EmpLoan::create([
            'employee_id' => $this->employee1->id,
            'loan_type_id' => $legacyLoanType->id,
            'principal_amount' => 3000.00,
            'current_balance' => 3000.00,
            'per_cutoff_amount' => 300.00,
            'status' => 'ACTIVE',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->company1->id,
            'period_name' => 'Test Period Legacy Block',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'cutoff_number' => 1,
            'status' => 'open',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Legacy Unmapped Loan Record.*not mapped to a Deduction Type/i');

        $service = new PayrollProcessingService();
        $service->generateDraft($period, [$this->employee1->id]);
    }

    /**
     * Test 9: Direct LoanType creation without deduction_type_id fails without creating either a Loan Type or Deduction Type.
     */
    public function test_direct_loan_type_creation_without_deduction_type_id_fails_without_creating_records(): void
    {
        $initialLoanTypeCount = LoanType::withoutGlobalScopes()->count();
        $initialDeductionTypeCount = DeductionType::withoutGlobalScopes()->count();

        $failed = false;
        try {
            LoanType::create(['name' => 'Any Unmapped Loan']);
        } catch (\Throwable $e) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Direct LoanType creation without deduction_type_id must fail.');

        $this->assertEquals($initialLoanTypeCount, LoanType::withoutGlobalScopes()->count());
        $this->assertEquals($initialDeductionTypeCount, DeductionType::withoutGlobalScopes()->count());
    }

    /**
     * Test 10: Deduction Type is NOT marked loan-enabled merely because its code contains a known loan keyword.
     */
    public function test_deduction_type_is_not_marked_loan_enabled_merely_because_of_code_keywords(): void
    {
        $deductionWithKeyword = DeductionType::create([
            'company_id' => $this->company1->id,
            'name' => 'Custom SSS Loan Deduction',
            'code' => 'CUSTOM_SSS_LOAN_CODE',
            'type' => 'deduction',
            'is_active' => true,
            'is_loan_enabled' => false,
        ]);

        $this->assertFalse((bool)$deductionWithKeyword->is_loan_enabled, 'DeductionType must remain is_loan_enabled = false by default even if code contains loan keyword.');
    }

    /**
     * Test 11: Direct LoanType update clearing deduction_type_id fails.
     */
    public function test_direct_loan_type_update_clearing_deduction_type_id_fails(): void
    {
        $loanType = LoanType::create([
            'company_id' => $this->company1->id,
            'name' => 'Valid Mapped Loan',
            'deduction_type_id' => $this->company1DeductionType->id,
        ]);

        $failed = false;
        try {
            $loanType->update(['deduction_type_id' => null]);
        } catch (\Throwable $e) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Updating LoanType to clear deduction_type_id must fail.');
        $loanType->refresh();
        $this->assertEquals($this->company1DeductionType->id, $loanType->deduction_type_id);
    }
}
