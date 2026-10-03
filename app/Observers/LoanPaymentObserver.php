<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\LoanPayment;
use Illuminate\Support\Facades\DB;

class LoanPaymentObserver
{
    /**
     * Handle the LoanPayment "created" event.
     */
    public function created(LoanPayment $loanPayment): void
    {
        DB::transaction(function () use ($loanPayment) {
            $empLoan = $loanPayment->empLoan()->lockForUpdate()->first();
            if ($empLoan) {
                $empLoan->decrement('current_balance', $loanPayment->amount_paid);
            }
        });
    }
}
