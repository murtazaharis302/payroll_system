<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $testCompany;
    protected EmployeeMaster $testEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        $this->admin = User::create([
            'name' => 'Test Admin',
            'email' => 'testadmin@test.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'role' => 'admin',
        ]);

        $this->testCompany = Company::create([
            'user_id' => $this->admin->id,
            'name' => 'Test Company',
            'email' => 'testcompany@test.com',
            'status' => 1,
        ]);

        $this->testDept = \App\Models\Department::create([
            'company_id' => $this->testCompany->id,
            'name' => 'General',
            'code' => 'GEN',
            'is_active' => 1,
        ]);

        $this->testEmployee = EmployeeMaster::create([
            'company_id' => $this->testCompany->id,
            'dept_id' => $this->testDept->id,
            'department' => $this->testDept->name,
            'employee_code' => 'TEST-001',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'date_hired' => '2025-01-01',
            'position' => 'Staff',
            'monthly_rate' => 20000,
            'status' => 'active',
        ]);

        PayrollPeriod::create([
            'company_id' => $this->testCompany->id,
            'period_name' => 'Test Period',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
            'status' => 'open',
        ]);

        $this->actingAs($this->admin);
    }

    public function test_admin_can_load_all_module_pages(): void
    {
        $pages = [
            '/admin/dashboard',
            '/admin/users',
            '/admin/employee',
            '/admin/companies',
            '/admin/settings',
            '/admin/payroll-employees',
            '/admin/income-types',
            '/admin/deduction-types',
            '/admin/emp-incomes',
            '/admin/emp-deductions',
            '/admin/loan-monitor',
            '/admin/attendance',
            '/admin/government-contributions',
            '/admin/payroll-drafts',
            '/admin/payroll-approvals',
        ];

        foreach ($pages as $uri) {
            $response = $this->get($uri);
            $this->assertTrue(
                in_array($response->status(), [200, 302]),
                "Failed loading {$uri}: HTTP {$response->status()}"
            );
        }
    }

    public function test_admin_can_create_payroll_employee(): void
    {
        $response = $this->post('/admin/payroll-employees', [
            'company_id' => $this->testCompany->id,
            'dept_id' => $this->testDept->id,
            'employee_code' => 'TEST-NEW-001',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_hired' => '2025-06-01',
            'position' => 'Helper',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees_master', ['employee_code' => 'TEST-NEW-001']);
    }

    public function test_admin_ajax_loads_employee_loans(): void
    {
        $response = $this->get("/admin/loan-monitor/employee/{$this->testEmployee->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'employee',
            'loans',
            'summary' => [
                'total',
                'active',
                'total_principal',
                'total_paid',
                'total_balance',
            ],
        ]);
    }
}
