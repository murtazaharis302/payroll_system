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

class PayrollDraftDepartmentFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $hrUser;
    protected Company $company1;
    protected Department $hrDept;
    protected Department $engDept;
    protected PayrollPeriod $period1;

    protected User $company2User;
    protected Company $company2;
    protected Department $foreignDept;
    protected PayrollPeriod $foreignPeriod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);

        // Setup Company 1
        $this->adminUser = User::create([
            'name' => 'Admin Company 1',
            'email' => 'admin1@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company1 = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company One Inc',
            'company_code' => 'COMP1',
            'email' => 'comp1@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company1->id]);

        $this->hrUser = User::create([
            'name' => 'HR User Comp 1',
            'email' => 'hr1@test.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'role_id' => 3,
            'company_id' => $this->company1->id,
        ]);

        $this->hrDept = Department::create([
            'company_id' => $this->company1->id,
            'code' => 'HR',
            'name' => 'Human Resources',
            'is_active' => true,
        ]);

        $this->engDept = Department::create([
            'company_id' => $this->company1->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $this->period1 = PayrollPeriod::create([
            'company_id' => $this->company1->id,
            'period_name' => 'August 2026 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'cut_off_start' => '2026-08-01',
            'cut_off_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        // Setup Company 2
        $this->company2User = User::create([
            'name' => 'Admin Company 2',
            'email' => 'admin2@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company2 = Company::create([
            'user_id' => $this->company2User->id,
            'name' => 'Company Two Corp',
            'company_code' => 'COMP2',
            'email' => 'comp2@test.com',
            'status' => '1',
        ]);

        $this->company2User->update(['company_id' => $this->company2->id]);

        $this->foreignDept = Department::create([
            'company_id' => $this->company2->id,
            'code' => 'FIN',
            'name' => 'Finance Foreign',
            'is_active' => true,
        ]);

        $this->foreignPeriod = PayrollPeriod::create([
            'company_id' => $this->company2->id,
            'period_name' => 'Foreign August 1st Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'cut_off_start' => '2026-08-01',
            'cut_off_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'period_number' => 1,
            'status' => 'open',
        ]);
    }

    public function test_department_filter_returns_only_matching_department_employees(): void
    {
        $hrEmp = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-01',
            'first_name' => 'Helen',
            'last_name' => 'HR',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $engEmp = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $this->engDept->id,
            'employee_code' => 'EMP-ENG-01',
            'first_name' => 'Edward',
            'last_name' => 'Engineer',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        $hrDraft = PayHeader::create([
            'payroll_period_id' => $this->period1->id,
            'employee_id' => $hrEmp->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 15000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 14000.00,
        ]);

        $engDraft = PayHeader::create([
            'payroll_period_id' => $this->period1->id,
            'employee_id' => $engEmp->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 20000.00,
            'total_deductions' => 1500.00,
            'net_pay' => 18500.00,
        ]);

        // Filter by HR Department
        $responseHr = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.index', [
                'payroll_period_id' => $this->period1->id,
                'dept_id' => $this->hrDept->id,
            ]));

        $responseHr->assertOk();
        $responseHr->assertSee('Helen HR');
        $responseHr->assertDontSee('Edward Engineer');

        // Filter by Engineering Department
        $responseEng = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.index', [
                'payroll_period_id' => $this->period1->id,
                'dept_id' => $this->engDept->id,
            ]));

        $responseEng->assertOk();
        $responseEng->assertSee('Edward Engineer');
        $responseEng->assertDontSee('Helen HR');

        // All Departments returns both
        $responseAll = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.index', [
                'payroll_period_id' => $this->period1->id,
                'dept_id' => '',
            ]));

        $responseAll->assertOk();
        $responseAll->assertSee('Helen HR');
        $responseAll->assertSee('Edward Engineer');
    }

    public function test_foreign_company_department_and_period_safely_normalized_and_isolate_tenants(): void
    {
        $comp1Emp = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-01',
            'first_name' => 'Helen',
            'last_name' => 'HR',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $comp2Emp = EmployeeMaster::create([
            'company_id' => $this->company2->id,
            'dept_id' => $this->foreignDept->id,
            'employee_code' => 'EMP-FIN-01',
            'first_name' => 'Fiona',
            'last_name' => 'Foreign',
            'monthly_rate' => 50000.00,
            'status' => 'active',
        ]);

        PayHeader::create([
            'payroll_period_id' => $this->period1->id,
            'employee_id' => $comp1Emp->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 15000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 14000.00,
        ]);

        PayHeader::create([
            'payroll_period_id' => $this->foreignPeriod->id,
            'employee_id' => $comp2Emp->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 25000.00,
            'total_deductions' => 2000.00,
            'net_pay' => 23000.00,
        ]);

        // Attempting to filter by foreign department ID while logged in as Company 1
        $response = $this->actingAs($this->hrUser)
            ->get(route('payroll-drafts.index', [
                'dept_id' => $this->foreignDept->id,
            ]));

        $response->assertOk();
        $response->assertSee('Helen HR');
        $response->assertDontSee('Fiona Foreign');

        // Attempting to filter by foreign payroll period ID while logged in as Company 1
        $responsePeriod = $this->actingAs($this->hrUser)
            ->get(route('payroll-drafts.index', [
                'payroll_period_id' => $this->foreignPeriod->id,
            ]));

        $responsePeriod->assertOk();
        $responsePeriod->assertSee('Helen HR');
        $responsePeriod->assertDontSee('Fiona Foreign');
    }

    public function test_department_filter_combines_with_real_workflow_statuses_including_pending_approval(): void
    {
        $hrEmp1 = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-01',
            'first_name' => 'Helen',
            'last_name' => 'Draft',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $hrEmp2 = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $this->hrDept->id,
            'employee_code' => 'EMP-HR-02',
            'first_name' => 'Henry',
            'last_name' => 'Pending',
            'monthly_rate' => 32000.00,
            'status' => 'active',
        ]);

        $engEmp = EmployeeMaster::create([
            'company_id' => $this->company1->id,
            'dept_id' => $this->engDept->id,
            'employee_code' => 'EMP-ENG-01',
            'first_name' => 'Edward',
            'last_name' => 'PendingEng',
            'monthly_rate' => 40000.00,
            'status' => 'active',
        ]);

        PayHeader::create([
            'payroll_period_id' => $this->period1->id,
            'employee_id' => $hrEmp1->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 15000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 14000.00,
        ]);

        PayHeader::create([
            'payroll_period_id' => $this->period1->id,
            'employee_id' => $hrEmp2->id,
            'status' => 'pending_approval',
            'days_worked' => 11.0,
            'total_gross_pay' => 16000.00,
            'total_deductions' => 1200.00,
            'net_pay' => 14800.00,
        ]);

        PayHeader::create([
            'payroll_period_id' => $this->period1->id,
            'employee_id' => $engEmp->id,
            'status' => 'pending_approval',
            'days_worked' => 11.0,
            'total_gross_pay' => 20000.00,
            'total_deductions' => 1500.00,
            'net_pay' => 18500.00,
        ]);

        // Filter: HR dept + pending_approval status
        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.index', [
                'payroll_period_id' => $this->period1->id,
                'dept_id' => $this->hrDept->id,
                'status' => 'pending_approval',
            ]));

        $response->assertOk();
        $response->assertSee('Henry Pending');
        $response->assertDontSee('Helen Draft');
        $response->assertDontSee('Edward PendingEng');

        // Filter: HR dept + draft status
        $responseDraft = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.index', [
                'payroll_period_id' => $this->period1->id,
                'dept_id' => $this->hrDept->id,
                'status' => 'draft',
            ]));

        $responseDraft->assertOk();
        $responseDraft->assertSee('Helen Draft');
        $responseDraft->assertDontSee('Henry Pending');
        $responseDraft->assertDontSee('Edward PendingEng');
    }

    public function test_pagination_preserves_department_and_query_string_filters(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $emp = EmployeeMaster::create([
                'company_id' => $this->company1->id,
                'dept_id' => $this->hrDept->id,
                'employee_code' => sprintf('EMP-HR-%02d', $i),
                'first_name' => 'HRMember' . $i,
                'last_name' => 'Test',
                'monthly_rate' => 30000.00,
                'status' => 'active',
            ]);

            PayHeader::create([
                'payroll_period_id' => $this->period1->id,
                'employee_id' => $emp->id,
                'status' => 'draft',
                'days_worked' => 11.0,
                'total_gross_pay' => 15000.00,
                'total_deductions' => 1000.00,
                'net_pay' => 14000.00,
            ]);
        }

        $response = $this->actingAs($this->adminUser)
            ->get(route('payroll-drafts.index', [
                'payroll_period_id' => $this->period1->id,
                'dept_id' => $this->hrDept->id,
                'status' => 'draft',
            ]));

        $response->assertOk();
        // Assert pagination links contain dept_id, payroll_period_id, and status
        $response->assertSee('dept_id=' . $this->hrDept->id);
        $response->assertSee('payroll_period_id=' . $this->period1->id);
        $response->assertSee('status=draft');
    }

    public function test_super_admin_loads_all_active_departments_when_no_company_is_selected(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin User',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => 1,
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('payroll-drafts.index'));

        $response->assertOk();
        // Super Admin sees active departments across companies in dropdown
        $response->assertSee($this->hrDept->name);
        $response->assertSee($this->engDept->name);
        $response->assertSee($this->foreignDept->name);
    }
}
