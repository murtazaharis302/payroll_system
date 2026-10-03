<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use App\Services\Reports\PayrollSummaryReportService;
use App\Support\Payroll\DeductionReportResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAbsenceDeductionReportSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Department $dept;
    protected PayrollPeriod $period1;
    protected PayrollPeriod $period2;
    protected User $adminUser;
    protected DeductionType $absType;
    protected \App\Models\PayrollPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);
        $this->seed(\Database\Seeders\StatutoryRulesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);

        $this->adminUser = User::create([
            'name' => 'Admin Absence Test',
            'email' => 'admin_abs_sync@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Absence Test Corp',
            'company_code' => 'ATC',
            'email' => 'atc@test.com',
            'status' => '1',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $this->policy = \App\Models\PayrollPolicy::create([
            'company_id' => $this->company->id,
            'policy_name' => 'Standard Policy',
            'pay_frequency' => 'semi_monthly',
            'workdays_per_month' => 26.00,
            'hours_per_day' => 8.00,
            'regular_ot_multiplier' => 1.25,
            'rest_day_ot_multiplier' => 1.30,
            'holiday_ot_multiplier' => 2.00,
            'rounding_mode' => 'round_half_up',
            'no_attendance_policy' => 'zero_pay_requires_review',
            'effective_date' => '2020-01-01',
            'is_active' => true,
        ]);

        $this->period1 = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-P01',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'cutoff_number' => 1,
            'frequency' => 'semi_monthly',
            'status' => 'open',
        ]);

        $this->period2 = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => '2026-P02',
            'period_start' => '2026-01-16',
            'period_end' => '2026-01-31',
            'pay_date' => '2026-02-05',
            'cutoff_number' => 2,
            'frequency' => 'semi_monthly',
            'status' => 'open',
        ]);

        $this->absType = DeductionType::withoutGlobalScopes()->where('code', 'ABSENCES')->firstOrFail();

        session(['active_company_id' => $this->company->id]);
    }

    /**
     * Requirement: Exact saved ABSENCES amounts are reflected identically in Summary and Deduction Report Resolver.
     */
    public function test_payroll_generation_with_varying_absence_days_and_rates_matches_summaries(): void
    {
        $emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-1',
            'first_name' => 'One',
            'last_name' => 'Day',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $emp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-2',
            'first_name' => 'Two',
            'last_name' => 'Days',
            'monthly_rate' => 52000.00,
            'status' => 'active',
        ]);

        $emp3 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ABS-3',
            'first_name' => 'ThreeHalf',
            'last_name' => 'Days',
            'monthly_rate' => 39000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        // Generate drafts with explicit ABSENCES deductions
        $h1 = $service->generateDraft($this->period1, [$emp1->id], [
            'use_fixed_amount' => true,
            'days_absent' => 1.0,
            'deductions' => [['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 1000.00]],
        ])->first();

        $h2 = $service->generateDraft($this->period1, [$emp2->id], [
            'use_fixed_amount' => true,
            'days_absent' => 2.0,
            'deductions' => [['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 4000.00]],
        ])->first();

        $h3 = $service->generateDraft($this->period1, [$emp3->id], [
            'use_fixed_amount' => true,
            'days_absent' => 3.5,
            'deductions' => [['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 5250.00]],
        ])->first();

        // Check PayOut lines
        $po1 = PayOut::where('pay_id', $h1->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($po1);
        $this->assertEquals(1.0, (float)$po1->number_input);
        $this->assertEquals(1000.00, (float)$po1->rate);

        $po2 = PayOut::where('pay_id', $h2->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($po2);
        $this->assertEquals(1.0, (float)$po2->number_input);
        $this->assertEquals(4000.00, (float)$po2->rate);

        $po3 = PayOut::where('pay_id', $h3->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($po3);
        $this->assertEquals(1.0, (float)$po3->number_input);
        $this->assertEquals(5250.00, (float)$po3->rate);

        // Check DeductionReportResolver
        $this->assertEquals(1000.00, DeductionReportResolver::resolveAmountForHeader($h1, 'absences_deduction'));
        $this->assertEquals(4000.00, DeductionReportResolver::resolveAmountForHeader($h2, 'absences_deduction'));
        $this->assertEquals(5250.00, DeductionReportResolver::resolveAmountForHeader($h3, 'absences_deduction'));

        // Check PayrollSummaryReportService Summary calculations
        $summaryService = new PayrollSummaryReportService();
        $summaryResult = $summaryService->build($this->period1, $this->company, $this->company->id);
        $this->assertEquals(10250.00, (float)($summaryResult['totals']['displayed_deductions'][$this->absType->id] ?? 0));
        $this->assertTrue((float)($summaryResult['totals']['total_deductions'] ?? 0) >= 10250.00);
    }

    /**
     * Requirement: Draft edited afterward does not alter ABSENCES PayOut amount or quantity.
     */
    public function test_draft_edited_synchronizes_absence_quantity_and_preserves_rate_override(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-DRAFT-EDIT',
            'first_name' => 'Draft',
            'last_name' => 'Editor',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period1, [$emp->id], [
            'use_fixed_amount' => true,
            'days_absent' => 1.0,
            'deductions' => [['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 1200.00]],
        ])->first();

        $absPayOut = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($absPayOut);
        $this->assertEquals(1.0, (float)$absPayOut->number_input);
        $this->assertEquals(1200.00, (float)$absPayOut->rate);

        // Edit draft attendance: change Days Absent to 3.0 via PUT
        $response = $this->actingAs($this->adminUser)->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 10,
            'days_absent' => 3.0,
            'late_hours' => 0,
            'undertime_hours' => 0,
            'undertime_deduction' => 0,
            'overtime_hours' => 0,
        ]);
        $response->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertEquals(3.0, (float)$draft->days_absent);

        $updatedPayOut = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertEquals(1.0, (float)$updatedPayOut->number_input);
        $this->assertEquals(1200.00, (float)$updatedPayOut->rate);
        $this->assertEquals(1200.00, (float)($updatedPayOut->rate * $updatedPayOut->number_input));
    }

    /**
     * Requirement: When ABSENCES is manually added from the deduction grid:
     * - Backend enforces number_input = 1.00 for ABSENCES.
     * - Adding ABSENCES does not modify PayHeader.days_absent.
     */
    public function test_manual_add_absences_from_grid_validation_and_synchronization(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-GRID-ADD',
            'first_name' => 'Grid',
            'last_name' => 'Add',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        // Generate without absences
        $draft = $service->generateDraft($this->period1, [$emp->id], ['days_absent' => 0.0])->first();

        // 1. Adding with rate 1000.00 creates line with number_input=1.0 and rate 1000.00
        $res1 = $this->actingAs($this->adminUser)->post(route('admin.payroll-drafts.add-deduction', $draft->id), [
            'deduction_type_id' => $this->absType->id,
            'rate' => 1000.00,
        ]);
        $res1->assertSessionHasNoErrors();

        $po0 = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($po0);
        $this->assertEquals(1.0, (float)$po0->number_input);
        $this->assertEquals(1000.00, (float)$po0->rate);

        // 2. Updating draft with days_absent 2.5 does NOT change ABSENCES PayOut quantity
        $response = $this->actingAs($this->adminUser)->put(route('admin.payroll-drafts.update', $draft->id), [
            'days_worked' => 10,
            'days_absent' => 2.5,
            'late_hours' => 0,
            'undertime_hours' => 0,
            'undertime_deduction' => 0,
            'overtime_hours' => 0,
        ]);
        $response->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertEquals(2.5, (float)$draft->days_absent);

        $po = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->first();
        $this->assertNotNull($po);
        $this->assertEquals(1.0, (float)$po->number_input);
        $this->assertEquals(1000.00, (float)$po->rate);
    }

    /**
     * Requirement: Zero days absent produces zero deduction and does not default to 1 day.
     */
    public function test_zero_days_absent_does_not_create_absence_deduction(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-ZERO-2',
            'first_name' => 'Zero',
            'last_name' => 'Two',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();
        $draft = $service->generateDraft($this->period1, [$emp->id], ['days_absent' => 0.0])->first();

        $absPayOuts = PayOut::where('pay_id', $draft->id)->where('deduction_type_id', $this->absType->id)->get();
        $this->assertTrue($absPayOuts->isEmpty() || $absPayOuts->sum(fn($p) => (float)($p->rate * $p->number_input)) == 0.0);
        $this->assertEquals(0.00, DeductionReportResolver::resolveAmountForHeader($draft, 'absences_deduction'));
    }

    /**
     * Requirement: Deduction Payroll History respects finalized-status rule
     * (draft/pending excluded, approved/posted/paid included) and reconciles against Payroll Summary.
     */
    public function test_deduction_payroll_history_reconciles_and_respects_status_scope(): void
    {
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-RECON',
            'first_name' => 'Recon',
            'last_name' => 'Emp',
            'monthly_rate' => 26000.00,
            'status' => 'active',
        ]);

        $service = new PayrollProcessingService();

        // Period 1: fixed ABSENCES 2000.00 (Draft status initially)
        $h1 = $service->generateDraft($this->period1, [$emp->id], [
            'use_fixed_amount' => true,
            'days_absent' => 2.0,
            'deductions' => [['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 2000.00]],
        ])->first();

        // Period 2: fixed ABSENCES 3000.00 (Approved)
        $h2 = $service->generateDraft($this->period2, [$emp->id], [
            'use_fixed_amount' => true,
            'days_absent' => 3.0,
            'deductions' => [['deduction_type' => 'ABSENCES', 'display' => true, 'amount' => 3000.00]],
        ])->first();
        $h2->update(['status' => 'approved']);

        // Request deduction payroll history covering period 1 and 2
        $response = $this->actingAs($this->adminUser)->post(route('reports.deduction-payroll-history'), [
            'from_period_id' => $this->period1->id,
            'to_period_id' => $this->period2->id,
            'company_id' => $this->company->id,
            'export_format' => 'xlsx',
        ]);
        $response->assertStatus(200);

        // Verify Draft period 1 is excluded and Approved period 2 is included
        $queryPhs = PayHeader::with(['payOuts.deductionType'])
            ->whereIn('payroll_period_id', [$this->period1->id, $this->period2->id])
            ->whereIn('status', PayHeader::FINAL_REPORT_STATUSES)
            ->get();

        $this->assertCount(1, $queryPhs);
        $this->assertEquals($h2->id, $queryPhs->first()->id);
        $this->assertEquals(3000.00, DeductionReportResolver::resolveAmountForHeader($queryPhs->first(), 'absences_deduction'));

        // Now approve Period 1 and verify both periods resolve correctly
        $h1->update(['status' => 'approved']);
        $queryBoth = PayHeader::with(['payOuts.deductionType'])
            ->whereIn('payroll_period_id', [$this->period1->id, $this->period2->id])
            ->whereIn('status', PayHeader::FINAL_REPORT_STATUSES)
            ->get();

        $this->assertCount(2, $queryBoth);
        $totalAbsHistory = $queryBoth->sum(fn($ph) => DeductionReportResolver::resolveAmountForHeader($ph, 'absences_deduction'));
        $this->assertEquals(5000.00, $totalAbsHistory);
    }
}
