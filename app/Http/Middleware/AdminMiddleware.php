<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AdminMiddleware
{
    private const LOGIN_FORM_ROLES = ['adminForm', 'userForm', 'employeeForm', 'hrForm', 'companyForm'];

    public function handle(Request $request, Closure $next, $role): Response
    {
        // Strict Company Admin check
        if ($role === 'company_strict') {
            if (!Auth::check() || Auth::user()->role !== 'company') {
                abort(403, 'Forbidden');
            }
            return $next($request);
        }

        // Guest users can always open login pages
        if (!Auth::check() && in_array($role, self::LOGIN_FORM_ROLES, true)) {
            return $next($request);
        }

        // Company login page fallback
        if (!Auth::check() && ($role === 'company' || $role === 'companyForm') && $request->is('company/login')) {
            return $next($request);
        }

        // Already logged in — send to dashboard instead of 401 on login URLs
        if (Auth::check() && $this->isLoginPage($request, $role)) {
            return redirect('/' . panelPrefix() . '/dashboard');
        }

        if (!Auth::check() && $request->has('email')) {
            $current_user = User::where('email', $request->email)->first();

            if (!$current_user) {
                return redirect()->back()->with('statusDanger', 'User not found.');
            }

            if ($current_user->status != 1) {
                return redirect()->back()->with('statusDanger', 'Your account is inactive or pending approval.');
            }

            if ($role !== $current_user->role) {
                if ($role === 'employeeForm' && $current_user->role === 'hr') {
                    // HR role user allowed to log in via employee form
                } else {
                    $roleNames = [
                        'admin' => 'Admin',
                        'company' => 'Company',
                        'employee' => 'Employee',
                        'hr' => 'HR',
                        'user' => 'User'
                    ];

                    $expectedRole = $roleNames[$role] ?? ucfirst($role);
                    $actualRole = $roleNames[$current_user->role] ?? ucfirst($current_user->role);

                    return redirect()->back()->with('statusDanger', $expectedRole . ' Access Only. This account is registered as ' . $actualRole);
                }
            }

            return $next($request);
        }

        if (Auth::check()) {
            return $next($request);
        }

        return $next($request);
    }

    private function isLoginPage(Request $request, string $role): bool
    {
        if (in_array($role, self::LOGIN_FORM_ROLES, true)) {
            return true;
        }

        return $role === 'company' && $request->is('company/login') && $request->isMethod('GET');
    }
}
