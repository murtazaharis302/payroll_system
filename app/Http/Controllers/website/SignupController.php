<?php

namespace App\Http\Controllers\website;

use App\Http\Controllers\Controller;
use App\Mail\UserActivatedEmail;
use App\Mail\UserRegister;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SignupController extends Controller
{
    /**
     * Handle public user registration.
     */
    public function signup(Request $request)
    {
        // 1. Standard request validation
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'phone'                 => 'required|string|max:50',
        ]);

        // 2. Normalize email: trimmed and lowercased
        $normalizedEmail = strtolower(trim((string) $validated['email']));

        // 3. Direct indexed database lookup
        $existingUser = User::withoutGlobalScopes()
            ->where('email', $normalizedEmail)
            ->first();

        // 4. If account already exists
        if ($existingUser) {
            // Case A: Active / confirmed account
            if ((int) $existingUser->status === 1 || $existingUser->email_verified_at !== null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'An account already exists with this email. Please sign in or use Forgot Password.',
                ], 422);
            }

            // Case B: Unconfirmed account - rate-limit confirmation email resends
            $rateLimitKey = 'resend-confirmation:' . $existingUser->id;
            $isThrottled = RateLimiter::tooManyAttempts($rateLimitKey, 3);

            if (!$isThrottled) {
                RateLimiter::hit($rateLimitKey, 300); // Max 3 resend attempts per 5 minutes

                // Only generate a token if one is somehow missing; preserve existing valid tokens
                if (empty($existingUser->confirmation_token)) {
                    $existingUser->update(['confirmation_token' => Str::random(64)]);
                }

                if (config('mail.notifications_enabled', true)) {
                    try {
                        Mail::to($existingUser->email)->queue(new UserRegister($existingUser));
                    } catch (\Throwable $e) {
                        Log::error('User registration confirmation resend email queuing failed: ' . $e->getMessage());
                    }
                }
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'This email is already registered but has not been confirmed yet. Please check your email for the confirmation link.',
            ], 422);
        }

        // 5. Create new user with normalized email, catching race-condition unique violations
        $token = Str::random(64);
        try {
            $user = User::create([
                'name'               => trim((string) $validated['name']),
                'email'              => $normalizedEmail,
                'phone'              => trim((string) $validated['phone']),
                'password'           => Hash::make($validated['password']),
                'role'               => 'user',
                'status'             => 0,
                'confirmation_token' => $token,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            Log::warning("Race-condition duplicate user registration caught for email: {$normalizedEmail}");
            return response()->json([
                'status'  => 'error',
                'message' => 'An account already exists with this email. Please sign in or use Forgot Password.',
            ], 422);
        } catch (\Throwable $e) {
            Log::error('User registration failed due to database/system error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Registration failed due to a system error. Please try again later.',
            ], 500);
        }

        // 6. Queue confirmation email
        if (config('mail.notifications_enabled', true)) {
            try {
                DB::afterCommit(function () use ($user) {
                    Mail::to($user->email)->queue(new UserRegister($user));
                });
            } catch (\Throwable $e) {
                Log::error('User registration email queuing failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status'  => 'Success',
            'message' => 'Registration successful.',
        ], 200);
    }

    /**
     * Confirm user email registration.
     */
    public function confirm($token)
    {
        $user = User::where('confirmation_token', $token)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'Error',
                'message' => 'Email Already Confirmed.',
            ], 404);
        }

        // Check if already confirmed
        if ((int) $user->status === 1) {
            return redirect('/');
        }

        $user->update([
            'status'             => 1,
            'confirmation_token' => null,
        ]);

        if (config('mail.notifications_enabled', true)) {
            try {
                DB::afterCommit(function () use ($user) {
                    Mail::to($user->email)->queue(new UserActivatedEmail($user));
                });
            } catch (\Throwable $e) {
                Log::error('User activation email queuing failed: ' . $e->getMessage());
            }
        }

        return redirect('/');
    }
}
