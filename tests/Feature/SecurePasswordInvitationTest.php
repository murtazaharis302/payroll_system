<?php

namespace Tests\Feature;

use App\Mail\EmployeeConformation;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SecurePasswordInvitationTest extends TestCase
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

    public function test_manual_employee_creation_hashes_password_without_queuing_invitation(): void
    {
        Mail::fake();

        $companyUser = User::factory()->create([
            'role_id' => 2,
            'role' => 'company',
        ]);
        $company = Company::create([
            'user_id' => $companyUser->id,
            'name' => 'Security Corp',
            'company_code' => 'SEC-0001',
            'email' => 'sec@test.com',
            'status' => 1,
        ]);
        $companyUser->update(['company_id' => $company->id]);

        $this->actingAs($companyUser);

        $response = $this->post(route('storeEmployee'), [
            'name' => 'Secure Employee',
            'email' => 'secure.employee@test.com',
            'phone' => '1234567890',
            'password' => 'InitialPassword123!',
            'password_confirmation' => 'InitialPassword123!',
            'role' => 'employee',
            'address' => '123 Security Way',
            'age' => '29',
        ]);

        $response->assertRedirect(route('employee'));

        $user = User::withoutGlobalScopes()->where('email', 'secure.employee@test.com')->first();
        $this->assertNotNull($user);

        $this->assertNotSame('InitialPassword123!', $user->password);
        $this->assertTrue(Hash::check('InitialPassword123!', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'secure.employee@test.com',
        ]);

        Mail::assertNotQueued(EmployeeConformation::class);
    }

    public function test_invitation_mode_queues_secure_setup_link_without_plaintext_password(): void
    {
        config(['auth.employee_password_provisioning' => 'invitation']);
        Mail::fake();

        $companyUser = User::factory()->create(['role_id' => 2, 'role' => 'company']);
        $company = Company::create([
            'user_id' => $companyUser->id,
            'name' => 'Invitation Corp',
            'company_code' => 'INV-0001',
            'email' => 'invitation@test.com',
            'status' => 1,
        ]);

        $result = app(\App\Services\EmployeeProvisioningService::class)->provision([
            'name' => 'Invited Employee',
            'email' => 'invited.employee@test.com',
            'phone' => '1234567890',
            'role' => 'employee',
            'address' => '123 Invitation Way',
        ], $companyUser->id, $company->id);

        Mail::assertQueued(EmployeeConformation::class, function ($mail) {
            $reflector = new \ReflectionClass($mail);
            $this->assertFalse($reflector->hasProperty('password'));

            $rendered = $mail->render();
            $this->assertStringNotContainsString('PlaintextPassword123!', $rendered);
            $this->assertStringContainsString('Set Up Password', $rendered);
            $this->assertStringContainsString('password/reset', $rendered);

            return $mail->hasTo('invited.employee@test.com');
        });

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $result['user']->email,
        ]);
    }

    public function test_invitation_setup_link_allows_user_to_set_password_and_token_is_single_use(): void
    {
        config(['auth.employee_password_provisioning' => 'invitation']);

        $user = User::factory()->create([
            'email' => 'setup.user@test.com',
            'password' => Hash::make('unusable-initial-hash'),
        ]);

        $token = Password::broker()->createToken($user);
        $this->assertNotNull($token);

        // Perform password setup via reset endpoint
        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->password));

        // Reusing token must fail
        $reuseResponse = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ]);

        $reuseResponse->assertSessionHasErrors('email');
    }

    public function test_expired_token_is_rejected(): void
    {
        config(['auth.employee_password_provisioning' => 'invitation']);

        $user = User::factory()->create([
            'email' => 'expired.user@test.com',
        ]);

        $token = Password::broker()->createToken($user);

        // Manually age the token created_at in password_reset_tokens table to 120 minutes ago
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(120)]);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_new_invitation_token_invalidates_previous_token(): void
    {
        config(['auth.employee_password_provisioning' => 'invitation']);

        $user = User::factory()->create([
            'email' => 'reissue.user@test.com',
        ]);

        $token1 = Password::broker()->createToken($user);

        // Issue second token
        $token2 = Password::broker()->createToken($user);

        // Attempting token1 must fail
        $response1 = $this->post(route('password.update'), [
            'token' => $token1,
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response1->assertSessionHasErrors('email');

        // Token 2 must succeed
        $response2 = $this->post(route('password.update'), [
            'token' => $token2,
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response2->assertSessionHasNoErrors();
    }
}
