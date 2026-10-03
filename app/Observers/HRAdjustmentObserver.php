<?php

namespace App\Observers;

use App\Models\HRAdjustment;
use App\Models\ActivityLog;

class HRAdjustmentObserver
{
    /**
     * Handle the HRAdjustment "created" event.
     */
    public function created(HRAdjustment $hrAdjustment): void
    {
        $companyId = $hrAdjustment->company_id ?? $hrAdjustment->employee?->company_id ?? getActiveCompanyId();
        ActivityLog::log(
            action: 'create',
            module: 'hr_adjustments',
            description: "Created HR Adjustment for employee {$hrAdjustment->employee?->full_name} (Type: {$hrAdjustment->adjustment_type}, Amount: ₱" . number_format((float)($hrAdjustment->amount ?? 0), 2) . ")",
            recordType: HRAdjustment::class,
            recordId: $hrAdjustment->id,
            companyId: $companyId
        );
    }

    /**
     * Handle the HRAdjustment "updated" event.
     */
    public function updated(HRAdjustment $hrAdjustment): void
    {
        if ($hrAdjustment->wasChanged('status')) {
            $companyId = $hrAdjustment->company_id ?? $hrAdjustment->employee?->company_id ?? getActiveCompanyId();
            ActivityLog::log(
                action: $hrAdjustment->status,
                module: 'hr_adjustments',
                description: "HR Adjustment #{$hrAdjustment->id} status updated to '{$hrAdjustment->status}' for employee {$hrAdjustment->employee?->full_name}",
                recordType: HRAdjustment::class,
                recordId: $hrAdjustment->id,
                companyId: $companyId
            );
        }
    }
}
