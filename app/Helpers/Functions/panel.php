<?php


use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\Notification;
use App\Models\User;

/**
 * Get the owner (admin or company user) of logged-in staff.
 */
function getStaffOwner(): ?User
{
    $user = auth()->user();
    if (! $user || ! in_array($user->role, ['hr', 'employee'])) {
        return null;
    }

    $employee = Employee::where('e_user_id', $user->id)->first();

    return $employee ? User::find($employee->owner_user_id) : null;
}

/**
 * Map system roles to UI display names.
 * Product Owner / Super Admin -> App Owner
 * Company Admin / Administrative Officer -> Admin
 */
if (! function_exists('getRoleDisplayName')) {
    function getRoleDisplayName(?string $role): string
    {
        if (! $role) {
            return '';
        }
        $trimmed = trim($role);
        $lower = strtolower($trimmed);

        if (in_array($lower, ['admin', 'super admin', 'super_admin', 'product owner', 'app owner'])) {
            return 'App Owner';
        }
        if (in_array($lower, ['company', 'company admin', 'company_admin', 'administrative officer', 'admin officer'])) {
            return 'Admin';
        }
        if ($lower === 'hr') {
            return 'HR';
        }
        if ($lower === 'employee') {
            return 'Employee';
        }

        return ucfirst($trimmed);
    }
}

/**
 * Which panel (admin or company) should staff use?
 */
function getStaffPanelRole(): string
{
    $owner = getStaffOwner();

    return ($owner && $owner->role === 'company') ? 'company' : 'admin';
}

/**
 * URL prefix for the current user's panel.
 */
function panelPrefix(): string
{
    if (! auth()->check()) {
        return 'admin';
    }

    if (session('login_portal') === 'employee') {
        return 'employee';
    }

    return match (auth()->user()->role) {
        'company' => 'company',
        'employee' => 'employee',
        'hr' => getStaffPanelRole(),
        default => 'admin',
    };
}

/**
 * Resolve a named route for the current panel.
 * Company users hit /company/* routes; admin users hit /admin/* routes.
 */
function panelRoute(string $name, mixed $parameters = [], bool $absolute = true): string
{
    if (str_starts_with($name, 'settings') && auth()->check()) {
        $portal = auth()->user()->role;

        if ($portal === 'hr' && session('login_portal') === 'employee') {
            $portal = 'employee';
        }

        $settingsRoute = $portal.'.'.$name;
        if (\Illuminate\Support\Facades\Route::has($settingsRoute)) {
            return route($settingsRoute, $parameters, $absolute);
        }
    }

    if ($name === 'dashboard' && auth()->check()) {
        if (session('login_portal') === 'employee') {
            return route('employee.dashboard', $parameters, $absolute);
        }
        $role = auth()->user()->role;
        $roleDashboardMap = [
            'admin' => 'admin.dashboard',
            'company' => 'company.dashboard',
            'hr' => 'hr.dashboard',
            'employee' => 'employee.dashboard',
        ];
        if (isset($roleDashboardMap[$role])) {
            return route($roleDashboardMap[$role], $parameters, $absolute);
        }
    }

    $prefixedName = panelPrefix().'.'.$name;

    if (\Illuminate\Support\Facades\Route::has($prefixedName)) {
        return route($prefixedName, $parameters, $absolute);
    }

    return route($name, $parameters, $absolute);
}

/**
 * Check if a named route exists for the current panel.
 */
function hasPanelRoute(string $name): bool
{
    if ($name === 'dashboard' && auth()->check()) {
        return true;
    }

    $prefixedName = panelPrefix().'.'.$name;

    return \Illuminate\Support\Facades\Route::has($prefixedName) || \Illuminate\Support\Facades\Route::has($name);
}
