<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;
use App\Models\Menu;
use App\Models\MenuRoleAccess;

class CheckMenuAccess
{
    /**
     * Map a route name to its controlling Menu module.
     */
    protected function getMenuForRoute(string $routeName): ?Menu
    {
        if (empty($routeName)) {
            return null;
        }

        $cleanRouteName = str_replace('admin.', '', $routeName);

        // 1. Direct match on route_name
        $menu = Menu::where('route_name', $routeName)
            ->orWhere('route_name', $cleanRouteName)
            ->first();

        if ($menu) {
            return $menu;
        }

        // 2. Comprehensive pattern matching for sub-routes & endpoints
        $moduleRoutePrefixes = [
            'employees'         => ['employees', 'createEmployee', 'storeEmployee', 'editEmployee', 'updateEmployee', 'deleteEmployee', 'user'],
            'companies'         => ['companies', 'createCompany', 'storeCompany', 'editCompany', 'updateCompany'],
            'payroll_employees' => ['payroll-employees', 'company.payroll-employees'],
            'departments'       => ['departments'],
            'income_types'      => ['income-types'],
            'deduction_types'   => ['deduction-types'],
            'loan_types'        => ['loan-types'],
            'emp_incomes'       => ['emp-incomes'],
            'emp_deductions'    => ['emp-deductions'],
            'gov_contributions' => ['government-contributions'],
            'attendance'        => ['attendance'],
            'hr_adjustments'    => ['hr-adjustments'],
            'payroll_periods'   => ['payroll-periods'],
            'vale_loans'        => ['loan-monitor'],
            'loan_requests'     => ['loan-requests'],
            'payroll_drafts'    => ['payroll-drafts'],
            'payroll_approvals' => ['payroll-approvals'],
            'reports'           => ['reports'],
            'activity_logs'     => ['activity-logs'],
            'permissions'       => ['permissions', 'hr-permissions'],
            'settings'          => ['settings'],
            'dashboard'         => ['dashboard'],
        ];

        foreach ($moduleRoutePrefixes as $module => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_contains($cleanRouteName, $prefix) || str_starts_with($cleanRouteName, $prefix)) {
                    return Menu::where('module', $module)->first();
                }
            }
        }

        return null;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $roleId = (int) ($user->role_id ?? 0);
        $roleName = strtolower((string) ($user->role ?? ''));

        // 1. Super Admin global bypass
        if ($roleId === Role::SUPER_ADMIN || $roleName === 'admin') {
            return $next($request);
        }

        // Normalize role ID from string if missing
        if ($roleId === 0) {
            if (in_array($roleName, ['company', 'company_admin'], true)) {
                $roleId = Role::COMPANY_ADMIN;
            } elseif ($roleName === 'hr') {
                $roleId = Role::HR;
            } elseif ($roleName === 'employee') {
                $roleId = Role::EMPLOYEE;
            }
        }

        $companyId = (int) ($user->company_id ?: getActiveCompanyId($user) ?: session('active_company_id') ?: 1);
        $routeName = (string) $request->route()?->getName();

        // Resolve Menu module for route
        $menu = $this->getMenuForRoute($routeName);

        if (!$menu) {
            return $next($request);
        }

        // 2. Company Admin self-lockout prevention on permissions matrix
        if ($menu->module === 'permissions' && $roleId === Role::COMPANY_ADMIN) {
            return $next($request);
        }

        // 3. Single source of truth matrix evaluation
        $hasAccess = MenuRoleAccess::checkAccess($companyId, $roleId, $menu->id);
        if (!$hasAccess) {
            if ($roleId === Role::COMPANY_ADMIN) {
                abort(403, 'This feature is not enabled for your company subscription.');
            }
            abort(403, 'You do not have permission to access this module.');
        }

        return $next($request);
    }
}
