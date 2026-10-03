<?php

declare(strict_types=1);

namespace App\Support\Payroll;

use App\Models\DeductionType;
use App\Models\PayHeader;
use App\Models\PayOut;

class DeductionReportResolver
{
    /**
     * Canonical alias mapping for deduction codes to standard report keys.
     */
    public const CODE_MAP = [
        'SSS'          => 'sss_contribution',
        'PHILHEALTH'   => 'philhealth_contribution',
        'PH'           => 'philhealth_contribution',
        'PAGIBIG'      => 'pagibig_contribution',
        'HDMF'         => 'pagibig_contribution',
        'PAG-IBIG'     => 'pagibig_contribution',
        'TAX'          => 'withholding_tax',
        'WTAX'         => 'withholding_tax',
        'SSS_LOAN'     => 'sss_loan',
        'PAGIBIG_LOAN' => 'pagibig_loan',
        'HDMF_LOAN'    => 'pagibig_loan',
        'PAG-IBIG_LOAN'=> 'pagibig_loan',
        'VALE'         => 'vale_deduction',
        'CASH_ADVANCE' => 'vale_deduction',
        'GOODS'        => 'goods_deduction',
        'TARDINESS'    => 'late_deduction',
        'LATE'         => 'late_deduction',
        'UNDERTIME'    => 'undertime_deduction',
        'ABSENCES'     => 'absences_deduction',
        'ABSENCE'      => 'absences_deduction',
        'OTHER'        => 'other_deductions',
    ];

    /**
     * Standard human-readable labels for deduction report keys.
     */
    public const STANDARD_LABELS = [
        'sss_contribution'        => 'SSS Contribution',
        'philhealth_contribution' => 'PhilHealth Contribution',
        'pagibig_contribution'    => 'Pag-IBIG Contribution',
        'withholding_tax'         => 'Withholding Tax',
        'sss_loan'                => 'SSS Loan',
        'pagibig_loan'            => 'Pag-IBIG Loan',
        'vale_deduction'          => 'Vale (Cash Advance)',
        'goods_deduction'         => 'Goods Purchase',
        'late_deduction'          => 'Late Ded.',
        'undertime_deduction'     => 'Undertime Ded.',
        'absences_deduction'      => 'Absences Ded.',
        'other_deductions'        => 'Other Ded.',
        'total_deductions'        => 'Total Ded.',
    ];

    /**
     * Determine if a PayOut row matches the given target deduction key or ID.
     *
     * @param string|int $targetKeyOrId
     * @param PayOut $payOut
     * @return bool
     */
    public static function matchesPayOut(string|int $targetKeyOrId, PayOut $payOut): bool
    {
        $poTypeId = (string) $payOut->deduction_type_id;
        $targetStr = (string) $targetKeyOrId;

        // Direct ID match
        if ($poTypeId === $targetStr || (is_numeric($targetKeyOrId) && (int)$poTypeId === (int)$targetKeyOrId)) {
            return true;
        }

        $sysKey = $payOut->deductionType->system_key ?? null;
        if ($sysKey !== null) {
            $sysMap = [
                'sss'        => 'sss_contribution',
                'philhealth' => 'philhealth_contribution',
                'pagibig'    => 'pagibig_contribution',
                'tax'        => 'withholding_tax',
                'tardiness'  => 'late_deduction',
                'undertime'  => 'undertime_deduction',
            ];
            if (isset($sysMap[$sysKey]) && $sysMap[$sysKey] === $targetStr) {
                return true;
            }
        }

        $code = strtoupper(trim($payOut->deductionType->code ?? ''));
        if (isset(self::CODE_MAP[$code]) && self::CODE_MAP[$code] === $targetStr) {
            return true;
        }

        $name = strtolower(trim($payOut->deductionType->name ?? ''));
        if ($targetStr === 'sss_contribution' && ($name === 'sss' || $name === 'sss contribution')) {
            return true;
        }
        if ($targetStr === 'philhealth_contribution' && ($name === 'philhealth' || $name === 'philhealth contribution')) {
            return true;
        }
        if ($targetStr === 'pagibig_contribution' && in_array($name, ['pagibig', 'pag-ibig', 'pagibig contribution', 'hdmf'], true)) {
            return true;
        }
        if ($targetStr === 'withholding_tax' && in_array($name, ['tax', 'wtax', 'w/tax', 'withholding tax'], true)) {
            return true;
        }
        if ($targetStr === 'sss_loan' && ($name === 'sss loan' || $name === 'sss loan deduction')) {
            return true;
        }
        if ($targetStr === 'pagibig_loan' && in_array($name, ['pagibig loan', 'pag-ibig loan', 'pagibig loan deduction', 'hdmf loan'], true)) {
            return true;
        }
        if ($targetStr === 'vale_deduction' && in_array($name, ['vale', 'vale deduction', 'cash advance'], true)) {
            return true;
        }
        if ($targetStr === 'goods_deduction' && in_array($name, ['goods', 'goods deduction', 'goods purchase'], true)) {
            return true;
        }
        if ($targetStr === 'absences_deduction' && in_array($name, ['absences', 'absence', 'absences deduction', 'absence deduction', 'days absent'], true)) {
            return true;
        }
        if ($targetStr === 'late_deduction' && in_array($name, ['late', 'tardiness', 'late deduction', 'tardiness deduction'], true)) {
            return true;
        }
        if ($targetStr === 'undertime_deduction' && in_array($name, ['undertime', 'undertime deduction'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the canonical deduction key or ID for a given PayOut.
     */
    public static function resolveCanonicalKey(PayOut $payOut, array $allowedKeys = []): ?string
    {
        $typeId = (string) $payOut->deduction_type_id;
        if (!empty($allowedKeys) && (in_array($typeId, $allowedKeys, true) || in_array((int)$typeId, $allowedKeys, true))) {
            return $typeId;
        }

        $sysKey = $payOut->deductionType->system_key ?? null;
        if ($sysKey !== null) {
            $sysMap = [
                'sss'        => 'sss_contribution',
                'philhealth' => 'philhealth_contribution',
                'pagibig'    => 'pagibig_contribution',
                'tax'        => 'withholding_tax',
                'tardiness'  => 'late_deduction',
                'undertime'  => 'undertime_deduction',
            ];
            if (isset($sysMap[$sysKey])) {
                $key = $sysMap[$sysKey];
                if (empty($allowedKeys) || in_array($key, $allowedKeys, true)) {
                    return $key;
                }
            }
        }

        $code = strtoupper(trim($payOut->deductionType->code ?? ''));
        if (isset(self::CODE_MAP[$code])) {
            $key = self::CODE_MAP[$code];
            if (empty($allowedKeys) || in_array($key, $allowedKeys, true)) {
                return $key;
            }
        }

        $name = strtolower(trim($payOut->deductionType->name ?? ''));
        $nameMap = [
            'sss'                     => 'sss_contribution',
            'sss contribution'        => 'sss_contribution',
            'philhealth'              => 'philhealth_contribution',
            'philhealth contribution' => 'philhealth_contribution',
            'pagibig'                 => 'pagibig_contribution',
            'pag-ibig'                => 'pagibig_contribution',
            'pagibig contribution'    => 'pagibig_contribution',
            'hdmf'                    => 'pagibig_contribution',
            'tax'                     => 'withholding_tax',
            'wtax'                    => 'withholding_tax',
            'w/tax'                   => 'withholding_tax',
            'withholding tax'         => 'withholding_tax',
            'sss loan'                => 'sss_loan',
            'sss loan deduction'      => 'sss_loan',
            'pagibig loan'            => 'pagibig_loan',
            'pag-ibig loan'           => 'pagibig_loan',
            'pagibig loan deduction'  => 'pagibig_loan',
            'hdmf loan'               => 'pagibig_loan',
            'vale'                    => 'vale_deduction',
            'vale deduction'          => 'vale_deduction',
            'cash advance'            => 'vale_deduction',
            'goods'                   => 'goods_deduction',
            'goods deduction'         => 'goods_deduction',
            'goods purchase'          => 'goods_deduction',
            'late'                    => 'late_deduction',
            'late deduction'          => 'late_deduction',
            'tardiness'               => 'late_deduction',
            'undertime'               => 'undertime_deduction',
            'undertime deduction'     => 'undertime_deduction',
            'absences'                => 'absences_deduction',
            'absence'                 => 'absences_deduction',
            'absences deduction'      => 'absences_deduction',
            'absence deduction'       => 'absences_deduction',
            'days absent'             => 'absences_deduction',
        ];

        if (isset($nameMap[$name])) {
            $key = $nameMap[$name];
            if (empty($allowedKeys) || in_array($key, $allowedKeys, true)) {
                return $key;
            }
        }

        return (!empty($allowedKeys) && in_array($typeId, $allowedKeys, true)) ? $typeId : (empty($allowedKeys) ? $typeId : null);
    }

    /**
     * Resolve the exact deduction amount for a PayHeader for a given deduction type.
     * Uses PayOut.amount as primary source and only falls back to legacy accessors when PayHeader has no PayOuts.
     */
    public static function resolveAmountForHeader(PayHeader $ph, string|int $deductionTypeId): float
    {
        $hasPayOutsLoaded = $ph->relationLoaded('payOuts');
        $payOutsCount = $hasPayOutsLoaded ? $ph->payOuts->count() : $ph->payOuts()->count();

        if ($payOutsCount > 0) {
            $payOuts = $hasPayOutsLoaded ? $ph->payOuts : $ph->payOuts()->with('deductionType')->get();
            $amt = 0.0;
            foreach ($payOuts as $po) {
                if (self::matchesPayOut($deductionTypeId, $po)) {
                    $amt += (float) ($po->amount ?? ((float)$po->rate * (float)($po->number_input ?? 1.00)));
                }
            }
            return $amt;
        }

        // Fallback to legacy PayHeader accessors only when NO PayOut records exist at all
        $phMap = [
            'sss_contribution'        => (float) ($ph->sss_contribution ?? 0),
            'philhealth_contribution' => (float) ($ph->philhealth_contribution ?? 0),
            'pagibig_contribution'    => (float) ($ph->pagibig_contribution ?? 0),
            'withholding_tax'         => (float) ($ph->withholding_tax ?? 0),
            'sss_loan'                => (float) ($ph->sss_loan_deduction ?? $ph->sss_loan ?? 0),
            'pagibig_loan'            => (float) ($ph->pagibig_loan_deduction ?? $ph->pagibig_loan ?? 0),
            'vale_deduction'          => (float) ($ph->vale_loan_deduction ?? $ph->vale_deduction ?? 0),
            'goods_deduction'         => (float) ($ph->goods_purchase_deduction ?? $ph->goods_deduction ?? 0),
            'late_deduction'          => (float) ($ph->late_deduction ?? 0),
            'undertime_deduction'     => (float) ($ph->undertime_deduction ?? 0),
            'absences_deduction'      => (float) ($ph->absences_deduction ?? 0),
            'other_deductions'        => (float) ($ph->other_deductions ?? 0),
        ];

        return isset($phMap[$deductionTypeId]) ? $phMap[$deductionTypeId] : 0.0;
    }

    /**
     * Resolve an associative map of [deduction_key => amount] for selected deduction keys.
     */
    public static function resolveRowDeductions(PayHeader $ph, array $selectedDeductions): array
    {
        $rowDeductions = array_fill_keys($selectedDeductions, 0.0);
        $hasPayOutsLoaded = $ph->relationLoaded('payOuts');
        $payOutsCount = $hasPayOutsLoaded ? $ph->payOuts->count() : $ph->payOuts()->count();

        if ($payOutsCount > 0) {
            $payOuts = $hasPayOutsLoaded ? $ph->payOuts : $ph->payOuts()->with('deductionType')->get();
            foreach ($payOuts as $po) {
                $key = self::resolveCanonicalKey($po, $selectedDeductions);
                if ($key !== null && array_key_exists($key, $rowDeductions)) {
                    $rowDeductions[$key] += (float) ($po->amount ?? ((float)$po->rate * (float)($po->number_input ?? 1.00)));
                }
            }
            return $rowDeductions;
        }

        // Fallback only when zero PayOut rows exist
        $phMap = [
            'sss_contribution'        => (float) ($ph->sss_contribution ?? 0),
            'philhealth_contribution' => (float) ($ph->philhealth_contribution ?? 0),
            'pagibig_contribution'    => (float) ($ph->pagibig_contribution ?? 0),
            'withholding_tax'         => (float) ($ph->withholding_tax ?? 0),
            'sss_loan'                => (float) ($ph->sss_loan_deduction ?? $ph->sss_loan ?? 0),
            'pagibig_loan'            => (float) ($ph->pagibig_loan_deduction ?? $ph->pagibig_loan ?? 0),
            'vale_deduction'          => (float) ($ph->vale_loan_deduction ?? $ph->vale_deduction ?? 0),
            'goods_deduction'         => (float) ($ph->goods_purchase_deduction ?? $ph->goods_deduction ?? 0),
            'late_deduction'          => (float) ($ph->late_deduction ?? 0),
            'undertime_deduction'     => (float) ($ph->undertime_deduction ?? 0),
            'absences_deduction'      => (float) ($ph->absences_deduction ?? 0),
            'other_deductions'        => (float) ($ph->other_deductions ?? 0),
        ];

        foreach ($selectedDeductions as $key) {
            if (isset($phMap[$key])) {
                $rowDeductions[$key] = $phMap[$key];
            }
        }

        return $rowDeductions;
    }
}
