<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuRoleAccess extends Model
{
    use HasFactory;

    protected $table = 'menu_role_access';

    protected $fillable = [
        'menu_id',
        'company_id',
        'role_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    // Relationships
    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if a specific role in a company has access to a menu ID.
     */
    public static function checkAccess($companyId, $roleId, $menuId): bool
    {
        // Company Admin is always granted access — never blocked by DB rows
        if ((int) $roleId === Role::COMPANY_ADMIN) {
            return true;
        }

        $access = self::where('company_id', $companyId)
            ->where('role_id', $roleId)
            ->where('menu_id', $menuId)
            ->first();

        if ($access) {
            return (bool) $access->is_enabled;
        }

        // Default fallbacks when no explicit database override row exists:

        if ((int) $roleId === Role::HR) {
            $menu = Menu::find($menuId);
            if ($menu && $menu->module) {
                $hrModules = [
                    'dashboard', 'employees', 'payroll_employees', 'departments',
                    'income_types', 'deduction_types', 'loan_types', 'emp_incomes',
                    'emp_deductions', 'gov_contributions', 'attendance', 'hr_adjustments',
                    'payroll_periods', 'vale_loans', 'loan_requests', 'payroll_drafts',
                    'payroll_approvals', 'reports', 'settings',
                    'payroll_income_column_visibility', 'payroll_income_inputs',
                    'payroll_income_rate_visibility', 'payroll_deduction_inputs'
                ];
                return in_array($menu->module, $hrModules, true);
            }
        }

        if ((int) $roleId === Role::EMPLOYEE) {
            $menu = Menu::find($menuId);
            if ($menu && $menu->module) {
                return in_array($menu->module, ['dashboard', 'reports', 'settings'], true);
            }
        }

        return false;
    }

    /**
     * Check if a user has access to a module.
     * Single Source of Truth for both Sidebar and Middleware.
     */
    public static function hasModuleAccess($user, string $module): bool
    {
        if (!$user) {
            return false;
        }

        $roleId = (int) ($user->role_id ?? 0);
        $roleName = strtolower((string) ($user->role ?? ''));

        // Super Admin global bypass
        if ($roleId === Role::SUPER_ADMIN || $roleName === 'admin') {
            return true;
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

        // Self-lockout prevention: Company Admin always has access to permissions matrix
        if ($module === 'permissions' && $roleId === Role::COMPANY_ADMIN) {
            return true;
        }

        $companyId = $user->company_id
            ?: getActiveCompanyId($user)
            ?: session('active_company_id')
            ?: 1;

        $menu = Menu::where('module', $module)->first();

        if ($menu) {
            return self::checkAccess($companyId, $roleId, $menu->id);
        }

        // Default fallbacks when menu table is unseeded:
        if ($roleId === Role::COMPANY_ADMIN) {
            return true;
        }

        if ($roleId === Role::HR) {
            $hrModules = [
                'dashboard', 'employees', 'payroll_employees', 'departments',
                'income_types', 'deduction_types', 'loan_types', 'emp_incomes',
                'emp_deductions', 'gov_contributions', 'attendance', 'hr_adjustments',
                'payroll_periods', 'vale_loans', 'loan_requests', 'payroll_drafts',
                'payroll_approvals', 'reports', 'settings',
                'payroll_income_column_visibility', 'payroll_income_inputs',
                'payroll_income_rate_visibility', 'payroll_deduction_inputs'
            ];
            return in_array($module, $hrModules, true);
        }

        if ($roleId === Role::EMPLOYEE) {
            return in_array($module, ['dashboard', 'reports', 'settings'], true);
        }

        return false;
    }
}
