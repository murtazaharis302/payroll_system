<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmpDeduction;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeRecordMaintenanceDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $companyAdmin;
    protected User $otherCompanyAdmin;
    protected Company $company;
    protected Company $otherCompany;
    protected Department $dept;
    protected EmployeeMaster $employee;
    protected IncomeType $allowanceType;
    protected DeductionType $uniformType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => Role::SUPER_ADMIN,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin@company.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Acme Corporation',
            'email' => 'info@acme.com',
            'status' => '1',
        ]);
        $this->companyAdmin->update(['company_id' => $this->company->id]);

        $this->otherCompanyAdmin = User::create([
            'name' => 'Other Admin',
            'email' => 'admin@other.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => Role::COMPANY_ADMIN,
        ]);

        $this->otherCompany = Company::create([
            'user_id' => $this->otherCompanyAdmin->id,
            'name' => 'Other Corp',
            'email' => 'info@other.com',
            'status' => '1',
        ]);
        $this->otherCompanyAdmin->update(['company_id' => $this->otherCompany->id]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Engineering',
            'code' => 'ENG',
            'is_active' => true,
        ]);

        $this->employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-EMR-001',
            'first_name' => 'Alice',
            'middle_name' => 'Marie',
            'last_name' => 'Smith',
            'email' => 'alice.smith@acme.com',
            'phone' => '09171234567',
            'date_of_birth' => '1992-05-15',
            'date_hired' => '2023-01-10',
            'position' => 'Senior Developer',
            'status' => 'active',
            'monthly_rate' => 65000.00,
            'daily_rate' => 2500.00,
            'hourly_rate' => 312.50,
            'sss_number' => '12-3456789-0',
            'philhealth_number' => '12-345678901-2',
            'pagibig_number' => '1234-5678-9012',
            'tin_number' => '123-456-789-000',
            'tax_code' => 'SINGLE',
            'acct_num' => '1234567890',
            'atm_num' => 'ATM-98765',
            'biometric_id' => 'BIO-001',
            'remarks' => 'Key personnel',
        ]);

        $this->allowanceType = IncomeType::create([
            'company_id' => $this->company->id,
            'name' => 'Internet Allowance',
            'code' => 'INTERNET_ALLOW',
            'type' => 'taxable',
            'is_active' => true,
        ]);

        $this->uniformType = DeductionType::create([
            'company_id' => $this->company->id,
            'name' => 'Uniform Deduction',
            'code' => 'UNIFORM_DED',
            'is_active' => true,
        ]);

        EmpIncome::create([
            'employee_id' => $this->employee->id,
            'income_type_id' => $this->allowanceType->id,
            'amount' => 2000.00,
            'rate' => 2000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        EmpDeduction::create([
            'employee_id' => $this->employee->id,
            'deduction_type_id' => $this->uniformType->id,
            'amount' => 500.00,
            'rate' => 500.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
    }

    public function test_emr_full_details_route_exists_and_returns_expected_employee_json(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->getJson(route('payroll-employees.full-details', $this->employee->id));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'employee' => [
                'id' => $this->employee->id,
                'company_id' => $this->company->id,
                'employee_code' => 'EMP-EMR-001',
                'first_name' => 'Alice',
                'middle_name' => 'Marie',
                'last_name' => 'Smith',
                'email' => 'alice.smith@acme.com',
                'phone' => '09171234567',
                'date_of_birth' => '1992-05-15',
                'date_hired' => '2023-01-10',
                'position' => 'Senior Developer',
                'status' => 'active',
                'dept_id' => $this->dept->id,
                'sss_number' => '12-3456789-0',
                'philhealth_number' => '12-345678901-2',
                'pagibig_number' => '1234-5678-9012',
                'tin_number' => '123-456-789-000',
                'tax_code' => 'SINGLE',
                'acct_num' => '1234567890',
                'atm_num' => 'ATM-98765',
                'biometric_id' => 'BIO-001',
                'remarks' => 'Key personnel',
                'monthly_rate' => 65000.00,
                'daily_rate' => 2500.00,
                'hourly_rate' => 312.50,
            ],
        ]);

        $incomes = collect($response->json('incomes'));
        $this->assertTrue($incomes->contains(function ($inc) {
            return $inc['income_type_id'] === $this->allowanceType->id && (float) $inc['amount'] === 2000.00;
        }));

        $deductions = collect($response->json('deductions'));
        $this->assertTrue($deductions->contains(function ($dec) {
            return $dec['deduction_type_id'] === $this->uniformType->id && (float) $dec['amount'] === 500.00;
        }));
    }

    public function test_emr_full_details_accessible_via_admin_tenant_and_company_routes(): void
    {
        // Admin tenant route
        $adminResponse = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.payroll-employees.full-details', $this->employee->id));
        $adminResponse->assertStatus(200);
        $adminResponse->assertJsonPath('status', 'success');
        $adminResponse->assertJsonPath('employee.id', $this->employee->id);

        // Company route
        $companyResponse = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->getJson(route('payroll-employees.full-details', $this->employee->id));
        $companyResponse->assertStatus(200);
        $companyResponse->assertJsonPath('status', 'success');
        $companyResponse->assertJsonPath('employee.id', $this->employee->id);
    }

    public function test_emr_full_details_rejects_unauthorized_cross_company_access(): void
    {
        $response = $this->actingAs($this->otherCompanyAdmin)
            ->withSession(['active_company_id' => $this->otherCompany->id])
            ->getJson(route('payroll-employees.full-details', $this->employee->id));

        // Blocked by global company scope / authorization (404/403)
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_emr_page_renders_quick_search_input_and_correct_full_details_route_in_script(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll-employees.index'));

        $response->assertStatus(200);

        // Verify quick search input ID exists
        $response->assertSee('id="employee_quick_search"', false);
        $response->assertSee('onkeyup="filterEmployeeDirectory()"', false);

        // Verify script contains the full-details route template
        $response->assertSee('payroll-employees/maintenance', false);
        $response->assertSee('full-details', false);
        $response->assertDontSee('/payroll-employees/maintenance/__ID__/details', false);
    }

    public function test_emr_script_reads_employee_quick_search_element_id(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll-employees.index'));

        $response->assertStatus(200);
        $response->assertSee("document.getElementById('employee_quick_search')", false);
    }

    public function test_emr_script_does_not_call_nonexistent_has_changes_api(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll-employees.index'));

        $response->assertStatus(200);
        $response->assertDontSee('.hasChanges()', false);
        $response->assertSee('window.PayrollEmployeeUnsavedChanges?.markSaved?.()', false);
        $response->assertSee('window.PayrollEmployeeUnsavedChanges = { markSaved }', false);
        $response->assertSee('id="employeeUnsavedChangesModal"', false);
        $response->assertSee('id="btnEmployeeUnsavedCancel"', false);
        $response->assertSee('id="btnEmployeeUnsavedDiscard"', false);
        $response->assertSee('id="btnEmployeeUnsavedSave"', false);
    }
}
