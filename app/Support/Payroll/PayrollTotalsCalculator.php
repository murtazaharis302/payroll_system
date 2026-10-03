<?php

declare(strict_types=1);

namespace App\Support\Payroll;

use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use Illuminate\Support\Facades\DB;

final class PayrollTotalsCalculator
{
    /**
     * Calculate all earnings, deductions, and net totals for a given PayHeader directly from PayIn and PayOut lines.
     *
     * @param PayHeader $payHeader
     * @return array
     */
    public function calculate(PayHeader $payHeader): array
    {
        $grossPay = (float) PayIn::withoutGlobalScopes()
            ->where('pay_id', $payHeader->id)
            ->sum(DB::raw('rate * number_input'));

        $totalDeductions = (float) PayOut::withoutGlobalScopes()
            ->where('pay_id', $payHeader->id)
            ->sum(DB::raw('rate * number_input'));

        $netPay = $grossPay - $totalDeductions;

        return [
            'total_gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ];
    }
}
