<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollDraftEmployeeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected PayrollPeriod $period;
    protected EmployeeMaster $employee1;
    protected EmployeeMaster $employee2;
    protected PayHeader $draft1;
    protected PayHeader $draft2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);

        $this->adminUser = User::create([
            'name' => 'Company Admin',
            'email' => 'admin@filtertest.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Filter Test Corp',
            'company_code' => 'FTC',
            'email' => 'ftc@test.com',
            'status' => 1,
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'frequency' => 'semi_monthly',
            'status' => 'open',
        ]);

        $this->employee1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-001',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'status' => 'active',
            'monthly_rate' => 30000.00,
            'date_hired' => '2026-01-01',
        ]);

        $this->employee2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-002',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'status' => 'active',
            'monthly_rate' => 32000.00,
            'date_hired' => '2026-01-01',
        ]);

        $this->draft1 = PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee1->id,
            'status' => 'draft',
            'total_gross_pay' => 15000.00,
            'total_deductions' => 0.00,
            'net_pay' => 15000.00,
            'lock_version' => 1,
        ]);

        $this->draft2 = PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee2->id,
            'status' => 'draft',
            'total_gross_pay' => 16000.00,
            'total_deductions' => 0.00,
            'net_pay' => 16000.00,
            'lock_version' => 1,
        ]);
    }

    /**
     * Test /admin/payroll-drafts renders the employee dropdown filter.
     */
    public function test_payroll_drafts_index_renders_employee_dropdown_filter(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.index'));

        $response->assertStatus(200);
        $response->assertSee('Employee Name');
        $response->assertSee('name="employee_id"', false);
        $response->assertSee('All Employees');
        $response->assertSee($this->employee1->first_name);
        $response->assertSee($this->employee1->last_name);
        $response->assertSee($this->employee2->first_name);
        $response->assertSee($this->employee2->last_name);
    }

    /**
     * Test filtering payroll drafts by employee_id returns only that employee's draft.
     */
    public function test_payroll_drafts_index_filters_by_selected_employee(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.index', [
            'employee_id' => $this->employee1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Alice Smith');
        $response->assertDontSee('Bob Jones');

        $response2 = $this->actingAs($this->adminUser)->get(route('payroll-drafts.index', [
            'employee_id' => $this->employee2->id,
        ]));

        $response2->assertStatus(200);
        $response2->assertSee('Bob Jones');
        $response2->assertDontSee('Alice Smith');
    }

    /**
     * Test combined filter: period and employee.
     */
    public function test_payroll_drafts_index_combined_period_and_employee_filter(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('payroll-drafts.index', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Alice Smith');
        $response->assertDontSee('Bob Jones');
    }
}
