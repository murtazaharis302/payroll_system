<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernmentContributionStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected EmployeeMaster $employee;
    protected PayrollPeriod $period;
    protected DeductionType $sssDeduction;
    protected DeductionType $phDeduction;
    protected DeductionType $pagibigDeduction;
    protected IncomeType $basicIncome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\StatutoryRulesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Gov Contribution Test Company',
            'company_code' => 'GCTC',
            'email' => 'gctc@example.com',
            'status' => 1,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-GC-01',
            'first_name' => 'Gov',
            'last_name' => 'Employee',
            'status' => 'active',
            'monthly_rate' => 35000.00,
            'date_hired' => '2026-01-01',
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'frequency' => 'semi_monthly',
            'status' => 'open',
        ]);

        $this->basicIncome = IncomeType::where('code', 'BASIC')->first()
            ?? IncomeType::create([
                'company_id' => $this->company->id,
                'name' => 'Basic Pay',
                'code' => 'BASIC',
                'is_taxable' => true,
                'is_active' => true,
            ]);

        $this->sssDeduction = DeductionType::where('code', 'SSS')->first()
            ?? DeductionType::create([
                'company_id' => $this->company->id,
                'name' => 'SSS Contribution',
                'code' => 'SSS',
                'is_active' => true,
            ]);

        $this->phDeduction = DeductionType::where('code', 'PHILHEALTH')->first()
            ?? DeductionType::create([
                'company_id' => $this->company->id,
                'name' => 'PhilHealth Contribution',
                'code' => 'PHILHEALTH',
                'is_active' => true,
            ]);

        $this->pagibigDeduction = DeductionType::where('code', 'PAGIBIG')->first()
            ?? DeductionType::create([
                'company_id' => $this->company->id,
                'name' => 'Pag-IBIG Contribution',
                'code' => 'PAGIBIG',
                'is_active' => true,
            ]);
    }

    protected function createPayHeaderWithLines(string $status = 'draft', float $basicPay = 17500.00): PayHeader
    {
        $payHeader = PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'status' => $status,
            'total_gross_pay' => $basicPay,
            'total_deductions' => 0.00,
            'net_pay' => $basicPay,
            'lock_version' => 1,
        ]);

        PayIn::create([
            'pay_id' => $payHeader->id,
            'income_type_id' => $this->basicIncome->id,
            'number_input' => 1.00,
            'rate' => $basicPay,
        ]);

        PayOut::create([
            'pay_id' => $payHeader->id,
            'deduction_type_id' => $this->sssDeduction->id,
            'number_input' => 1.00,
            'rate' => 787.50,
        ]);

        PayOut::create([
            'pay_id' => $payHeader->id,
            'deduction_type_id' => $this->phDeduction->id,
            'number_input' => 1.00,
            'rate' => 437.50,
        ]);

        PayOut::create([
            'pay_id' => $payHeader->id,
            'deduction_type_id' => $this->pagibigDeduction->id,
            'number_input' => 1.00,
            'rate' => 100.00,
        ]);

        return $payHeader;
    }

    /**
     * Test newly generated/recalculated GovernmentContributions receive 'pending' status
     * when saving or recalculating a draft payroll header.
     */
    public function test_draft_payroll_synchronization_maps_contributions_to_pending(): void
    {
        $service = app(PayrollProcessingService::class);
        $draft = $this->createPayHeaderWithLines('draft');

        $service->syncDraftGovernmentContributions($draft);

        $contributions = GovernmentContribution::where('payroll_draft_id', $draft->id)->get();
        $this->assertCount(3, $contributions);

        foreach ($contributions as $contrib) {
            $this->assertSame(
                GovernmentContribution::STATUS_PENDING,
                $contrib->status,
                "GovernmentContribution {$contrib->type} status must be 'pending', not 'draft'."
            );
            $this->assertContains($contrib->status, GovernmentContribution::VALID_STATUSES);
        }
    }

    /**
     * Test statutory sync across all possible PayHeader statuses.
     * Ensures unsupported statuses like 'approved', 'pending_approval', 'posted', 'paid'
     * are never written to government_contributions.status column.
     */
    public function test_sync_government_contributions_never_writes_unsupported_pay_header_statuses(): void
    {
        $service = app(PayrollProcessingService::class);
        $testStatuses = ['draft', 'pending_approval', 'approved', 'posted', 'paid'];

        foreach ($testStatuses as $index => $headerStatus) {
            $emp = EmployeeMaster::create([
                'company_id' => $this->company->id,
                'employee_code' => 'EMP-STATUS-' . $index,
                'first_name' => 'Status',
                'last_name' => 'Test' . $index,
                'status' => 'active',
                'monthly_rate' => 35000.00,
                'date_hired' => '2026-01-01',
            ]);

            $header = PayHeader::create([
                'company_id' => $this->company->id,
                'payroll_period_id' => $this->period->id,
                'employee_id' => $emp->id,
                'status' => $headerStatus,
                'total_gross_pay' => 20000.00,
                'total_deductions' => 0.00,
                'net_pay' => 20000.00,
                'lock_version' => 1,
            ]);

            PayIn::create([
                'pay_id' => $header->id,
                'income_type_id' => $this->basicIncome->id,
                'number_input' => 1.00,
                'rate' => 20000.00,
            ]);

            PayOut::create([
                'pay_id' => $header->id,
                'deduction_type_id' => $this->sssDeduction->id,
                'number_input' => 1.00,
                'rate' => 900.00,
            ]);

            PayOut::create([
                'pay_id' => $header->id,
                'deduction_type_id' => $this->phDeduction->id,
                'number_input' => 1.00,
                'rate' => 500.00,
            ]);

            PayOut::create([
                'pay_id' => $header->id,
                'deduction_type_id' => $this->pagibigDeduction->id,
                'number_input' => 1.00,
                'rate' => 100.00,
            ]);

            // Execute sync
            $service->syncDraftGovernmentContributions($header);

            $contributions = GovernmentContribution::where('payroll_draft_id', $header->id)->get();
            $this->assertCount(3, $contributions, "Header with status {$headerStatus} should have 3 contributions");

            foreach ($contributions as $contrib) {
                $this->assertSame(
                    GovernmentContribution::STATUS_PENDING,
                    $contrib->status,
                    "Expected contribution status 'pending' for PayHeader status '{$headerStatus}', got '{$contrib->status}'."
                );
                $this->assertNotSame(
                    $headerStatus === 'pending' ? 'invalid' : $headerStatus,
                    $contrib->status,
                    "PayHeader status '{$headerStatus}' must not be directly copied to contribution status."
                );
                $this->assertContains($contrib->status, GovernmentContribution::VALID_STATUSES);
            }
        }
    }

    /**
     * Test that statutory synchronization preserves valid existing contribution lifecycle statuses (e.g. submitted, paid).
     */
    public function test_statutory_recalculation_preserves_valid_existing_lifecycle_statuses(): void
    {
        $service = app(PayrollProcessingService::class);
        $draft = $this->createPayHeaderWithLines('draft');

        // Pre-create SSS as 'submitted' and PhilHealth as 'paid'
        GovernmentContribution::create([
            'payroll_draft_id' => $draft->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'payroll_period_id' => $this->period->id,
            'type' => 'sss',
            'employee_share' => 700.00,
            'employer_share' => 1400.00,
            'total_contribution' => 2100.00,
            'period_start' => $this->period->period_start,
            'period_end' => $this->period->period_end,
            'status' => GovernmentContribution::STATUS_SUBMITTED,
        ]);

        GovernmentContribution::create([
            'payroll_draft_id' => $draft->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'payroll_period_id' => $this->period->id,
            'type' => 'philhealth',
            'employee_share' => 400.00,
            'employer_share' => 400.00,
            'total_contribution' => 800.00,
            'period_start' => $this->period->period_start,
            'period_end' => $this->period->period_end,
            'status' => GovernmentContribution::STATUS_PAID,
        ]);

        // Recalculate
        $service->syncDraftGovernmentContributions($draft);

        $sss = GovernmentContribution::where('payroll_draft_id', $draft->id)->where('type', 'sss')->first();
        $ph = GovernmentContribution::where('payroll_draft_id', $draft->id)->where('type', 'philhealth')->first();
        $pagibig = GovernmentContribution::where('payroll_draft_id', $draft->id)->where('type', 'pagibig')->first();

        $this->assertNotNull($sss);
        $this->assertSame(GovernmentContribution::STATUS_SUBMITTED, $sss->status);

        $this->assertNotNull($ph);
        $this->assertSame(GovernmentContribution::STATUS_PAID, $ph->status);

        $this->assertNotNull($pagibig);
        $this->assertSame(GovernmentContribution::STATUS_PENDING, $pagibig->status);
    }

    /**
     * Test full draft update flow via controller endpoint.
     */
    public function test_payroll_draft_edit_save_endpoint_persists_valid_contribution_status(): void
    {
        $this->actingAs($this->adminUser);

        $draft = $this->createPayHeaderWithLines('draft', 15000.00);

        $basicPayIn = $draft->payIns()->first();
        $sssPayOut = $draft->payOuts()->where('deduction_type_id', $this->sssDeduction->id)->first();
        $phPayOut = $draft->payOuts()->where('deduction_type_id', $this->phDeduction->id)->first();
        $pagibigPayOut = $draft->payOuts()->where('deduction_type_id', $this->pagibigDeduction->id)->first();

        $payload = [
            'incomes' => [
                $basicPayIn->id => [
                    'rate' => 18000.00,
                    'number_input' => 1.00,
                ],
            ],
            'deductions' => [
                $sssPayOut->id => [
                    'rate' => 810.00,
                    'number_input' => 1.00,
                ],
                $phPayOut->id => [
                    'rate' => 450.00,
                    'number_input' => 1.00,
                ],
                $pagibigPayOut->id => [
                    'rate' => 100.00,
                    'number_input' => 1.00,
                ],
            ],
            'days_worked' => 11,
            'days_absent' => 0,
            'overtime_hours' => 0,
            'late_hours' => 0,
        ];

        $response = $this->put(route('payroll-drafts.update', ['id' => $draft->id]), $payload);
        $response->assertStatus(302);
        $response->assertSessionHas('status', 'Payroll draft saved successfully.');

        $contributions = GovernmentContribution::where('payroll_draft_id', $draft->id)->get();
        $this->assertCount(3, $contributions);

        foreach ($contributions as $contrib) {
            $this->assertSame(GovernmentContribution::STATUS_PENDING, $contrib->status);
        }
    }

    /**
     * Test model helper normalizeStatus and scopes.
     */
    public function test_model_normalize_status_and_scopes(): void
    {
        $this->assertSame(GovernmentContribution::STATUS_PENDING, GovernmentContribution::normalizeStatus(null));
        $this->assertSame(GovernmentContribution::STATUS_PENDING, GovernmentContribution::normalizeStatus('invalid_status'));
        $this->assertSame(GovernmentContribution::STATUS_PENDING, GovernmentContribution::normalizeStatus('draft'));
        $this->assertSame(GovernmentContribution::STATUS_PENDING, GovernmentContribution::normalizeStatus('approved'));
        $this->assertSame(GovernmentContribution::STATUS_PENDING, GovernmentContribution::normalizeStatus('pending'));
        $this->assertSame(GovernmentContribution::STATUS_SUBMITTED, GovernmentContribution::normalizeStatus('submitted'));
        $this->assertSame(GovernmentContribution::STATUS_PAID, GovernmentContribution::normalizeStatus('paid'));
        $this->assertSame(GovernmentContribution::STATUS_PAID, GovernmentContribution::normalizeStatus('PAID'));

        $draft = $this->createPayHeaderWithLines('draft');

        GovernmentContribution::create([
            'payroll_draft_id' => $draft->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'payroll_period_id' => $this->period->id,
            'type' => 'sss',
            'employee_share' => 100.00,
            'employer_share' => 200.00,
            'total_contribution' => 300.00,
            'period_start' => $this->period->period_start,
            'period_end' => $this->period->period_end,
            'status' => GovernmentContribution::STATUS_PENDING,
        ]);

        GovernmentContribution::create([
            'payroll_draft_id' => $draft->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'payroll_period_id' => $this->period->id,
            'type' => 'philhealth',
            'employee_share' => 100.00,
            'employer_share' => 200.00,
            'total_contribution' => 300.00,
            'period_start' => $this->period->period_start,
            'period_end' => $this->period->period_end,
            'status' => GovernmentContribution::STATUS_SUBMITTED,
        ]);

        GovernmentContribution::create([
            'payroll_draft_id' => $draft->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'payroll_period_id' => $this->period->id,
            'type' => 'pagibig',
            'employee_share' => 100.00,
            'employer_share' => 200.00,
            'total_contribution' => 300.00,
            'period_start' => $this->period->period_start,
            'period_end' => $this->period->period_end,
            'status' => GovernmentContribution::STATUS_PAID,
        ]);

        $this->assertEquals(1, GovernmentContribution::pending()->count());
        $this->assertEquals(1, GovernmentContribution::submitted()->count());
        $this->assertEquals(1, GovernmentContribution::paid()->count());
    }
}
