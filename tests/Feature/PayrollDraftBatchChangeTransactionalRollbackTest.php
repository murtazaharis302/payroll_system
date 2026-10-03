<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollBatchChangeService;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\MenusTableSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollDraftBatchChangeTransactionalRollbackTest extends TestCase
{
    use RefreshDatabase;

    private User $companyAdmin;
    private Company $company;
    private PayrollPeriod $period;
    private Department $dept;
    private IncomeType $allowanceType;
    private DeductionType $valeDeductionType;
    private LoanType $loanType;
    private EmployeeMaster $emp1;
    private EmployeeMaster $emp2;
    private PayHeader $draft1;
    private PayHeader $draft2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);
        $this->seed(MenusTableSeeder::class);

        Role::firstOrCreate(['id' => Role::COMPANY_ADMIN, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => Role::HR, 'name' => 'HR']);

        $this->companyAdmin = User::create([
            'name' => 'Admin Rollback',
            'email' => 'admin@rollback.test',
            'password' => Hash::make('secret'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Rollback Corp',
            'company_code' => 'ROLLBACK',
            'email' => 'rollback@test.com',
            'status' => '1',
        ]);
        $this->companyAdmin->update(['company_id' => $this->company->id]);

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
            'pay_date' => '2026-08-20',
            'status' => 'open',
        ]);

        $this->allowanceType = IncomeType::create([
            'company_id' => $this->company->id,
            'code' => 'TEST_ALLOWANCE',
            'name' => 'Test Allowance',
            'is_active' => true,
        ]);

        $this->valeDeductionType = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'VALE_DED',
            'name' => 'Vale / Cash Advance',
            'is_active' => true,
            'is_loan_enabled' => true,
            'type' => 'loan',
        ]);

        $this->loanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $this->valeDeductionType->id,
            'name' => 'Emergency Cash Advance',
            'default_amortization_per_cutoff' => 500,
            'is_active' => true,
        ]);

        $this->emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ROLL-01',
            'first_name' => 'Dave',
            'last_name' => 'Miller',
            'monthly_rate' => 26000,
            'daily_rate' => 1000,
            'hourly_rate' => 125,
            'status' => 'active',
        ]);

        $this->emp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ROLL-02',
            'first_name' => 'Eva',
            'last_name' => 'Green',
            'monthly_rate' => 26000,
            'daily_rate' => 1000,
            'hourly_rate' => 125,
            'status' => 'active',
        ]);

        $this->draft1 = $this->createDraftForEmployee($this->emp1, 10);
        $this->draft2 = $this->createDraftForEmployee($this->emp2, 10);
    }

    private function createDraftForEmployee(EmployeeMaster $emp, int $days): PayHeader
    {
        $draft = PayHeader::create([
            'employee_id' => $emp->id,
            'payroll_period_id' => $this->period->id,
            'status' => 'draft',
            'days_worked' => $days,
            'lock_version' => 1,
        ]);

        $basic = IncomeType::where('code', 'BASIC')->firstOrFail();
        PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $basic->id,
            'number_input' => $days,
            'rate' => 1000,
        ]);
        $draft->recalculateTotals();

        return $draft;
    }

    public function test_failure_after_earlier_draft_writes_rolls_back_entire_batch(): void
    {
        $draft1OriginalPayInsCount = $this->draft1->payIns()->count();
        $draft1OriginalGross = (float) $this->draft1->total_gross_pay;
        $failingDraftId = $this->draft2->id;

        // Custom processing service that fails on the second draft
        $mockProcessing = new class(
            app(\App\Services\StatutoryCalculationService::class),
            app(\App\Services\PayrollWorkflowService::class),
            $failingDraftId
        ) extends PayrollProcessingService {
            public function __construct(
                \App\Services\StatutoryCalculationService $calc,
                \App\Services\PayrollWorkflowService $wf,
                private int $failingDraftId
            ) {
                parent::__construct($calc, $wf);
            }

            public function resolveAddedIncomeLine(PayHeader $draft, IncomeType $incomeType, float $numberInput, mixed $rateOverride = null): array
            {
                if ((int) $draft->id === $this->failingDraftId) {
                    throw new \RuntimeException('Simulated runtime failure during draft 2 processing');
                }

                return parent::resolveAddedIncomeLine($draft, $incomeType, $numberInput, $rateOverride);
            }
        };

        $this->app->instance(PayrollProcessingService::class, $mockProcessing);
        $service = app(PayrollBatchChangeService::class);

        try {
            $service->applyBatchChange(
                $this->period,
                [$this->allowanceType->id],
                [$this->allowanceType->id => ['number_input' => 1, 'rate' => 250.00]],
                [],
                [],
                [$this->draft1->id, $this->draft2->id],
                $this->companyAdmin
            );
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated runtime failure during draft 2 processing', $e->getMessage());
        }

        // Entire transaction must have rolled back: draft1 has 0 new lines and original gross
        $this->assertDatabaseMissing('pay_ins', [
            'pay_id' => $this->draft1->id,
            'income_type_id' => $this->allowanceType->id,
        ]);
        $this->assertEquals($draft1OriginalPayInsCount, $this->draft1->fresh()->payIns()->count());
        $this->assertEquals($draft1OriginalGross, (float) $this->draft1->fresh()->total_gross_pay);
    }

    public function test_concurrency_race_draft_approved_before_lock_aborts_batch(): void
    {
        $this->draft2->update(['status' => 'approved', 'approved_at' => now()]);

        $response = $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->from(route('company.payroll-drafts.batch-change.create'))
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'income_type_ids' => [$this->allowanceType->id],
                'draft_ids' => [$this->draft1->id, $this->draft2->id],
                'income_type_configs' => [
                    $this->allowanceType->id => ['number_input' => 1, 'rate' => 300.00],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('generation_errors');
        $this->assertStringContainsString("Status: record is finalized ('approved')", session('generation_errors')[0]);

        $this->assertDatabaseMissing('pay_ins', [
            'pay_id' => $this->draft1->id,
            'income_type_id' => $this->allowanceType->id,
        ]);
    }

    public function test_loan_quantity_is_forced_to_one_regardless_of_submitted_input(): void
    {
        $loan = EmpLoan::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->emp1->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 5000,
            'current_balance' => 5000,
            'per_cutoff_amount' => 500,
            'status' => 'ACTIVE',
            'start_date' => '2026-08-01',
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$this->valeDeductionType->id],
                'draft_ids' => [$this->draft1->id],
                'deduction_type_configs' => [
                    $this->valeDeductionType->id => ['number_input' => 5],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft1->id,
            'deduction_type_id' => $this->valeDeductionType->id,
            'loan_id' => $loan->id,
            'number_input' => 1.00,
            'rate' => 500.00,
        ]);
    }

    public function test_multiple_emp_deduction_records_selects_active_date_effective_assignment(): void
    {
        $clubDed = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'CLUB_FEE',
            'name' => 'Club Membership Fee',
            'is_active' => true,
            'type' => 'other',
        ]);

        \App\Models\EmpDeduction::create([
            'employee_id' => $this->emp1->id,
            'deduction_type_id' => $clubDed->id,
            'rate' => 100,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        \App\Models\EmpDeduction::create([
            'employee_id' => $this->emp1->id,
            'deduction_type_id' => $clubDed->id,
            'rate' => 750,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'is_active' => true,
        ]);

        $this->actingAs($this->companyAdmin)->withSession(['active_company_id' => $this->company->id])
            ->post(route('company.payroll-drafts.batch-change.store'), [
                'payroll_period_id' => $this->period->id,
                'deduction_type_ids' => [$clubDed->id],
                'draft_ids' => [$this->draft1->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pay_outs', [
            'pay_id' => $this->draft1->id,
            'deduction_type_id' => $clubDed->id,
            'rate' => 750.00,
        ]);
    }

    public function test_multi_type_failure_on_either_side_rolls_back_both_income_and_deduction_writes(): void
    {
        $draft1PayInsBefore = $this->draft1->payIns()->count();
        $draft1PayOutsBefore = $this->draft1->payOuts()->count();

        $customDeduction = DeductionType::create([
            'company_id' => $this->company->id,
            'code' => 'CUSTOM_FAIL_DED',
            'name' => 'Custom Fail Deduction',
            'is_active' => true,
            'type' => 'other',
        ]);

        // Simulate a failure during deduction write
        $mockProcessing = new class(
            app(\App\Services\StatutoryCalculationService::class),
            app(\App\Services\PayrollWorkflowService::class)
        ) extends PayrollProcessingService {
            public function resolveAddedDraftDeductionLine(
                PayHeader $draft,
                DeductionType $deductionType,
                ?\App\Models\EmpLoan $loan = null,
                ?float $numberInput = null,
                mixed $rateOverride = null
            ): array {
                throw new \RuntimeException('Simulated deduction failure during combined batch');
            }
        };

        $this->app->instance(PayrollProcessingService::class, $mockProcessing);
        $service = app(PayrollBatchChangeService::class);

        try {
            $service->applyBatchChange(
                $this->period,
                [$this->allowanceType->id],
                [$this->allowanceType->id => ['number_input' => 1, 'rate' => 200.00]],
                [$customDeduction->id],
                [$customDeduction->id => ['number_input' => 1, 'rate' => 100.00]],
                [$this->draft1->id],
                $this->companyAdmin
            );
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated deduction failure during combined batch', $e->getMessage());
        }

        // Must roll back everything: 0 pay_ins created and 0 pay_outs created
        $this->assertDatabaseMissing('pay_ins', [
            'pay_id' => $this->draft1->id,
            'income_type_id' => $this->allowanceType->id,
        ]);
        $this->assertDatabaseMissing('pay_outs', [
            'pay_id' => $this->draft1->id,
            'deduction_type_id' => $customDeduction->id,
        ]);
        $this->assertEquals($draft1PayInsBefore, $this->draft1->fresh()->payIns()->count());
        $this->assertEquals($draft1PayOutsBefore, $this->draft1->fresh()->payOuts()->count());
    }

    public function test_batch_changes_blade_preserves_both_income_and_deduction_checkboxes_on_rerender(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession([
                'active_company_id' => $this->company->id,
                '_old_input' => [
                    'income_type_ids' => [$this->allowanceType->id],
                    'deduction_type_ids' => [$this->valeDeductionType->id],
                ],
            ])
            ->get(route('company.payroll-drafts.batch-change.create', ['payroll_period_id' => $this->period->id]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('id="income_type_chk_' . $this->allowanceType->id . '"', $content);
        $this->assertStringContainsString('id="deduction_type_chk_' . $this->valeDeductionType->id . '"', $content);
    }
}
