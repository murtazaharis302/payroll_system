<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LoanPayment;
use App\Models\Role;
use App\Models\User;
use App\Models\ActivityLog;

class LoanPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function view(User $user, LoanPayment $payment): bool
    {
        return $this->checkOwnership($user, $payment, 'view_loan_payment');
    }

    public function create(User $user): bool
    {
        return in_array((int) $user->role_id, [Role::SUPER_ADMIN, Role::COMPANY_ADMIN, Role::HR], true);
    }

    public function update(User $user, LoanPayment $payment): bool
    {
        return $this->checkOwnership($user, $payment, 'update_loan_payment');
    }

    public function delete(User $user, LoanPayment $payment): bool
    {
        return $this->checkOwnership($user, $payment, 'delete_loan_payment');
    }

    protected function checkOwnership(User $user, LoanPayment $payment, string $action): bool
    {
        $loan = $payment->empLoan()->withoutGlobalScopes()->first();
        $employee = $loan?->employee()->withoutGlobalScopes()->first();
        $modelCompanyId = (int) ($employee->company_id ?? 0);
        $userCompanyId = (int) (session('company_id') ?? $user->company_id ?? 0);

        if ((int) $user->role_id === Role::SUPER_ADMIN) {
            if ($modelCompanyId > 0 && $userCompanyId > 0 && $modelCompanyId !== $userCompanyId) {
                ActivityLog::log(
                    'super_admin_cross_tenant_access',
                    'LoanPaymentPolicy',
                    "SuperAdmin accessed LoanPayment ID {$payment->id} belonging to Company ID {$modelCompanyId}",
                    'LoanPayment',
                    $payment->id,
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
