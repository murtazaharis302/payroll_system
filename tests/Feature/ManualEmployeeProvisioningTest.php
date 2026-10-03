<?php

namespace Tests\Feature;

use App\Mail\EmployeeConformation;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\EmployeeProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManualEmployeeProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private User $companyAdmin;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.employee_password_provisioning' => 'manual']);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        $this->companyAdmin = User::factory()->create([
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);
        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Manual Password Corp',
            'company_code' => 'MAN-0001',
            'email' => 'manual-password@test.com',
            'status' => 1,
        ]);
        $this->companyAdmin->update(['company_id' => $this->company->id]);
    }

    public function test_employee_creation_requires_an_initial_password(): void
    {
        $response = $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload([
            'password' => null,
            'password_confirmation' => null,
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'new.employee@test.com']);
    }

    public function test_employee_creation_requires_matching_password_confirmation(): void
    {
        $response = $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload([
            'password_confirmation' => 'DifferentPassword123!',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'new.employee@test.com']);
    }

    public function test_employee_creation_rejects_a_short_password(): void
    {
        $response = $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'new.employee@test.com']);
    }

    public function test_manual_employee_creation_hashes_password_and_creates_no_invitation(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload());

        $response->assertRedirect(route('employee'));
        $user = User::withoutGlobalScopes()->where('email', 'new.employee@test.com')->firstOrFail();

        $this->assertNotSame('InitialPassword123!', $user->password);
        $this->assertTrue(Hash::check('InitialPassword123!', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Mail::assertNotQueued(EmployeeConformation::class);
    }

    public function test_hr_account_creation_uses_the_same_manual_password_rule(): void
    {
        $response = $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload([
            'email' => 'new.hr@test.com',
            'role' => 'hr',
        ]));

        $response->assertRedirect(route('employee'));
        $user = User::withoutGlobalScopes()->where('email', 'new.hr@test.com')->firstOrFail();

        $this->assertSame('hr', $user->role);
        $this->assertTrue(Hash::check('InitialPassword123!', $user->password));
    }

    public function test_manual_provisioning_service_rejects_missing_password(): void
    {
        $this->expectException(ValidationException::class);

        app(EmployeeProvisioningService::class)->provision(
            $this->payload(['password' => null, 'password_confirmation' => null]),
            $this->companyAdmin->id,
            $this->company->id
        );
    }

    public function test_created_employee_can_log_in_immediately_with_initial_password(): void
    {
        $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload())
            ->assertRedirect(route('employee'));

        Auth::logout();

        $response = $this->post(route('employeeLogin'), [
            'email' => 'new.employee@test.com',
            'password' => 'InitialPassword123!',
        ]);

        $response->assertRedirect(route('employee.dashboard'));
        $this->assertAuthenticatedAs(
            User::withoutGlobalScopes()->where('email', 'new.employee@test.com')->firstOrFail()
        );
    }

    public function test_duplicate_email_validation_remains_intact(): void
    {
        User::factory()->create(['email' => 'new.employee@test.com']);

        $response = $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload());

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::withoutGlobalScopes()->where('email', 'new.employee@test.com')->count());
    }

    public function test_company_admin_cannot_assign_a_new_account_to_another_company(): void
    {
        $otherOwner = User::factory()->create(['role_id' => Role::COMPANY_ADMIN]);
        $otherCompany = Company::create([
            'user_id' => $otherOwner->id,
            'name' => 'Other Tenant',
            'company_code' => 'OTHER-002',
            'email' => 'other-tenant@test.com',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload([
            'company_id' => $otherCompany->id,
        ]));

        $response->assertSessionHasErrors('company_id');
        $this->assertDatabaseMissing('users', ['email' => 'new.employee@test.com']);
    }

    public function test_normal_employee_edit_does_not_change_or_expose_password(): void
    {
        $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload());

        $user = User::withoutGlobalScopes()->where('email', 'new.employee@test.com')->firstOrFail();
        $employee = $user->employees()->firstOrFail();
        $originalHash = $user->password;

        $editResponse = $this->get(route('editEmployee', $employee->id));
        $editResponse->assertOk();
        $editResponse->assertDontSee($originalHash);
        $editResponse->assertDontSee('name="password"', false);

        $response = $this->put(route('updateEmployee', $user->id), [
            'name' => 'Updated Employee Name',
            'email' => 'updated.employee@test.com',
            'phone' => '0987654321',
            'role' => 'employee',
            'address' => '456 Updated Street',
        ]);

        $response->assertRedirect(route('employee'));
        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertTrue(Hash::check('InitialPassword123!', $user->fresh()->password));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Employee',
            'email' => 'new.employee@test.com',
            'phone' => '1234567890',
            'password' => 'InitialPassword123!',
            'password_confirmation' => 'InitialPassword123!',
            'role' => 'employee',
            'address' => '123 Manual Street',
            'age' => '30',
        ], $overrides);
    }

    public function test_edit_employee_resolves_via_user_id_even_when_employee_id_differs(): void
    {
        // 1. Create a dummy employee record to offset the employees table auto-increment ID
        $dummyUser = User::factory()->create(['company_id' => $this->company->id]);
        \App\Models\Employee::create([
            'e_user_id' => $dummyUser->id,
            'owner_user_id' => $this->companyAdmin->id,
            'id_number' => 'EMP-DUMMY-999',
            'address' => 'Dummy St',
        ]);

        // 2. Provision new user
        $this->actingAs($this->companyAdmin)->post(route('storeEmployee'), $this->payload([
            'email' => 'mismatch.id@test.com',
        ]));

        $user = User::withoutGlobalScopes()->where('email', 'mismatch.id@test.com')->firstOrFail();
        $employee = $user->employees()->firstOrFail();

        // Prove employee ID and user ID are different
        $this->assertNotEquals($user->id, $employee->id);

        // 3. Request edit page using $user->id (matching index page contract)
        $response = $this->get(route('editEmployee', $user->id));
        $response->assertOk();
        $response->assertSee('Edit User Account');
        $response->assertSee('mismatch.id@test.com');
    }

    public function test_hr_access_to_edit_employee_governed_by_permission_matrix(): void
    {
        $this->seed(\Database\Seeders\MenusTableSeeder::class);
        $empMenu = \App\Models\Menu::where('module', 'employees')->firstOrFail();

        $hrUser = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'hr',
            'role_id' => Role::HR,
        ]);

        $targetUser = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'role_id' => Role::EMPLOYEE,
        ]);

        // 1. Enabled in matrix -> 200 OK
        \App\Models\MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $empMenu->id],
            ['is_enabled' => 1]
        );
        $this->actingAs($hrUser)->get(route('editEmployee', $targetUser->id))->assertOk();

        // 2. Disabled in matrix -> 403 Forbidden
        \App\Models\MenuRoleAccess::updateOrCreate(
            ['company_id' => $this->company->id, 'role_id' => Role::HR, 'menu_id' => $empMenu->id],
            ['is_enabled' => 0]
        );
        $this->actingAs($hrUser)->get(route('editEmployee', $targetUser->id))->assertStatus(403);
    }
}
