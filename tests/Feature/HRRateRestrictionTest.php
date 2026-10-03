<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HRRateRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);
    }

    public function test_rates_can_be_set_during_employee_creation_by_company_admin()
    {
        $adminUser = User::create([
            'name' => 'Owner',
            'email' => 'owner1@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Test Company HR 1',
            'email' => 'comp1@test.com',
            'status' => '1',
        ]);

        $companyAdmin = User::create([
            'company_id' => $company->id,
            'name' => 'Company Admin User',
            'email' => 'compadmin1@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $response = $this->actingAs($companyAdmin)->post(route('payroll-employees.store'), [
            'company_id' => $company->id,
            'employee_code' => 'EMP-TEST-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_hired' => '2026-01-01',
            'position' => 'Developer',
            'monthly_rate' => 45000.00,
            'daily_rate' => 2045.45,
            'hourly_rate' => 255.68,
        ]);

        $response->assertRedirect(route('payroll-employees.index'));

        $this->assertDatabaseHas('employees_master', [
            'employee_code' => 'EMP-TEST-001',
            'monthly_rate' => 45000.00,
        ]);
    }

    public function test_employee_rates_cannot_be_modified_on_update()
    {
        $adminUser = User::create([
            'name' => 'Owner2',
            'email' => 'owner2@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Test Company HR 2',
            'email' => 'comp2@test.com',
            'status' => '1',
        ]);

        $companyAdmin = User::create([
            'company_id' => $company->id,
            'name' => 'Company Admin User 2',
            'email' => 'compadmin2@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-LOCK-001',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_hired' => '2026-01-01',
            'position' => 'Manager',
            'monthly_rate' => 50000.00,
            'daily_rate' => 2272.73,
            'hourly_rate' => 284.09,
            'status' => 'active',
        ]);

        $response = $this->actingAs($companyAdmin)->put(route('payroll-employees.update', $employee->id), [
            'company_id' => $company->id,
            'employee_code' => 'EMP-LOCK-001',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_hired' => '2026-01-01',
            'position' => 'Senior Manager',
            'monthly_rate' => 99999.00,
            'daily_rate' => 8888.00,
            'hourly_rate' => 999.00,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('payroll-employees.index'));

        $employee->refresh();
        $this->assertEquals(50000.00, (float) $employee->monthly_rate);
        $this->assertEquals('Senior Manager', $employee->position);
    }

    public function test_hr_cannot_see_rates_in_ajax_details()
    {
        $adminUser = User::create([
            'name' => 'Owner3',
            'email' => 'owner3@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Test Company HR 3',
            'email' => 'comp3@test.com',
            'status' => '1',
        ]);

        $hrUser = User::create([
            'company_id' => $company->id,
            'name' => 'HR Staff',
            'email' => 'hrstaff@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-HR-001',
            'first_name' => 'Bob',
            'last_name' => 'Taylor',
            'date_hired' => '2026-01-01',
            'position' => 'Staff',
            'monthly_rate' => 30000.00,
            'daily_rate' => 1363.64,
            'hourly_rate' => 170.45,
            'status' => 'active',
        ]);

        $response = $this->actingAs($hrUser)->get(route('payroll-employees.company-master.details', $employee->id));
        $response->assertStatus(403);

        $fullDetailsResponse = $this->actingAs($hrUser)->get(route('payroll-employees.full-details', $employee->id));
        $fullDetailsResponse->assertStatus(403);
    }

    public function test_hr_draft_update_preserves_income_calculations()
    {
        $adminUser = User::create([
            'name' => 'Owner4',
            'email' => 'owner4@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Test Company HR 4',
            'email' => 'comp4@test.com',
            'status' => '1',
        ]);

        $hrUser = User::create([
            'company_id' => $company->id,
            'name' => 'HR Staff 2',
            'email' => 'hrstaff2@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => '2026-08-A',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-15',
            'status' => 'open',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-DRAFT-001',
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'date_hired' => '2026-01-01',
            'position' => 'Analyst',
            'monthly_rate' => 44000.00,
            'daily_rate' => 2000.00,
            'hourly_rate' => 250.00,
            'status' => 'active',
        ]);

        $draft = PayHeader::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'days_worked' => 10.0,
            'total_gross_pay' => 20000.00,
            'total_deductions' => 500.00,
            'net_pay' => 19500.00,
            'status' => 'draft',
        ]);

        $basicType = IncomeType::firstOrCreate(['code' => 'BASIC'], ['company_id' => $company->id, 'name' => 'Basic Salary', 'type' => 'taxable', 'is_active' => true]);
        $sssType = DeductionType::firstOrCreate(['code' => 'SSS'], ['company_id' => $company->id, 'name' => 'SSS Contribution', 'type' => 'statutory', 'is_active' => true]);

        PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $basicType->id,
            'number_input' => 10.0,
            'rate' => 2000.00,
        ]);

        PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $sssType->id,
            'number_input' => 1.0,
            'rate' => 500.00,
        ]);

        $response = $this->actingAs($hrUser)->put(route('payroll-drafts.update', $draft->id), [
            'days_worked' => 11.0,
            'days_absent' => 0,
            'overtime_hours' => 0,
            'sss_contribution' => 1000.00,
        ]);

        $response->assertRedirect(route('payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $draft->payroll_period_id]));


        $draft->refresh();
        $this->assertEquals(22000.00, (float) $draft->total_gross_pay);
        $this->assertEquals(1000.00, (float) $draft->total_deductions);
        $this->assertEquals(21000.00, (float) $draft->net_pay);
    }

    public function test_hr_user_restricted_from_creating_employees()
    {
        $adminUser = User::create([
            'name' => 'Owner Restrict',
            'email' => 'owner_restr@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Test Company Restrict',
            'email' => 'comp_restr@test.com',
            'status' => '1',
        ]);

        $hrUser = User::create([
            'company_id' => $company->id,
            'name' => 'HR Restricted User',
            'email' => 'hr_restr@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::HR,
            'role' => 'hr',
        ]);

        $createResponse = $this->actingAs($hrUser)->get(route('payroll-employees.create'));
        $createResponse->assertStatus(403);

        $systemUsersResponse = $this->actingAs($hrUser)->get(route('employee'));
        $systemUsersResponse->assertStatus(403);

        $indexResponse = $this->actingAs($hrUser)->get(route('payroll-employees.index'));
        $indexResponse->assertStatus(403);

        $companyMasterResponse = $this->actingAs($hrUser)->get(route('payroll-employees.company-master'));
        $companyMasterResponse->assertStatus(403);



        $storeResponse = $this->actingAs($hrUser)->post(route('payroll-employees.store'), [
            'company_id' => $company->id,
            'employee_code' => 'EMP-HR-RESTR',
            'first_name' => 'Restricted',
            'last_name' => 'User',
            'date_hired' => '2026-01-01',
            'position' => 'Clerk',
        ]);
        $storeResponse->assertStatus(403);
    }

    public function test_employee_maintenance_ajax_record_update()
    {
        $adminUser = User::create([
            'name' => 'Owner Maint',
            'email' => 'owner_maint@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $company = Company::create([
            'user_id' => $adminUser->id,
            'name' => 'Test Company Maintenance',
            'email' => 'comp_maint@test.com',
            'status' => '1',
        ]);

        $companyAdmin = User::create([
            'company_id' => $company->id,
            'name' => 'Admin Maintenance',
            'email' => 'admin_maint@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-MAINT-001',
            'first_name' => 'Robert',
            'last_name' => 'Paulson',
            'date_hired' => '2026-01-01',
            'position' => 'Specialist',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $detailsResponse = $this->actingAs($companyAdmin)->get(route('payroll-employees.full-details', $employee->id));
        $detailsResponse->assertStatus(200)->assertJsonPath('employee.first_name', 'Robert');

        $updateResponse = $this->actingAs($companyAdmin)->postJson(route('payroll-employees.update-record', $employee->id), [
            'employee_code' => 'EMP-MAINT-001',
            'first_name' => 'Robert',
            'last_name' => 'Paulson Updated',
            'date_hired' => '2026-01-01',
            'position' => 'Lead Specialist',
            'status' => 'active',
            'atm_num' => '9988776655',
            'tax_code' => 'MARRIED',
        ]);

        $updateResponse->assertStatus(200)->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('employees_master', [
            'id' => $employee->id,
            'last_name' => 'Paulson Updated',
            'position' => 'Lead Specialist',
            'atm_num' => '9988776655',
        ]);
    }
}
