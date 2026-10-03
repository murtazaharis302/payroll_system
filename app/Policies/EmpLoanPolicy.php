<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmpLoan;
use App\Models\Role;
use App\Models\User;
use App\Models\ActivityLog;

class EmpLoanPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function view(User $user, EmpLoan $loan): bool
    {
        return $this->checkOwnership($user, $loan, 'view_emp_loan');
    }

    public function create(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function update(User $user, EmpLoan $loan): bool
    {
        return $this->checkOwnership($user, $loan, 'update_emp_loan');
    }

    public function delete(User $user, EmpLoan $loan): bool
    {
        return $this->checkOwnership($user, $loan, 'delete_emp_loan');
    }

    protected function checkOwnership(User $user, EmpLoan $loan, string $action): bool
    {
        $employee = $loan->employee()->withoutGlobalScopes()->first();
        $modelCompanyId = (int) ($employee->company_id ?? 0);
        $userCompanyId = (int) (session('company_id') ?? $user->company_id ?? 0);

        if ((int) $user->role_id === Role::SUPER_ADMIN) {
            if ($modelCompanyId > 0 && $userCompanyId > 0 && $modelCompanyId !== $userCompanyId) {
                ActivityLog::log(
                    'super_admin_cross_tenant_access',
                    'EmpLoanPolicy',
                    "SuperAdmin accessed EmpLoan ID {$loan->id} belonging to Company ID {$modelCompanyId}",
                    'EmpLoan',
                    $loan->id,
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
