<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\ActivityLog;

class SettingsPolicy
{
    public function view(User $user, ?int $targetCompanyId = null): bool
    {
        $userCompanyId = (int) (session('company_id') ?? $user->company_id ?? 0);
        $targetCompanyId = (int) ($targetCompanyId ?? $userCompanyId);

        if ((int) $user->role_id === Role::SUPER_ADMIN) {
            if ($targetCompanyId > 0 && $userCompanyId > 0 && $targetCompanyId !== $userCompanyId) {
                ActivityLog::log(
                    'super_admin_cross_tenant_access',
                    'SettingsPolicy',
                    "SuperAdmin accessed settings for Company ID {$targetCompanyId}",
                    'Company',
                    $targetCompanyId,
                    null,
                    null,
                    $targetCompanyId
                );
            }
            return true;
        }

        if (!in_array((int) $user->role_id, [Role::COMPANY_ADMIN], true)) {
            return false;
        }

        return $targetCompanyId > 0 && $userCompanyId > 0 && $targetCompanyId === $userCompanyId;
    }

    public function update(User $user, ?int $targetCompanyId = null): bool
    {
        return $this->view($user, $targetCompanyId);
    }
}
