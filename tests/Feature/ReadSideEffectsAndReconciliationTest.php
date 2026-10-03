<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReadSideEffectsAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);
    }

    public function test_employee_index_is_read_only_and_does_not_modify_database_or_create_users(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Test Corp',
            'company_code' => 'TEST-0001',
            'email' => 'corp@test.com',
            'status' => 1,
        ]);

        // Unlinked user
        $unlinkedUser = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $company->id,
        ]);

        $initialUserCount = User::count();
        $initialEmployeeCount = Employee::count();

        $this->actingAs($admin);

        // Perform GET request multiple times
        $response1 = $this->get(route('employee'));
        $response1->assertStatus(200);

        $response2 = $this->get(route('employee'));
        $response2->assertStatus(200);

        // Assert no side-effect writes occurred
        $this->assertEquals($initialUserCount, User::count());
        $this->assertEquals($initialEmployeeCount, Employee::count());

        // Assert no shared default password123 accounts exist
        $users = User::all();
        foreach ($users as $user) {
            $this->assertFalse(Hash::check('password123', $user->password));
        }
    }

    public function test_reconciliation_command_dry_run_mode_produces_report_without_writes(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'DryRun Corp',
            'company_code' => 'DRY-0001',
            'email' => 'dry@test.com',
            'status' => 1,
        ]);

        // Unlinked user
        $unlinkedUser = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'email' => 'unlinked@dryrun.com',
            'company_id' => $company->id,
        ]);

        $initialEmployeeCount = Employee::count();

        $this->artisan('payroll:reconcile-employee-identities --dry-run')
            ->expectsOutputToContain('DRY-RUN (No Database Writes)')
            ->expectsOutputToContain('Proposed Unlinked User Links')
            ->assertExitCode(0);

        $this->assertEquals($initialEmployeeCount, Employee::count());
        $this->assertDatabaseMissing('employees', ['e_user_id' => $unlinkedUser->id]);
    }

    public function test_reconciliation_command_apply_mode_is_idempotent_and_audited(): void
    {
        $companyOwner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyOwner->id,
            'name' => 'Apply Corp',
            'company_code' => 'APP-0001',
            'email' => 'app@test.com',
            'status' => 1,
        ]);

        $unlinkedUser = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'email' => 'unlinked@apply.com',
            'company_id' => $company->id,
        ]);

        // First run with --apply
        $this->artisan('payroll:reconcile-employee-identities --apply')
            ->expectsOutputToContain('APPLY (Writing Changes)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('employees', ['e_user_id' => $unlinkedUser->id]);
        $employeeCountAfterFirstRun = Employee::count();

        // Second run with --apply (idempotency check)
        $this->artisan('payroll:reconcile-employee-identities --apply')
            ->expectsOutputToContain('APPLY (Writing Changes)')
            ->assertExitCode(0);

        $this->assertEquals($employeeCountAfterFirstRun, Employee::count());
    }

    public function test_reconciliation_command_refuses_ambiguous_email_matches(): void
    {
        $owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $owner->id,
            'name' => 'Ambiguous Corp',
            'company_code' => 'AMB-0001',
            'email' => 'amb@test.com',
            'status' => 1,
        ]);

        $user = User::factory()->create([
            'email' => 'ambiguous@test.com',
            'role_id' => 4,
            'role' => 'employee',
        ]);

        // Existing link
        Employee::create([
            'owner_user_id' => $owner->id,
            'e_user_id' => $user->id,
            'id_number' => 'EMP-999',
            'status' => 1,
        ]);

        // Master with same email
        EmployeeMaster::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'ambiguous@test.com',
        ]);

        $this->artisan('payroll:reconcile-employee-identities --apply')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('employees', ['id_number' => 'EMP-001']);
    }

    public function test_unique_database_constraint_prevents_duplicate_user_employee_links(): void
    {
        $owner = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $user = User::factory()->create(['role_id' => 4, 'role' => 'employee']);

        Employee::create([
            'owner_user_id' => $owner->id,
            'e_user_id' => $user->id,
            'id_number' => 'EMP-0001',
            'status' => 1,
        ]);

        $this->expectException(QueryException::class);

        // Attempting to create duplicate link for same e_user_id must trigger unique constraint violation
        Employee::create([
            'owner_user_id' => $owner->id,
            'e_user_id' => $user->id,
            'id_number' => 'EMP-0002',
            'status' => 1,
        ]);
    }

    public function test_reconciliation_skips_records_with_unresolvable_company_owner(): void
    {
        // Unlinked user without company (company_id null, company?->user_id null)
        $unlinkedUser = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'email' => 'noowner@test.com',
            'company_id' => null,
        ]);

        $this->artisan('payroll:reconcile-employee-identities --apply')
            ->expectsOutputToContain('Company owner user ID could not be resolved')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('employees', ['e_user_id' => $unlinkedUser->id]);
    }
}
