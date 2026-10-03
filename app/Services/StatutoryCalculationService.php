<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\StatutoryRuleNotFoundException;
use App\Models\StatutoryRule;

class StatutoryCalculationService
{
    /**
     * Resolve effective statutory rule for a target statutory type and pay date.
     *
     * @param string $type
     * @param string $payDate
     * @return StatutoryRule
     * @throws StatutoryRuleNotFoundException
     */
    public function resolveRule(string $type, string $payDate): StatutoryRule
    {
        $rule = StatutoryRule::forTypeAndDate($type, $payDate)->first();

        if (!$rule) {
            throw new StatutoryRuleNotFoundException($type, $payDate);
        }

        return $rule;
    }

    /**
     * Calculate SSS contributions (EE, ER, EC shares and MSC) using effective rule version.
     *
     * @param float $monthlySalaryBasis
     * @param StatutoryRule $rule
     * @return array{monthly_ee: float, monthly_er: float, monthly_ec: float, msc: float, version_code: string}
     */
    public function calculateSSS(float $monthlySalaryBasis, StatutoryRule $rule): array
    {
        if ($monthlySalaryBasis <= 0) {
            return [
                'monthly_ee' => 0.00,
                'monthly_er' => 0.00,
                'monthly_ec' => 0.00,
                'msc' => 0.00,
                'version_code' => $rule->version_code,
            ];
        }

        $brackets = $rule->brackets ?? [];
        $matchedBracket = null;

        foreach ($brackets as $bracket) {
            $rangeFrom = (float) $bracket['range_from'];
            $rangeTo = isset($bracket['range_to']) && $bracket['range_to'] !== null ? (float) $bracket['range_to'] : null;

            if ($monthlySalaryBasis >= $rangeFrom && ($rangeTo === null || $monthlySalaryBasis <= $rangeTo)) {
                $matchedBracket = $bracket;
                break;
            }
        }

        if (!$matchedBracket && !empty($brackets)) {
            // Fallback to highest bracket if salary exceeds top threshold
            $matchedBracket = end($brackets);
        }

        if ($matchedBracket) {
            $totalEe = (float) ($matchedBracket['total_ee'] ?? ($matchedBracket['regular_ee'] + ($matchedBracket['wisp_ee'] ?? 0)));
            $regularEr = (float) ($matchedBracket['regular_er'] ?? 0);
            $wispEr = (float) ($matchedBracket['wisp_er'] ?? 0);
            $totalEr = $regularEr + $wispEr;
            $ec = (float) ($matchedBracket['ec'] ?? 0);
            $msc = (float) ($matchedBracket['msc'] ?? 0);

            return [
                'monthly_ee' => round($totalEe, $rule->rounding_decimals),
                'monthly_er' => round($totalEr, $rule->rounding_decimals),
                'monthly_ec' => round($ec, $rule->rounding_decimals),
                'msc' => $msc,
                'version_code' => $rule->version_code,
            ];
        }

        return [
            'monthly_ee' => 0.00,
            'monthly_er' => 0.00,
            'monthly_ec' => 0.00,
            'msc' => 0.00,
            'version_code' => $rule->version_code,
        ];
    }

    /**
     * Calculate PhilHealth contributions (EE and ER shares) using effective rule version.
     *
     * @param float $monthlySalaryBasis
     * @param StatutoryRule $rule
     * @return array{monthly_ee: float, monthly_er: float, version_code: string}
     */
    public function calculatePhilHealth(float $monthlySalaryBasis, StatutoryRule $rule): array
    {
        if ($monthlySalaryBasis <= 0) {
            return [
                'monthly_ee' => 0.00,
                'monthly_er' => 0.00,
                'version_code' => $rule->version_code,
            ];
        }

        $floor = (float) ($rule->salary_floor ?? 10000.00);
        $ceiling = (float) ($rule->salary_ceiling ?? 100000.00);
        $eeRate = (float) ($rule->ee_share_rate ?? 0.0250);
        $erRate = (float) ($rule->er_share_rate ?? 0.0250);

        $effectiveSalary = max($floor, min($monthlySalaryBasis, $ceiling));

        $monthlyEe = round($effectiveSalary * $eeRate, $rule->rounding_decimals);
        $monthlyEr = round($effectiveSalary * $erRate, $rule->rounding_decimals);

        return [
            'monthly_ee' => $monthlyEe,
            'monthly_er' => $monthlyEr,
            'version_code' => $rule->version_code,
        ];
    }

    /**
     * Calculate Pag-IBIG contributions (EE and ER shares) using effective rule version.
     *
     * @param float $monthlySalaryBasis
     * @param StatutoryRule $rule
     * @return array{monthly_ee: float, monthly_er: float, version_code: string}
     */
    public function calculatePagIbig(float $monthlySalaryBasis, StatutoryRule $rule): array
    {
        if ($monthlySalaryBasis <= 0) {
            return [
                'monthly_ee' => 0.00,
                'monthly_er' => 0.00,
                'version_code' => $rule->version_code,
            ];
        }

        $cap = $rule->salary_ceiling !== null ? (float) $rule->salary_ceiling : null;
        $brackets = $rule->brackets ?? [];

        $matchedBracket = null;
        foreach ($brackets as $bracket) {
            $min = (float) ($bracket['min'] ?? 0.00);
            $max = isset($bracket['max']) && $bracket['max'] !== null ? (float) $bracket['max'] : null;

            if ($monthlySalaryBasis >= $min && ($max === null || $monthlySalaryBasis <= $max)) {
                $matchedBracket = $bracket;
                break;
            }
        }

        if (!$matchedBracket && !empty($brackets)) {
            $matchedBracket = end($brackets);
        }

        if ($matchedBracket) {
            $eeRate = (float) ($matchedBracket['ee_rate'] ?? $rule->ee_share_rate ?? 0.00);
            $erRate = (float) ($matchedBracket['er_rate'] ?? $rule->er_share_rate ?? 0.00);
            $shouldCap = (bool) ($matchedBracket['cap_salary'] ?? true);

            $basis = ($shouldCap && $cap !== null) ? min($monthlySalaryBasis, $cap) : $monthlySalaryBasis;
        } else {
            $eeRate = (float) ($rule->ee_share_rate ?? 0.00);
            $erRate = (float) ($rule->er_share_rate ?? 0.00);
            $basis = ($cap !== null) ? min($monthlySalaryBasis, $cap) : $monthlySalaryBasis;
        }

        $monthlyEe = round($basis * $eeRate, $rule->rounding_decimals);
        $monthlyEr = round($basis * $erRate, $rule->rounding_decimals);

        return [
            'monthly_ee' => $monthlyEe,
            'monthly_er' => $monthlyEr,
            'version_code' => $rule->version_code,
        ];
    }

    /**
     * Calculate Withholding Tax (TRAIN Law) using effective rule version.
     *
     * @param float $taxableIncome Period taxable income
     * @param int $annualPeriods 24 for semi-monthly, 12 for monthly
     * @param StatutoryRule $rule
     * @return float
     */
    public function calculateWithholdingTax(float $taxableIncome, int $annualPeriods, StatutoryRule $rule): float
    {
        if ($taxableIncome <= 0 || $annualPeriods <= 0) {
            return 0.00;
        }

        $annualizedIncome = $taxableIncome * $annualPeriods;
        $brackets = $rule->brackets ?? [];

        $matchedTier = null;
        foreach ($brackets as $tier) {
            $min = (float) $tier['min'];
            $max = isset($tier['max']) && $tier['max'] !== null ? (float) $tier['max'] : null;

            if ($annualizedIncome >= $min && ($max === null || $annualizedIncome <= $max)) {
                $matchedTier = $tier;
                break;
            }
        }

        if (!$matchedTier && !empty($brackets)) {
            $matchedTier = end($brackets);
        }

        if ($matchedTier) {
            $baseTax = (float) ($matchedTier['base_tax'] ?? 0.00);
            $percentage = (float) ($matchedTier['percentage'] ?? 0.00);
            $excessOver = (float) ($matchedTier['excess_over'] ?? 0.00);

            $excess = max(0.00, $annualizedIncome - $excessOver);
            $annualTax = $baseTax + ($excess * $percentage);

            return round($annualTax / $annualPeriods, $rule->rounding_decimals);
        }

        return 0.00;
    }
}
