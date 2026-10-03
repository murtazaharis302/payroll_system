<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanPayment;
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

class NegativeNetPayApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $employee1;
    protected EmployeeMaster $employee2;
    protected PayrollPeriod $period;
    protected DeductionType $valeDeductionType;
    protected LoanType $valeLoanType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->adminUser = User::create([
            'name' => 'Admin C10',
            'email' => 'admin_c10@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'C10 Test Company',
            'email' => 'c10@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->employee1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-C10-001',
            'first_name' => 'Alice',
            'last_name' => 'Positive',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $this->employee2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-C10-002',
            'first_name' => 'Bob',
            'last_name' => 'Negative',
            'monthly_rate' => 20000.00,
            'status' => 'active',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Period 2026-08-C10',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $this->valeDeductionType = DeductionType::withoutGlobalScopes()->where('code', 'VALE')->first();
        $this->valeDeductionType->update(['is_loan_enabled' => true]);

        $this->valeLoanType = LoanType::create([
            'company_id' => $this->company->id,
            'name' => 'Cash Advance / Vale',
            'deduction_type_id' => $this->valeDeductionType->id,
            'default_amortization_per_cutoff' => 1000.00,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Single approval is blocked when recalculated net_pay is negative.
     */
    public function test_single_approval_blocked_when_recalculated_net_pay_is_negative(): void
    {
        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee2->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 1000.00,
            'total_deductions' => 1500.00,
            'net_pay' => -500.00,
        ]);

        $basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        PayIn::create(['pay_id' => $draft->id, 'income_type_id' => $basicType->id, 'number_input' => 1.00, 'rate' => 1000.00]);

        $dedType = DeductionType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other', 'type' => 'deduction', 'is_active' => true]);
        PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $dedType->id, 'number_input' => 1.00, 'rate' => 1500.00]);

        $service = new PayrollProcessingService();

        $failed = false;
        try {
            $service->approvePayroll($draft, $this->adminUser);
        } catch (\RuntimeException $e) {
            $failed = true;
            $this->assertStringContainsString('EMP-C10-002', $e->getMessage());
            $this->assertStringContainsString('negative net income', $e->getMessage());
        }

        $this->assertTrue($failed, 'Approval must throw RuntimeException when net_pay < 0.');
        $draft->refresh();
        $this->assertEquals('pending_approval', $draft->status);
    }

    /**
     * Test 2: Single approval is permitted when net_pay is exactly zero.
     */
    public function test_single_approval_permitted_when_net_pay_is_exactly_zero(): void
    {
        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee1->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 1000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 0.00,
        ]);

        $basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        PayIn::create(['pay_id' => $draft->id, 'income_type_id' => $basicType->id, 'number_input' => 1.00, 'rate' => 1000.00]);

        $dedType = DeductionType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other', 'type' => 'deduction', 'is_active' => true]);
        PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $dedType->id, 'number_input' => 1.00, 'rate' => 1000.00]);

        $service = new PayrollProcessingService();
        $approvedDraft = $service->approvePayroll($draft, $this->adminUser);

        $this->assertEquals('approved', $approvedDraft->status);
        $this->assertEquals(0.00, (float) $approvedDraft->net_pay);
    }

    /**
     * Test 3: Recalculates totals from latest PayIn and PayOut records immediately before approval.
     */
    public function test_recalculates_totals_from_latest_pay_in_and_pay_out_immediately_before_approval(): void
    {
        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee2->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 2000.00,
            'total_deductions' => 500.00,
            'net_pay' => 1500.00, // Stored column shows positive net pay
        ]);

        $basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        PayIn::create(['pay_id' => $draft->id, 'income_type_id' => $basicType->id, 'number_input' => 1.00, 'rate' => 2000.00]);

        // Add a large deduction line directly to database making true net pay negative (-500)
        $dedType = DeductionType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other', 'type' => 'deduction', 'is_active' => true]);
        PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $dedType->id, 'number_input' => 1.00, 'rate' => 2500.00]);

        $service = new PayrollProcessingService();

        $failed = false;
        try {
            $service->approvePayroll($draft, $this->adminUser);
        } catch (\RuntimeException $e) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Real-time recalculation must detect negative net pay before approval.');
        $draft->refresh();
        $this->assertEquals('pending_approval', $draft->status);
    }

    /**
     * Test 4: Failed approval does not persist recalculated totals or status changes.
     */
    public function test_failed_approval_does_not_persist_recalculated_totals_or_status_changes(): void
    {
        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee2->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 2000.00,
            'total_deductions' => 500.00,
            'net_pay' => 1500.00,
        ]);

        $basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        PayIn::create(['pay_id' => $draft->id, 'income_type_id' => $basicType->id, 'number_input' => 1.00, 'rate' => 2000.00]);

        $dedType = DeductionType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other', 'type' => 'deduction', 'is_active' => true]);
        PayOut::create(['pay_id' => $draft->id, 'deduction_type_id' => $dedType->id, 'number_input' => 1.00, 'rate' => 3000.00]);

        $service = new PayrollProcessingService();

        try {
            $service->approvePayroll($draft, $this->adminUser);
        } catch (\RuntimeException $e) {
            // Expected
        }

        $draft->refresh();
        $this->assertEquals('pending_approval', $draft->status);
        $this->assertEquals(1500.00, (float) $draft->net_pay); // Rolled back cleanly to original value
    }

    /**
     * Test 5: When the second employee in a batch has negative net pay, all database changes created while approving the first employee must be rolled back.
     */
    public function test_batch_approval_rolls_back_all_first_employee_changes_when_second_employee_fails_due_to_negative_net_pay(): void
    {
        // First Employee: valid draft with active loan
        $loan1 = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee1->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $draft1 = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee1->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 2000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 1000.00,
        ]);

        $basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        PayIn::create(['pay_id' => $draft1->id, 'income_type_id' => $basicType->id, 'number_input' => 1.00, 'rate' => 2000.00]);
        PayOut::create(['pay_id' => $draft1->id, 'deduction_type_id' => $this->valeDeductionType->id, 'loan_id' => $loan1->id, 'number_input' => 1.00, 'rate' => 1000.00]);

        // Second Employee: invalid draft with negative net pay
        $loan2 = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee2->id,
            'loan_type_id' => $this->valeLoanType->id,
            'principal_amount' => 3000.00,
            'current_balance' => 3000.00,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);

        $draft2 = PayHeader::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee2->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'pending_approval',
            'total_gross_pay' => 1000.00,
            'total_deductions' => 1500.00,
            'net_pay' => -500.00,
        ]);

        PayIn::create(['pay_id' => $draft2->id, 'income_type_id' => $basicType->id, 'number_input' => 1.00, 'rate' => 1000.00]);
        PayOut::create(['pay_id' => $draft2->id, 'deduction_type_id' => $this->valeDeductionType->id, 'loan_id' => $loan2->id, 'number_input' => 1.00, 'rate' => 1500.00]);

        // Submit batch approval request containing both draft IDs
        $response = $this->actingAs($this->adminUser)
            ->post(route('payroll-approvals.batch-approve'), [
                'draft_ids' => [$draft1->id, $draft2->id],
            ]);

        $response->assertSessionHas('statusDanger');

        // Verify Draft 1 status remains pending_approval (rolled back!)
        $draft1->refresh();
        $this->assertEquals('pending_approval', $draft1->status);

        // Verify Draft 2 status remains pending_approval
        $draft2->refresh();
        $this->assertEquals('pending_approval', $draft2->status);

        // Verify NO LoanPayment was posted for employee 1 or 2
        $this->assertEquals(0, LoanPayment::where('emp_loan_id', $loan1->id)->count());
        $this->assertEquals(0, LoanPayment::where('emp_loan_id', $loan2->id)->count());

        // Verify Employee 1 loan balance was NOT deducted (remains 5000.00)
        $loan1->refresh();
        $this->assertEquals(5000.00, (float) $loan1->current_balance);
    }
}
