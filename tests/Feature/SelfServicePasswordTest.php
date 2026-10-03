<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SelfServicePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => Role::SUPER_ADMIN, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => Role::COMPANY_ADMIN, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => Role::HR, 'name' => 'HR']);
        Role::firstOrCreate(['id' => Role::EMPLOYEE, 'name' => 'Employee']);
    }

    public function test_every_internal_portal_user_can_open_their_settings_page(): void
    {
        $portals = [
            'admin' => Role::SUPER_ADMIN,
            'company' => Role::COMPANY_ADMIN,
            'hr' => Role::HR,
            'employee' => Role::EMPLOYEE,
        ];

        foreach ($portals as $portal => $roleId) {
            $user = $this->makeUser($roleId, "{$portal}@test.com");

            $response = $this->actingAs($user)->get(route("{$portal}.settings"));

            $response->assertOk();
            $response->assertSee('Change Password');
            $response->assertDontSee($user->password);
            $response->assertSee(route("{$portal}.settings.update-password"), false);
        }
    }

    public function test_unauthenticated_users_cannot_open_or_submit_settings(): void
    {
        $this->get(route('employee.settings'))->assertRedirect(route('employeeLoginForm'));
        $this->post(route('employee.settings.update-password'), [])->assertRedirect(route('employeeLoginForm'));
    }

    public function test_correct_current_password_changes_only_the_authenticated_users_password(): void
    {
        $user = $this->makeUser(Role::EMPLOYEE, 'employee@test.com');
        $otherUser = $this->makeUser(Role::EMPLOYEE, 'other.employee@test.com');
        $oldSessionId = session()->getId();

        $response = $this->actingAs($user)->post(route('employee.settings.update-password'), [
            'user_id' => $otherUser->id,
            'current_password' => 'CurrentPassword123!',
            'new_password' => 'UpdatedPassword123!',
            'new_password_confirmation' => 'UpdatedPassword123!',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Password updated successfully!');
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertTrue(Hash::check('UpdatedPassword123!', $user->fresh()->password));
        $this->assertFalse(Hash::check('CurrentPassword123!', $user->fresh()->password));
        $this->assertTrue(Hash::check('CurrentPassword123!', $otherUser->fresh()->password));
        $response->assertDontSee($user->fresh()->password);
    }

    public function test_incorrect_current_password_is_rejected(): void
    {
        $user = $this->makeUser(Role::HR, 'hr@test.com');

        $response = $this->actingAs($user)->post(route('hr.settings.update-password'), [
            'current_password' => 'IncorrectPassword123!',
            'new_password' => 'UpdatedPassword123!',
            'new_password_confirmation' => 'UpdatedPassword123!',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('CurrentPassword123!', $user->fresh()->password));
    }

    public function test_new_password_confirmation_and_minimum_length_are_enforced(): void
    {
        $user = $this->makeUser(Role::COMPANY_ADMIN, 'company@test.com');

        $mismatch = $this->actingAs($user)->post(route('company.settings.update-password'), [
            'current_password' => 'CurrentPassword123!',
            'new_password' => 'UpdatedPassword123!',
            'new_password_confirmation' => 'DifferentPassword123!',
        ]);
        $mismatch->assertSessionHasErrors('new_password');

        $short = $this->actingAs($user)->post(route('company.settings.update-password'), [
            'current_password' => 'CurrentPassword123!',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ]);
        $short->assertSessionHasErrors('new_password');

        $this->assertTrue(Hash::check('CurrentPassword123!', $user->fresh()->password));
    }

    public function test_new_password_must_differ_from_current_password(): void
    {
        $user = $this->makeUser(Role::SUPER_ADMIN, 'admin@test.com');

        $response = $this->actingAs($user)->post(route('admin.settings.update-password'), [
            'current_password' => 'CurrentPassword123!',
            'new_password' => 'CurrentPassword123!',
            'new_password_confirmation' => 'CurrentPassword123!',
        ]);

        $response->assertSessionHasErrors('new_password');
        $this->assertTrue(Hash::check('CurrentPassword123!', $user->fresh()->password));
    }

    public function test_active_company_and_submitted_tenant_data_cannot_change_the_target_user(): void
    {
        $companyAdmin = $this->makeUser(Role::COMPANY_ADMIN, 'tenant-admin@test.com');
        $targetUser = $this->makeUser(Role::EMPLOYEE, 'tenant-employee@test.com');
        $otherCompany = Company::create([
            'user_id' => $companyAdmin->id,
            'name' => 'Other Company',
            'company_code' => 'OTHER-001',
            'email' => 'other-company@test.com',
            'status' => 1,
        ]);

        $response = $this->actingAs($companyAdmin)
            ->withSession(['company_id' => $otherCompany->id])
            ->post(route('company.settings.update-password'), [
                'user_id' => $targetUser->id,
                'company_id' => $otherCompany->id,
                'current_password' => 'CurrentPassword123!',
                'new_password' => 'TenantSafePassword123!',
                'new_password_confirmation' => 'TenantSafePassword123!',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('TenantSafePassword123!', $companyAdmin->fresh()->password));
        $this->assertTrue(Hash::check('CurrentPassword123!', $targetUser->fresh()->password));
    }

    public function test_hr_employee_portal_uses_employee_settings_route(): void
    {
        $hr = $this->makeUser(Role::HR, 'dual-portal-hr@test.com');

        $this->actingAs($hr)->withSession(['login_portal' => 'employee']);

        $this->assertSame(route('employee.settings'), panelRoute('settings'));
        $this->assertSame(
            route('employee.settings.update-password'),
            panelRoute('settings.update-password')
        );
    }

    private function makeUser(int $roleId, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('CurrentPassword123!'),
            'role_id' => $roleId,
        ]);
    }
}
