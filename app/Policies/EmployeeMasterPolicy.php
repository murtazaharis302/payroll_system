<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeMaster;
use App\Models\Role;
use App\Models\User;
use App\Models\ActivityLog;

class EmployeeMasterPolicy
{
    /**
     * Determine whether the user can view any employee masters.
     */
    public function viewAny(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    /**
     * Determine whether the user can view the employee master.
     */
    public function view(User $user, EmployeeMaster $employeeMaster): bool
    {
        return $this->checkOwnership($user, $employeeMaster, 'view_employee_master');
    }

    /**
     * Determine whether the user can create employee masters.
     */
    public function create(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    /**
     * Determine whether the user can update the employee master.
     */
    public function update(User $user, EmployeeMaster $employeeMaster): bool
    {
        return $this->checkOwnership($user, $employeeMaster, 'update_employee_master');
    }

    /**
     * Determine whether the user can delete the employee master.
     */
    public function delete(User $user, EmployeeMaster $employeeMaster): bool
    {
        return $this->checkOwnership($user, $employeeMaster, 'delete_employee_master');
    }

    /**
     * Helper to verify tenant company ownership and log deliberate SuperAdmin cross-tenant access.
     */
    protected function checkOwnership(User $user, EmployeeMaster $employeeMaster, string $action): bool
    {
        $modelCompanyId = (int) ($employeeMaster->company_id ?? 0);
        $userCompanyId = (int) (session('company_id') ?? $user->company_id ?? 0);

        if ((int) $user->role_id === Role::SUPER_ADMIN) {
            if ($modelCompanyId > 0 && $userCompanyId > 0 && $modelCompanyId !== $userCompanyId) {
                ActivityLog::log(
                    'super_admin_cross_tenant_access',
                    'EmployeeMasterPolicy',
                    "SuperAdmin accessed EmployeeMaster ID {$employeeMaster->id} belonging to Company ID {$modelCompanyId}",
                    'EmployeeMaster',
                    $employeeMaster->id,
                    null,
                    null,
                    $modelCompanyId
                );
            }
            return true;
        }

        if (!in_array((int) $user->role_id, [Role::COMPANY_ADMIN, Role::HR, Role::EMPLOYEE], true)) {
            return false;
        }

        // Employee self-service check
        if ((int) $user->role_id === Role::EMPLOYEE) {
            return (int) $user->emp_id === (int) $employeeMaster->id && $modelCompanyId === $userCompanyId;
        }

        return $modelCompanyId > 0 && $userCompanyId > 0 && $modelCompanyId === $userCompanyId;
    }
}
