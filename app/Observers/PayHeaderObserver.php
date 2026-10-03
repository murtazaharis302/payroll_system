<?php

namespace App\Observers;

use App\Models\PayHeader;
use App\Models\ActivityLog;

class PayHeaderObserver
{
    /**
     * Handle the PayHeader "created" event.
     */
    public function created(PayHeader $payHeader): void
    {
        $companyId = $payHeader->employee?->company_id ?? getActiveCompanyId();
        ActivityLog::log(
            action: 'create',
            module: 'payroll',
            description: "PayHeader #{$payHeader->id} created for employee {$payHeader->employee?->full_name} (Net Pay: ₱" . number_format((float)$payHeader->net_pay, 2) . ")",
            recordType: PayHeader::class,
            recordId: $payHeader->id,
            companyId: $companyId
        );
    }

    /**
     * Handle the PayHeader "updated" event.
     */
    public function updated(PayHeader $payHeader): void
    {
        if ($payHeader->wasChanged('posted_at') || $payHeader->wasChanged('status')) {
            $companyId = $payHeader->employee?->company_id ?? getActiveCompanyId();
            $action = $payHeader->posted_at !== null ? 'post' : 'update';
            ActivityLog::log(
                action: $action,
                module: 'payroll',
                description: "PayHeader #{$payHeader->id} posted/updated for employee {$payHeader->employee?->full_name} (Status: {$payHeader->status})",
                recordType: PayHeader::class,
                recordId: $payHeader->id,
                companyId: $companyId
            );

            if (in_array($payHeader->status, ['approved', 'posted', 'finalized'])) {
                app(\App\Services\PayrollLoanSyncService::class)->sync($payHeader);
            }
        }
    }
}
