<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\ActivityLog;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->checkOwnership($user, $employee, 'view_employee');
    }

    public function create(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->checkOwnership($user, $employee, 'update_employee');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->checkOwnership($user, $employee, 'delete_employee');
    }

    protected function checkOwnership(User $user, Employee $employee, string $action): bool
    {
        $owner = $employee->owner()->withoutGlobalScopes()->first();
        $employeeUser = $employee->employee()->withoutGlobalScopes()->first();

        $modelCompanyId = (int) (
            $owner?->company_id
            ?? $employeeUser?->company_id
            ?? 0
        );

        $userCompanyId = (int) (session('company_id') ?? $user->company_id ?? 0);

        if ((int) $user->role_id === Role::SUPER_ADMIN) {
            if ($modelCompanyId > 0 && $userCompanyId > 0 && $modelCompanyId !== $userCompanyId) {
                ActivityLog::log(
                    'super_admin_cross_tenant_access',
                    'EmployeePolicy',
                    "SuperAdmin accessed Employee ID {$employee->id} belonging to Company ID {$modelCompanyId}",
                    'Employee',
                    $employee->id,
                    null,
                    null,
                    $modelCompanyId
                );
            }
            return true;
        }

        if (!in_array((int) $user->role_id, [Role::COMPANY_ADMIN, Role::HR], true)) {
            return false;
        }

        return $modelCompanyId > 0 && $userCompanyId > 0 && $modelCompanyId === $userCompanyId;
    }
}
