<?php

namespace App\Observers;

use App\Models\LoanRequest;
use App\Models\EmpLoan;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LoanRequestObserver
{
    /**
     * Handle the LoanRequest "created" event.
     */
    public function created(LoanRequest $loanRequest): void
    {
        $companyId = $loanRequest->employee?->company_id ?? getActiveCompanyId();
        ActivityLog::log(
            action: 'create',
            module: 'loan_requests',
            description: "Submitted loan request #{$loanRequest->id} for employee {$loanRequest->employee?->full_name} (Amount: ₱" . number_format((float)$loanRequest->requested_amount, 2) . ")",
            recordType: LoanRequest::class,
            recordId: $loanRequest->id,
            companyId: $companyId
        );
    }

    /**
     * Handle the LoanRequest "updated" event.
     */
    public function updated(LoanRequest $loanRequest): void
    {
        if ($loanRequest->wasChanged('status')) {
            $companyId = $loanRequest->employee?->company_id ?? getActiveCompanyId();

            if ($loanRequest->status === 'approved') {
                EmpLoan::create([
                    'employee_id'      => $loanRequest->employee_id,
                    'loan_type_id'     => $loanRequest->loan_type_id,
                    'principal_amount' => $loanRequest->requested_amount,
                    'current_balance'  => $loanRequest->requested_amount,
                ]);

                Log::info("LoanRequestObserver: Automatically created EmpLoan record for approved LoanRequest ID {$loanRequest->id}.");
            }

            ActivityLog::log(
                action: $loanRequest->status,
                module: 'loan_requests',
                description: "Loan request #{$loanRequest->id} status updated to '{$loanRequest->status}' for employee {$loanRequest->employee?->full_name}",
                recordType: LoanRequest::class,
                recordId: $loanRequest->id,
                companyId: $companyId
            );
        }
    }
}
