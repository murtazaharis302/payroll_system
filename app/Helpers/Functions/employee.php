<?php


use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\Notification;
use App\Models\User;

/**
 * Synchronization helper for users and payroll employees.
 * Automatic creation/linking on GET/read operations is removed to keep Users and Payroll Employees separate.
 */
function syncHREmployeeUsersToMaster(?int $companyId = null): void
{
    // Automatic User-to-EmployeeMaster synchronization is disabled.
    // User accounts and Payroll Employee records must be created and linked only through explicit action.
}

function getEmployeeCount()
{
    $user = auth()->user();
    if (! $user) {
        return 0;
    }

    $companyId = getActiveCompanyId() ?? $user->company_id;

    $query = EmployeeMaster::query();

    if ($user->role !== 'admin' && $user->role_id !== \App\Models\Role::SUPER_ADMIN) {
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
    }

    return $query->count();
}

function getCompaniesCount($status)
{
    return company::where('status', $status)->count();
}

/**
 * Save notification to database
 */
function saveNotification($notificationType, $fromUserId, $toUserId, $userId, $message)
{
    try {
        Notification::create([
            'notification_type' => $notificationType,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'user_id' => $userId,
            'message' => $message,
            'is_read' => false,
        ]);

        return true;
    } catch (\Exception $e) {
        \Log::error('Failed to save notification: '.$e->getMessage());

        return false;
    }
}

/**
 * Log activity
 */
function logActivity($action, $module, $description = null, $recordType = null, $recordId = null, $oldValue = null, $newValue = null, $companyId = null)
{
    return ActivityLog::log($action, $module, $description, $recordType, $recordId, $oldValue, $newValue, $companyId);
}
