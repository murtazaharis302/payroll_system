<?php

namespace Tests\Feature;

use App\Mail\UserRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicSignupDuplicateEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('resend-confirmation:*');
    }

    public function test_new_email_registers_successfully(): void
    {
        Mail::fake();

        $response = $this->postJson(route('user.signup'), [
            'name'                  => 'John Doe',
            'email'                 => 'john.doe@example.com',
            'phone'                 => '09123456789',
            'password'              => 'SecretPassword123',
            'password_confirmation' => 'SecretPassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => 'Success',
            'message' => 'Registration successful.',
        ]);

        $this->assertDatabaseHas('users', [
            'email'  => 'john.doe@example.com',
            'status' => 0,
        ]);

        $createdUser = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertNotNull($createdUser->confirmation_token);

        Mail::assertQueued(UserRegister::class, function ($mail) use ($createdUser) {
            return $mail->user->id === $createdUser->id;
        });
    }

    public function test_active_existing_email_returns_friendly_account_exists_message(): void
    {
        Mail::fake();

        User::create([
            'name'     => 'Active User',
            'email'    => 'active.user@example.com',
            'phone'    => '09111111111',
            'password' => Hash::make('SecretPassword123'),
            'status'   => 1,
            'role'     => 'user',
        ]);

        $this->assertEquals(1, User::count());

        $response = $this->postJson(route('user.signup'), [
            'name'                  => 'Another Name',
            'email'                 => 'active.user@example.com',
            'phone'                 => '09222222222',
            'password'              => 'SecretPassword123',
            'password_confirmation' => 'SecretPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status'  => 'error',
            'message' => 'An account already exists with this email. Please sign in or use Forgot Password.',
        ]);

        $response->assertJsonMissing(['errors' => ['email']]);
        $this->assertEquals(1, User::count());
        Mail::assertNothingQueued();
    }

    public function test_unconfirmed_existing_email_returns_unconfirmed_message_and_resends_confirmation_link(): void
    {
        Mail::fake();

        $existingToken = 'pre_existing_valid_token_12345';
        $unconfirmedUser = User::create([
            'name'               => 'Pending User',
            'email'              => 'pending.user@example.com',
            'phone'              => '09111111111',
            'password'           => Hash::make('SecretPassword123'),
            'status'             => 0,
            'confirmation_token' => $existingToken,
            'role'               => 'user',
        ]);

        $response = $this->postJson(route('user.signup'), [
            'name'                  => 'Pending Attempt',
            'email'                 => 'pending.user@example.com',
            'phone'                 => '09333333333',
            'password'              => 'SecretPassword123',
            'password_confirmation' => 'SecretPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status'  => 'error',
            'message' => 'This email is already registered but has not been confirmed yet. Please check your email for the confirmation link.',
        ]);

        $this->assertEquals(1, User::count());

        $unconfirmedUser->refresh();
        $this->assertEquals($existingToken, $unconfirmedUser->confirmation_token, 'Existing token must not be regenerated/invalidated.');

        Mail::assertQueued(UserRegister::class, function ($mail) use ($unconfirmedUser) {
            return $mail->user->id === $unconfirmedUser->id;
        });
    }

    public function test_email_normalization_trims_and_lowercases_email_for_lookup_and_saving(): void
    {
        Mail::fake();

        User::create([
            'name'     => 'Existing Normal User',
            'email'    => 'normalized@example.com',
            'phone'    => '09111111111',
            'password' => Hash::make('SecretPassword123'),
            'status'   => 1,
            'role'     => 'user',
        ]);

        // Submitting with leading/trailing spaces and uppercase characters
        $response = $this->postJson(route('user.signup'), [
            'name'                  => 'Duplicate Tester',
            'email'                 => "  NORMALIZED@Example.COM  \t",
            'phone'                 => '09222222222',
            'password'              => 'SecretPassword123',
            'password_confirmation' => 'SecretPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status'  => 'error',
            'message' => 'An account already exists with this email. Please sign in or use Forgot Password.',
        ]);

        // Creating a new user with untrimmed uppercase email saves cleanly in normalized form
        $newResponse = $this->postJson(route('user.signup'), [
            'name'                  => 'New Normalized',
            'email'                 => "  NEW.NORMALIZED@Example.COM  ",
            'phone'                 => '09444444444',
            'password'              => 'SecretPassword123',
            'password_confirmation' => 'SecretPassword123',
        ]);

        $newResponse->assertStatus(200);
        $this->assertDatabaseHas('users', ['email' => 'new.normalized@example.com']);
        $this->assertDatabaseMissing('users', ['email' => '  NEW.NORMALIZED@Example.COM  ']);
    }

    public function test_confirmation_resend_is_rate_limited(): void
    {
        Mail::fake();

        $unconfirmedUser = User::create([
            'name'               => 'Throttled User',
            'email'              => 'throttled@example.com',
            'phone'              => '09111111111',
            'password'           => Hash::make('SecretPassword123'),
            'status'             => 0,
            'confirmation_token' => 'token_123',
            'role'               => 'user',
        ]);

        // Send 3 requests (within the rate limit)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson(route('user.signup'), [
                'name'                  => 'Throttled User',
                'email'                 => 'throttled@example.com',
                'phone'                 => '09111111111',
                'password'              => 'SecretPassword123',
                'password_confirmation' => 'SecretPassword123',
            ]);
            $response->assertStatus(422);
        }

        Mail::assertQueued(UserRegister::class, 3);

        // 4th request: should still return clean unconfirmed message but NOT queue an additional email
        $response4 = $this->postJson(route('user.signup'), [
            'name'                  => 'Throttled User',
            'email'                 => 'throttled@example.com',
            'phone'                 => '09111111111',
            'password'              => 'SecretPassword123',
            'password_confirmation' => 'SecretPassword123',
        ]);

        $response4->assertStatus(422);
        $response4->assertJson([
            'status'  => 'error',
            'message' => 'This email is already registered but has not been confirmed yet. Please check your email for the confirmation link.',
        ]);

        Mail::assertQueued(UserRegister::class, 3);
    }

    public function test_race_condition_unique_constraint_violation_returns_friendly_message_without_sql_leak(): void
    {
        Mail::fake();

        // Simulate a concurrent insert throwing UniqueConstraintViolationException
        User::creating(function ($user) {
            if ($user->email === 'concurrent@example.com') {
                throw new \Illuminate\Database\UniqueConstraintViolationException(
                    'sqlite',
                    'insert into users (email) values ("concurrent@example.com")',
                    [],
                    new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: users.email', '23000')
                );
            }
        });

        $response = $this->postJson(route('user.signup'), [
            'name'                  => 'Concurrent User',
            'email'                 => 'concurrent@example.com',
            'phone'                 => '09111111111',
            'password'              => 'SecretPassword123',
            'password_confirmation' => 'SecretPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status'  => 'error',
            'message' => 'An account already exists with this email. Please sign in or use Forgot Password.',
        ]);

        // Ensure SQL exception text is never exposed in response body
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('Integrity constraint violation', $response->getContent());
        $this->assertStringNotContainsString('UniqueConstraintViolationException', $response->getContent());
    }

    public function test_validation_errors_for_other_fields_remain_standard(): void
    {
        $response = $this->postJson(route('user.signup'), [
            'name'                  => '',
            'email'                 => 'not-an-email',
            'phone'                 => '',
            'password'              => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'phone', 'password']);
    }
}
