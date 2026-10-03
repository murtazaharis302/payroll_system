<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicPayContractRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->seed(\Database\Seeders\IncomeTypesSeeder::class);
        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);
    }

    public function test_daily_employee_10_days_at_1000_produces_10000_gross_pay_and_remains_unchanged_on_resave(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Daily Rate Corp',
            'company_code' => 'DAILY-001',
            'email' => 'daily@corp.com',
            'status' => 1,
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'name' => 'Daily Dept',
            'code' => 'D_DEPT',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-DAILY-01',
            'first_name' => 'Daily',
            'last_name' => 'Worker',
            'email' => 'dailyworker@test.com',
            'daily_rate' => 1000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        $this->actingAs($admin);

        // First edit & update: 10 days worked x ₱1,000 daily rate = ₱10,000
        $response = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 10.0,
            'basic_pay' => 10000.00,
        ]);

        $response->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertEquals(10000.00, (float) $draft->basic_pay);
        $this->assertEquals(10000.00, (float) $draft->total_gross_pay);

        $basicPayIn = $draft->payIns()->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))->first();
        $this->assertEquals(10.0, (float) $basicPayIn->number_input);
        $this->assertEquals(1000.00, (float) $basicPayIn->rate);
        $this->assertEquals(10000.00, (float) $basicPayIn->amount);

        // Save unchanged draft 5 consecutive times
        for ($i = 0; $i < 5; $i++) {
            $resaveResponse = $this->put(route('payroll-drafts.update', $draft->id), [
                'days_worked' => 10.0,
                'basic_pay' => 10000.00,
            ]);

            $resaveResponse->assertSessionHasNoErrors();
            $draft->refresh();

            $this->assertEquals(10000.00, (float) $draft->basic_pay, "Resave iteration {$i} failed: basic_pay changed!");
            $this->assertEquals(10000.00, (float) $draft->total_gross_pay, "Resave iteration {$i} failed: gross_pay changed!");
        }
    }

    public function test_monthly_employee_fixed_pay_produces_exact_period_salary_and_remains_unchanged_on_resave(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Monthly Corp',
            'company_code' => 'MONTH-001',
            'email' => 'monthly@corp.com',
            'status' => 1,
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'name' => 'Monthly Dept',
            'code' => 'M_DEPT',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-MONTH-01',
            'first_name' => 'Monthly',
            'last_name' => 'Staff',
            'email' => 'monthlystaff@test.com',
            'monthly_rate' => 20000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        // ₱20,000 / 2 = ₱10,000 period salary
        $this->assertEquals(10000.00, (float) $draft->basic_pay);

        $this->actingAs($admin);

        // Update draft 3 times without changing basic pay
        for ($i = 0; $i < 3; $i++) {
            $response = $this->put(route('payroll-drafts.update', $draft->id), [
                'days_worked' => 11.0,
                'basic_pay' => 10000.00,
            ]);

            $response->assertSessionHasNoErrors();
            $draft->refresh();

            $this->assertEquals(10000.00, (float) $draft->basic_pay);
            $this->assertEquals(10000.00, (float) $draft->total_gross_pay);
        }
    }

    public function test_operator_amount_override_stores_quantity_1_and_unit_rate_override_amount(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Override Corp',
            'company_code' => 'OVER-001',
            'email' => 'override@corp.com',
            'status' => 1,
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'name' => 'Override Dept',
            'code' => 'O_DEPT',
            'is_active' => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id' => $dept->id,
            'employee_code' => 'EMP-OVER-01',
            'first_name' => 'Custom',
            'last_name' => 'Override',
            'email' => 'override@test.com',
            'daily_rate' => 1000.00,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08 1st Half',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-16',
            'status' => 'open',
        ]);

        $service = new PayrollProcessingService();
        $headers = $service->generateDraft($period, [$employee->id]);
        $draft = $headers->first();

        $this->actingAs($admin);

        // Operator enters custom override amount of ₱15,000 instead of 10 * 1000
        $response = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 10.0,
            'basic_pay' => 15000.00,
        ]);

        $response->assertSessionHasNoErrors();
        $draft->refresh();

        $this->assertEquals(15000.00, (float) $draft->basic_pay);

        $basicPayIn = $draft->payIns()->whereHas('incomeType', fn($q) => $q->where('code', 'BASIC'))->first();
        $this->assertEquals(1.00, (float) $basicPayIn->number_input);
        $this->assertEquals(15000.00, (float) $basicPayIn->rate);
        $this->assertEquals(15000.00, (float) $basicPayIn->amount);

        // Re-save draft and verify no compounding
        $resaveResponse = $this->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 10.0,
            'basic_pay' => 15000.00,
        ]);

        $resaveResponse->assertSessionHasNoErrors();
        $draft->refresh();

        $this->assertEquals(15000.00, (float) $draft->basic_pay);
        $this->assertEquals(15000.00, (float) $draft->total_gross_pay);
    }
}
