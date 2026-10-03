<?php

function can($module, $action = null, $userId = null)
{
    $user = auth()->user();
    if (!$user) {
        return false;
    }

    if ($userId && (int) $userId !== (int) $user->id) {
        $targetUser = \App\Models\User::find($userId);
        return $targetUser ? \App\Models\MenuRoleAccess::hasModuleAccess($targetUser, (string) $module) : false;
    }

    return \App\Models\MenuRoleAccess::hasModuleAccess($user, (string) $module);
}

/**
 * Get permission modules for a user based on role and owner context.
 */
function getPermissionModules(?string $userRole = null, ?string $ownerRole = null): array
{
    if ($userRole === 'company') {
        return config('permissions.company_owner', []);
    }

    if ($userRole === 'admin' || $ownerRole === 'admin') {
        return config('permissions.admin_staff', []);
    }

    return config('permissions.company_staff', []);
}

function permissionActions(): array
{
    return config('permissions.actions', ['view', 'edit']);
}

function permissionViewOnlyModules(): array
{
    return config('permissions.view_only', ['dashboard']);
}
