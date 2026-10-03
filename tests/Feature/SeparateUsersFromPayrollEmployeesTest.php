<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeparateUsersFromPayrollEmployeesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected User $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $this->companyAdmin = User::factory()->create(['role_id' => 2, 'role' => 'company']);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Acme Payroll Corp',
            'company_code' => 'ACME-001',
            'email' => 'acme@test.com',
            'status' => 1,
        ]);

        $this->companyAdmin->update(['company_id' => $this->company->id]);
    }

    /**
     * Required Test 1:
     * Creating a User does not create an EmployeeMaster or make the user appear in Payroll Employee lists.
     */
    public function test_creating_user_does_not_create_employee_master_or_appear_in_payroll_employee_list(): void
    {
        $user = User::factory()->create([
            'name' => 'John Regular User',
            'email' => 'john.user@example.com',
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $this->assertNull($user->emp_id);
        $this->assertDatabaseMissing('employees_master', [
            'email' => 'john.user@example.com',
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('payroll-employees.index', ['company_id' => $this->company->id]));

        $response->assertStatus(200);
        $response->assertDontSee('john.user@example.com');
        $this->assertEquals(0, EmployeeMaster::where('company_id', $this->company->id)->count());
    }

    /**
     * Required Test 2:
     * An HR user can exist without a payroll employee record.
     */
    public function test_hr_user_can_exist_without_payroll_employee_record(): void
    {
        $hrUser = User::factory()->create([
            'name' => 'Jane HR Specialist',
            'email' => 'jane.hr@example.com',
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $this->company->id,
        ]);

        $this->assertNull($hrUser->emp_id);
        $this->assertDatabaseMissing('employees_master', [
            'email' => 'jane.hr@example.com',
        ]);

        $this->actingAs($this->companyAdmin);
        $response = $this->get(route('payroll-employees.index'));
        $response->assertStatus(200);

        $this->assertNull($hrUser->fresh()->emp_id);
        $this->assertEquals(0, EmployeeMaster::where('email', 'jane.hr@example.com')->count());
    }

    /**
     * Required Test 3:
     * An authorized admin can explicitly link one User to one Payroll Employee.
     */
    public function test_authorized_admin_can_explicitly_link_user_to_payroll_employee(): void
    {
        $user = User::factory()->create([
            'name' => 'Mark User',
            'email' => 'mark@example.com',
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-1001',
            'first_name' => 'Mark',
            'last_name' => 'Employee',
            'email' => 'mark.payroll@example.com',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin);

        $response = $this->post(route('payroll-employees.link-user'), [
            'user_id' => $user->id,
            'employee_id' => $employee->id,
        ]);

        $response->assertStatus(302);
        $this->assertEquals($employee->id, $user->fresh()->emp_id);
    }

    /**
     * Required Test 4:
     * Repeated list/search/GET requests cause zero database writes.
     */
    public function test_repeated_list_search_get_requests_cause_zero_database_writes(): void
    {
        $hrUser = User::factory()->create([
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $this->company->id,
        ]);

        $employeeUser = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $this->actingAs($this->companyAdmin);

        $initialUserCount = User::withoutGlobalScopes()->count();
        $initialEmployeeMasterCount = EmployeeMaster::withoutGlobalScopes()->count();

        // Perform repeated GET calls across various endpoints
        $this->get(route('payroll-employees.index'));
        $this->get(route('payroll-employees.company-master'));
        $this->get(route('payroll-employees.create'));
        $this->get(route('payroll-employees.index', ['company_id' => $this->company->id]));

        $this->assertEquals($initialUserCount, User::withoutGlobalScopes()->count());
        $this->assertEquals($initialEmployeeMasterCount, EmployeeMaster::withoutGlobalScopes()->count());
        $this->assertNull($hrUser->fresh()->emp_id);
        $this->assertNull($employeeUser->fresh()->emp_id);
    }

    /**
     * Required Test 5:
     * A second link for the same User or employee is rejected.
     */
    public function test_second_link_for_same_user_or_employee_is_rejected(): void
    {
        $user1 = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $user2 = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $emp1 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-2001',
            'first_name' => 'Emp',
            'last_name' => 'One',
            'status' => 'active',
        ]);

        $emp2 = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-2002',
            'first_name' => 'Emp',
            'last_name' => 'Two',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin);

        // Explicitly link User 1 to Emp 1
        $this->post(route('payroll-employees.link-user'), [
            'user_id' => $user1->id,
            'employee_id' => $emp1->id,
        ])->assertStatus(302);

        $this->assertEquals($emp1->id, $user1->fresh()->emp_id);

        // Rejection 1: Attempt to link user1 (who is already linked to emp1) to emp2
        $response1 = $this->post(route('payroll-employees.link-user'), [
            'user_id' => $user1->id,
            'employee_id' => $emp2->id,
        ]);
        $response1->assertSessionHasErrors(['user_id']);
        $this->assertEquals($emp1->id, $user1->fresh()->emp_id);

        // Rejection 2: Attempt to link user2 to emp1 (which is already linked to user1)
        $response2 = $this->post(route('payroll-employees.link-user'), [
            'user_id' => $user2->id,
            'employee_id' => $emp1->id,
        ]);
        $response2->assertSessionHasErrors(['employee_id']);
        $this->assertNull($user2->fresh()->emp_id);
    }

    /**
     * Additional Required Test:
     * A failed User link during store creates no EmployeeMaster (transactional roll back & pre-validation).
     */
    public function test_failed_user_link_creates_no_employee_master(): void
    {
        $existingEmp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-EXISTING',
            'first_name' => 'Existing',
            'last_name' => 'Staff',
            'status' => 'active',
        ]);

        // Already linked user
        $linkedUser = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
            'emp_id' => $existingEmp->id,
        ]);

        $initialEmployeeMasterCount = EmployeeMaster::count();

        $this->actingAs($this->companyAdmin);

        // Attempt store with a user_id that is already linked
        $response = $this->post(route('payroll-employees.store'), [
            'company_id' => $this->company->id,
            'user_id' => $linkedUser->id,
            'first_name' => 'Orphan',
            'last_name' => 'Test',
            'email' => 'orphan.test@example.com',
        ]);

        $response->assertSessionHasErrors(['user_id']);

        // Confirm zero EmployeeMaster records were created
        $this->assertEquals($initialEmployeeMasterCount, EmployeeMaster::count());
        $this->assertDatabaseMissing('employees_master', [
            'email' => 'orphan.test@example.com',
        ]);
    }

    /**
     * Additional Required Test:
     * An invalid user_id is rejected via validation.
     */
    public function test_invalid_user_id_is_rejected(): void
    {
        $this->actingAs($this->companyAdmin);

        // Test invalid user_id on store
        $response1 = $this->post(route('payroll-employees.store'), [
            'company_id' => $this->company->id,
            'user_id' => 999999,
            'first_name' => 'Invalid',
            'last_name' => 'User',
            'email' => 'invalid.user@example.com',
        ]);
        $response1->assertSessionHasErrors(['user_id']);

        // Test invalid user_id on explicit link endpoint
        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-VALID',
            'first_name' => 'Valid',
            'last_name' => 'Employee',
        ]);

        $response2 = $this->post(route('payroll-employees.link-user'), [
            'user_id' => 999999,
            'employee_id' => $emp->id,
        ]);
        $response2->assertSessionHasErrors(['user_id']);
    }

    /**
     * Additional Required Test:
     * Two Users cannot reference the same employee at the database level due to unique constraint.
     */
    public function test_two_users_cannot_reference_same_employee_at_database_level(): void
    {
        $user1 = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $user2 = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-UNIQUE',
            'first_name' => 'Unique',
            'last_name' => 'Test',
        ]);

        $user1->update(['emp_id' => $emp->id]);

        $caughtException = false;
        try {
            // Attempt direct database update to bypass application logic
            $user2->emp_id = $emp->id;
            $user2->save();
        } catch (QueryException $e) {
            $caughtException = true;
        }

        $this->assertTrue($caughtException, 'Expected QueryException due to database unique constraint on users.emp_id');
    }

    /**
     * Additional Required Test:
     * Only Super Admin or the correct Company Admin can perform explicit linking.
     */
    public function test_only_super_admin_or_correct_company_admin_can_perform_explicit_linking(): void
    {
        $company2Owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company2 = Company::create([
            'user_id' => $company2Owner->id,
            'name' => 'Company 2 Corp',
            'company_code' => 'COMP2-001',
            'email' => 'comp2@test.com',
            'status' => 1,
        ]);
        $company2Owner->update(['company_id' => $company2->id]);

        $hrUser = User::factory()->create([
            'role_id' => 3,
            'role' => 'hr',
            'company_id' => $this->company->id,
        ]);

        $targetUser = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-AUTH',
            'first_name' => 'Auth',
            'last_name' => 'Check',
        ]);

        // 1. HR User attempting explicit link must be forbidden (403)
        $this->actingAs($hrUser);
        $this->post(route('payroll-employees.link-user'), [
            'user_id' => $targetUser->id,
            'employee_id' => $emp->id,
        ])->assertStatus(403);

        // 2. Company Admin 2 attempting to link Company 1 employee must be forbidden (403)
        $this->actingAs($company2Owner);
        $this->post(route('payroll-employees.link-user'), [
            'user_id' => $targetUser->id,
            'employee_id' => $emp->id,
        ])->assertStatus(403);

        // 3. Correct Company Admin 1 attempting explicit link must succeed (302)
        $this->actingAs($this->companyAdmin);
        $this->post(route('payroll-employees.link-user'), [
            'user_id' => $targetUser->id,
            'employee_id' => $emp->id,
        ])->assertStatus(302);

        $this->assertEquals($emp->id, $targetUser->fresh()->emp_id);

        // Reset link for Super Admin test
        $targetUser->update(['emp_id' => null]);
        $targetUser2 = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        // 4. Super Admin attempting explicit link must succeed (302)
        $this->actingAs($this->admin);
        $this->post(route('payroll-employees.link-user'), [
            'user_id' => $targetUser2->id,
            'employee_id' => $emp->id,
        ])->assertStatus(302);

        $this->assertEquals($emp->id, $targetUser2->fresh()->emp_id);
    }

    /**
     * Required Test 6:
     * Creating HR, Company Admin or Employee Users via HTTP provisioning endpoints creates no employees_master row.
     */
    public function test_creating_users_via_controllers_creates_no_employees_master_row(): void
    {
        $this->actingAs($this->companyAdmin);

        // 1. Provision an HR User via EmployeeController
        $response1 = $this->post(route('storeEmployee'), [
            'name' => 'HR Staff Account',
            'email' => 'hr.staff@example.com',
            'phone' => '1234567890',
            'password' => 'InitialPassword123!',
            'password_confirmation' => 'InitialPassword123!',
            'role' => 'hr',
            'address' => '123 HR Street',
        ]);
        $response1->assertStatus(302);

        $hrUser = User::where('email', 'hr.staff@example.com')->first();
        $this->assertNotNull($hrUser);
        $this->assertNull($hrUser->emp_id);

        // 2. Provision an Employee User via EmployeeController
        $response2 = $this->post(route('storeEmployee'), [
            'name' => 'Regular Employee Account',
            'email' => 'regular.emp@example.com',
            'phone' => '0987654321',
            'password' => 'InitialPassword123!',
            'password_confirmation' => 'InitialPassword123!',
            'role' => 'employee',
            'address' => '456 Employee Way',
        ]);
        $response2->assertStatus(302);

        $empUser = User::where('email', 'regular.emp@example.com')->first();
        $this->assertNotNull($empUser);
        $this->assertNull($empUser->emp_id);

        // 3. Super Admin user creation via userController
        $this->actingAs($this->admin);
        $response3 = $this->post(route('storeUser'), [
            'name' => 'New Super Admin',
            'email' => 'new.admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response3->assertStatus(302);

        $adminUser = User::where('email', 'new.admin@example.com')->first();
        $this->assertNotNull($adminUser);
        $this->assertNull($adminUser->emp_id);

        // Verify zero employees_master records exist
        $this->assertEquals(0, EmployeeMaster::count());
    }

    /**
     * Required Test 7:
     * Cross-company linking by Super Admin returns a validation error for company mismatch.
     */
    public function test_cross_company_linking_returns_validation_error_when_company_mismatch(): void
    {
        $company2Owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company2 = Company::create([
            'user_id' => $company2Owner->id,
            'name' => 'Company Two Corp',
            'company_code' => 'COMP2-002',
            'email' => 'comp2.mismatch@test.com',
            'status' => 1,
        ]);
        $company2Owner->update(['company_id' => $company2->id]);

        $userComp1 = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $this->company->id,
        ]);

        $empComp2 = EmployeeMaster::create([
            'company_id' => $company2->id,
            'employee_code' => 'EMP-COMP2',
            'first_name' => 'Company2',
            'last_name' => 'Employee',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin);

        // 1. Explicit link user (Company 1) to employee (Company 2) must fail validation
        $response1 = $this->post(route('payroll-employees.link-user'), [
            'user_id' => $userComp1->id,
            'employee_id' => $empComp2->id,
        ]);
        $response1->assertSessionHasErrors(['user_id']);
        $this->assertNull($userComp1->fresh()->emp_id);

        // 2. Creating an employee for Company 2 passing user_id from Company 1 must fail validation
        $response2 = $this->post(route('payroll-employees.store'), [
            'company_id' => $company2->id,
            'user_id' => $userComp1->id,
            'first_name' => 'Mismatch',
            'last_name' => 'Test',
            'email' => 'mismatch.test@example.com',
        ]);
        $response2->assertSessionHasErrors(['user_id']);
        $this->assertDatabaseMissing('employees_master', [
            'email' => 'mismatch.test@example.com',
        ]);
    }

    /**
     * Required Test 8:
     * Existing users without payroll records continue functioning normally without errors or unwanted DB writes.
     */
    public function test_existing_users_without_payroll_records_function_normally(): void
    {
        $hrUser = User::factory()->create([
            'name' => 'Existing HR User',
            'email' => 'existing.hr@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role_id' => 3,
            'role' => 'hr',
            'status' => 1,
            'company_id' => $this->company->id,
        ]);

        $employeeUser = User::factory()->create([
            'name' => 'Existing Employee User',
            'email' => 'existing.emp@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role_id' => 4,
            'role' => 'employee',
            'status' => 1,
            'company_id' => $this->company->id,
        ]);

        // 1. Verify payrollMaster relationship returns null safely
        $this->assertNull($hrUser->payrollMaster);
        $this->assertNull($employeeUser->payrollMaster);

        // 2. HR portal login succeeds and redirects to hr/dashboard
        $hrLoginResponse = $this->post(route('hrLogin'), [
            'email' => 'existing.hr@example.com',
            'password' => 'password123',
        ]);
        $hrLoginResponse->assertRedirect('hr/dashboard');
        $this->assertAuthenticatedAs($hrUser);

        // 3. Authenticated HR is blocked (403) from payroll-employees ERM index
        $hrIndexResponse = $this->get(route('payroll-employees.index'));
        $hrIndexResponse->assertStatus(403);



        // Logout HR
        $this->post(route('logout'));

        // 4. Employee portal login succeeds and redirects to employee/dashboard
        $empLoginResponse = $this->post(route('employeeLogin'), [
            'email' => 'existing.emp@example.com',
            'password' => 'password123',
        ]);
        $empLoginResponse->assertRedirect('employee/dashboard');
        $this->assertAuthenticatedAs($employeeUser);

        // 5. Assert zero employees_master records were created automatically
        $this->assertEquals(0, EmployeeMaster::count());
    }
}

