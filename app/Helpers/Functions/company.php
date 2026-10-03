<?php


use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\Notification;
use App\Models\User;

/**
 * Get active company ID for current user
 * Returns null for Admin (sees all companies)
 * Returns company_id for Company users (their company only)
 */
function getActiveCompanyId($user = null)
{
    $user = $user ?? auth()->user();

    if (! $user) {
        return session('active_company_id');
    }

    // System Admin - sees all companies (no filter)
    if ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN) {
        return null; // Return null = no company filter = sees all
    }

    // Company Owner / Admin - sees only their company
    elseif (in_array($user->role, ['company', 'company_admin'], true) || $user->role_id === \App\Models\Role::COMPANY_ADMIN) {
        if (! empty($user->company_id)) {
            return (int) $user->company_id;
        }

        $c = \App\Models\Company::where('user_id', $user->id)->first();
        if ($c) {
            return (int) $c->id;
        }

        return session('active_company_id') ? (int) session('active_company_id') : null;
    }

    // HR, Employee - check who their owner is
    else {
        // Get employee record to find owner
        $employee = Employee::where('e_user_id', $user->id)->first();

        if ($employee) {
            $owner = User::find($employee->owner_user_id);

            // If owner is Admin - return null (show all companies)
            if ($owner && $owner->role === 'admin') {
                return null; // Admin's employees see all companies
            }

            // If owner is Company - return company_id (show only that company)
            if ($owner && $owner->role === 'company') {
                $ownerCompany = \App\Models\Company::where('user_id', $owner->id)->first();

                if ($ownerCompany) {
                    return $ownerCompany->id;
                }
            }
        }

        // Get payroll employee master record if user is linked
        $payrollEmployee = null;
        if (! empty($user->emp_id)) {
            $payrollEmployee = \App\Models\EmployeeMaster::withoutGlobalScopes()
                ->find($user->emp_id);
        }

        if ($payrollEmployee && $payrollEmployee->company_id) {
            return $payrollEmployee->company_id;
        }

        // Check session (for company code login)
        $sessionCompanyId = session('active_company_id');
        if ($sessionCompanyId) {
            return $sessionCompanyId;
        }

        if ($user->company_id) {
            return $user->company_id;
        }
    }

    return $user->company_id ?? null;
}

/**
 * True when user can see data from all companies (system admin or admin's staff).
 */
function seesAllCompanies(): bool
{
    return getActiveCompanyId() === null;
}

/**
 * Block access when record belongs to another company.
 */
function authorizeCompanyAccess(?int $companyId): void
{
    $userCompanyId = getActiveCompanyId();

    if ($userCompanyId !== null && (int) $companyId !== (int) $userCompanyId) {
        abort(403, 'Unauthorized access to this company data.');
    }
}

/**
 * Resolve company_id for store operations.
 * Company users: their company. Admin: from request or employee record.
 */
function resolveCompanyId(?int $employeeId = null, ?int $requestedCompanyId = null): ?int
{
    $companyId = getActiveCompanyId();

    if ($companyId) {
        return $companyId;
    }

    if ($requestedCompanyId) {
        return $requestedCompanyId;
    }

    if ($employeeId) {
        return EmployeeMaster::find($employeeId)?->company_id;
    }

    return null;
}

/**
 * Apply company filter to query if company_id is set
 * If company_id is null (admin's employees), no filter is applied
 */
function applyCompanyFilter($query, $companyId = null)
{
    if ($companyId === null) {
        $companyId = getActiveCompanyId();
    }

    if ($companyId) {
        return $query->where('company_id', $companyId);
    }

    return $query; // No filter - shows all companies
}

/**
 * Get active company code from session
 */
function getActiveCompanyCode()
{
    return session('active_company_code');
}

/**
 * Get active company name from session
 */
function getActiveCompanyName()
{
    return session('active_company_name');
}

/**
 * Get complete active company object
 */
function getActiveCompany()
{
    $companyId = getActiveCompanyId();
    if ($companyId) {
        return company::find($companyId);
    }

    return null;
}
