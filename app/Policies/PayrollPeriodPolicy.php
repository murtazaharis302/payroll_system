<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Models\ActivityLog;

class PayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return $this->checkOwnership($user, $period, 'view_payroll_period');
    }

    public function create(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function update(User $user, PayrollPeriod $period): bool
    {
        return $this->checkOwnership($user, $period, 'update_payroll_period');
    }

    public function delete(User $user, PayrollPeriod $period): bool
    {
        return $this->checkOwnership($user, $period, 'delete_payroll_period');
    }

    protected function checkOwnership(User $user, PayrollPeriod $period, string $action): bool
    {
        $modelCompanyId = (int) ($period->company_id ?? 0);
        $userCompanyId = (int) (session('company_id') ?? $user->company_id ?? 0);

        if ((int) $user->role_id === Role::SUPER_ADMIN) {
            if ($modelCompanyId > 0 && $userCompanyId > 0 && $modelCompanyId !== $userCompanyId) {
                ActivityLog::log(
                    'super_admin_cross_tenant_access',
                    'PayrollPeriodPolicy',
                    "SuperAdmin accessed PayrollPeriod ID {$period->id} belonging to Company ID {$modelCompanyId}",
                    'PayrollPeriod',
                    $period->id,
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
