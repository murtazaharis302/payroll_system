<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PayHeader;
use App\Models\Role;
use App\Models\User;
use App\Models\ActivityLog;

class PayHeaderPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function view(User $user, PayHeader $payHeader): bool
    {
        return $this->checkOwnership($user, $payHeader, 'view_pay_header');
    }

    public function create(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function update(User $user, PayHeader $payHeader): bool
    {
        return $this->checkOwnership($user, $payHeader, 'update_pay_header');
    }

    public function delete(User $user, PayHeader $payHeader): bool
    {
        return $this->checkOwnership($user, $payHeader, 'delete_pay_header');
    }

    public function approve(User $user, PayHeader $payHeader): bool
    {
        return $this->checkOwnership($user, $payHeader, 'approve_pay_header');
    }

    protected function checkOwnership(User $user, PayHeader $payHeader, string $action): bool
    {
        $employee = $payHeader->employee()->withoutGlobalScopes()->first();
        $period = $payHeader->payrollPeriod()->withoutGlobalScopes()->first();

        $modelCompanyId = (int) ($period->company_id ?? $employee->company_id ?? 0);
        $userCompanyId = (int) (session('company_id') ?? $user->company_id ?? 0);

        if ((int) $user->role_id === Role::SUPER_ADMIN) {
            if ($modelCompanyId > 0 && $userCompanyId > 0 && $modelCompanyId !== $userCompanyId) {
                ActivityLog::log(
                    'super_admin_cross_tenant_access',
                    'PayHeaderPolicy',
                    "SuperAdmin accessed PayHeader ID {$payHeader->id} belonging to Company ID {$modelCompanyId}",
                    'PayHeader',
                    $payHeader->id,
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
