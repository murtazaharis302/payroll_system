<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
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
use App\Models\StatutoryRule;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Database\Seeders\StatutoryRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollDraftAddEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Company $company;
    private Department $department;
    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => Role::SUPER_ADMIN, 'name' => 'SuperAdmin']);
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
        $this->seed(StatutoryRulesSeeder::class);

        $this->admin = User::factory()->create(['role' => 'admin', 'role_id' => Role::SUPER_ADMIN]);
        $this->company = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Add Employee Payroll Co',
            'company_code' => 'ADD-EMP',
            'email' => 'add-employee@example.test',
            'status' => 1,
        ]);
        $this->department = Department::create([
            'company_id' => $this->company->id,
            'code' => 'PAY',
            'name' => 'Payroll',
            'is_active' => true,
        ]);
        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 First Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);
    }

    public function test_adding_employee_creates_only_the_new_draft_and_preserves_existing_manual_inputs(): void
    {
        $employeeA = $this->employee('EMP-A', 'Existing', 'Employee', 500.00);
        $draftA = $this->generate($employeeA);
        $draftA->update(['days_worked' => 9, 'status' => 'approved']);

        $payInA = PayIn::withoutGlobalScopes()->where('pay_id', $draftA->id)->firstOrFail();
        $payOutA = PayOut::withoutGlobalScopes()->where('pay_id', $draftA->id)->firstOrFail();
        $payInA->update(['number_input' => 9, 'rate' => 543.21]);
        $payOutA->update(['number_input' => 2, 'rate' => 87.65]);
        $draftA->recalculateTotals();

        $headerSnapshot = $draftA->fresh()->only(['id', 'status', 'days_worked', 'total_gross_pay', 'total_deductions', 'net_pay']);
        $payInSnapshot = $this->linesFor(PayIn::class, $draftA->id);
        $payOutSnapshot = $this->linesFor(PayOut::class, $draftA->id);

        $employeeB = $this->employee('EMP-B', 'New', 'Employee', 600.00);

        $response = $this->actingAs($this->admin)->post(panelRoute('payroll-drafts.add-employee.store'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employeeB->id,
        ]);

        $draftB = PayHeader::withoutGlobalScopes()
            ->where('payroll_period_id', $this->period->id)
            ->where('employee_id', $employeeB->id)
            ->firstOrFail();

        $response->assertRedirect(panelRoute('payroll-drafts.edit', $draftB->id));
        $response->assertSessionHas('status', 'Added EMP-B - New Employee to this payroll period successfully.');
        $this->assertSame($headerSnapshot, $draftA->fresh()->only(array_keys($headerSnapshot)));
        $this->assertSame($payInSnapshot, $this->linesFor(PayIn::class, $draftA->id));
        $this->assertSame($payOutSnapshot, $this->linesFor(PayOut::class, $draftA->id));
        $this->assertSame('draft', $draftB->status);
        $this->assertGreaterThan(0, PayIn::withoutGlobalScopes()->where('pay_id', $draftB->id)->count());
        $this->assertGreaterThan(0, PayOut::withoutGlobalScopes()->where('pay_id', $draftB->id)->count());
        $this->assertSame(600.00, (float) PayIn::withoutGlobalScopes()
            ->where('pay_id', $draftB->id)
            ->where('income_type_id', IncomeType::where('code', 'BASIC')->value('id'))
            ->sole()
            ->rate);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'add_employee',
            'record_id' => $draftB->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_selector_requires_a_specific_open_period_and_excludes_ineligible_employees(): void
    {
        $eligible = $this->employee('EMP-ELIGIBLE', 'Eligible', 'Employee');
        $included = $this->employee('EMP-INCLUDED', 'Included', 'Employee');
        $this->generate($included);
        $inactive = $this->employee('EMP-INACTIVE', 'Inactive', 'Employee', 500.00, 'inactive');
        $noDepartment = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-NO-DEPT',
            'first_name' => 'No',
            'last_name' => 'Department',
            'daily_rate' => 500,
            'status' => 'active',
        ]);
        $otherCompany = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Other Company',
            'company_code' => 'OTHER-EMP',
            'email' => 'other-employee@example.test',
            'status' => 1,
        ]);
        $otherDepartment = Department::create([
            'company_id' => $otherCompany->id,
            'code' => 'OTHER',
            'name' => 'Other Department',
            'is_active' => true,
        ]);
        $otherEmployee = EmployeeMaster::create([
            'company_id' => $otherCompany->id,
            'dept_id' => $otherDepartment->id,
            'employee_code' => 'EMP-OTHER-CO',
            'first_name' => 'Other',
            'last_name' => 'Company',
            'daily_rate' => 500,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->get(panelRoute('payroll-drafts.index'))
            ->assertOk()
            ->assertDontSee('Add Employee');

        $this->get(panelRoute('payroll-drafts.index', ['payroll_period_id' => $this->period->id]))
            ->assertOk()
            ->assertSee('Add Employee');

        $form = $this->get(panelRoute('payroll-drafts.add-employee.create', ['payroll_period_id' => $this->period->id]));
        $form->assertOk()
            ->assertSee($eligible->employee_code)
            ->assertDontSee($included->employee_code)
            ->assertDontSee($inactive->employee_code)
            ->assertDontSee($noDepartment->employee_code)
            ->assertDontSee($otherEmployee->employee_code);

        $this->post(panelRoute('payroll-drafts.add-employee.store'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $noDepartment->id,
        ])->assertSessionHas('statusDanger', 'The selected employee is not eligible for this payroll period.');
    }

    public function test_duplicate_addition_is_prevented_without_replacing_the_existing_header(): void
    {
        $employee = $this->employee('EMP-DUP', 'Duplicate', 'Employee');
        $draft = $this->generate($employee);

        $this->actingAs($this->admin)
            ->post(panelRoute('payroll-drafts.add-employee.store'), [
                'payroll_period_id' => $this->period->id,
                'employee_id' => $employee->id,
            ])
            ->assertSessionHas('statusDanger', 'Employee is already included in this payroll period.');

        $this->assertSame($draft->id, PayHeader::withoutGlobalScopes()
            ->where('payroll_period_id', $this->period->id)
            ->where('employee_id', $employee->id)
            ->sole()
            ->id);
    }

    public function test_closed_and_completed_periods_reject_direct_add_requests(): void
    {
        $employee = $this->employee('EMP-CLOSED', 'Closed', 'Employee');

        foreach (['closed', 'completed'] as $status) {
            $this->period->update(['status' => $status]);
            $this->actingAs($this->admin)
                ->post(panelRoute('payroll-drafts.add-employee.store'), [
                    'payroll_period_id' => $this->period->id,
                    'employee_id' => $employee->id,
                ])
                ->assertSessionHas('statusDanger', 'This payroll period is not open and cannot accept additional employees.');
            $this->assertDatabaseMissing('pay_headers', [
                'payroll_period_id' => $this->period->id,
                'employee_id' => $employee->id,
            ]);
        }
    }

    public function test_generation_failure_rolls_back_the_new_employee_and_returns_the_actual_error(): void
    {
        $employeeA = $this->employee('EMP-SAFE', 'Safe', 'Employee');
        $draftA = $this->generate($employeeA);
        $employeeB = $this->employee('EMP-FAIL', 'Failing', 'Employee');
        StatutoryRule::where('statutory_type', 'tax')->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post(panelRoute('payroll-drafts.add-employee.store'), [
                'payroll_period_id' => $this->period->id,
                'employee_id' => $employeeB->id,
            ])
            ->assertSessionHas('statusDanger');

        $this->assertDatabaseMissing('pay_headers', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employeeB->id,
        ]);
        $this->assertSame($draftA->id, $draftA->fresh()->id);
    }

    public function test_repeated_add_attempts_leave_exactly_one_header(): void
    {
        $employee = $this->employee('EMP-RACE', 'Race', 'Employee');
        $service = app(PayrollProcessingService::class);

        $first = $service->addEmployeeDraft($this->period, $employee->id);

        try {
            $service->addEmployeeDraft($this->period, $employee->id);
            $this->fail('The second add attempt should be rejected as a duplicate.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Employee is already included in this payroll period.', $exception->getMessage());
        }

        $this->assertSame($first->id, PayHeader::withoutGlobalScopes()
            ->where('payroll_period_id', $this->period->id)
            ->where('employee_id', $employee->id)
            ->sole()
            ->id);
    }

    public function test_unique_index_migration_reports_legacy_duplicates_without_deleting_them(): void
    {
        $employee = $this->employee('EMP-LEGACY-DUP', 'Legacy', 'Duplicate');

        Schema::table('pay_headers', function ($table) {
            $table->dropUnique('pay_headers_period_employee_unique');
        });

        $first = PayHeader::withoutGlobalScopes()->create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
        ]);
        $second = PayHeader::withoutGlobalScopes()->create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
        ]);

        $migration = require database_path('migrations/2026_08_08_000001_add_unique_pay_header_per_employee_period.php');

        try {
            $migration->up();
            $this->fail('The unique-index migration should stop when legacy duplicates exist.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('found 1 duplicate payroll-period/employee pair', $exception->getMessage());
            $this->assertStringContainsString("period {$this->period->id} / employee {$employee->id} (2 headers)", $exception->getMessage());
        }

        $this->assertDatabaseHas('pay_headers', ['id' => $first->id]);
        $this->assertDatabaseHas('pay_headers', ['id' => $second->id]);

        PayHeader::withoutGlobalScopes()->whereIn('id', [$first->id, $second->id])->delete();
        $migration->up();
    }

    private function employee(string $code, string $firstName, string $lastName, float $dailyRate = 500.00, string $status = 'active'): EmployeeMaster
    {
        return EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->department->id,
            'employee_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'daily_rate' => $dailyRate,
            'status' => $status,
        ]);
    }

    private function generate(EmployeeMaster $employee): PayHeader
    {
        return app(PayrollProcessingService::class)
            ->generateDraft($this->period, [$employee->id])
            ->sole();
    }

    /** @return array<int, array<string, mixed>> */
    private function linesFor(string $model, int $payHeaderId): array
    {
        $columns = $model === PayIn::class
            ? ['id', 'pay_id', 'income_type_id', 'number_input', 'rate']
            : ['id', 'pay_id', 'deduction_type_id', 'loan_id', 'number_input', 'rate'];

        return $model::withoutGlobalScopes()
            ->where('pay_id', $payHeaderId)
            ->orderBy('id')
            ->get($columns)
            ->map(fn ($line) => $line->getAttributes())
            ->all();
    }
}
