<?php

namespace App\Services;

use App\Models\EmployeeMaster;
use App\Models\PayrollPolicy;

class PayrollLineCalculator
{
    protected PayrollPolicy $policy;

    public function __construct(?PayrollPolicy $policy = null)
    {
        $this->policy = $policy ?? PayrollPolicy::makeDefaultPolicy();
    }

    /**
     * Calculate basic pay line item enforcing explicit quantity x unit_rate contract.
     * ZERO hidden fallbacks (no 11 days or 88 hours substitution).
     *
     * @param EmployeeMaster|null $employee
     * @param float $quantityDaysOrHours Days worked or hours worked
     * @param float|null $inputAmount Optional operator override amount
     * @param float $statutoryPeriodFactor Default 2.0 for semi-monthly
     * @return array Array containing 'number_input', 'rate', and 'amount'
     */
    public function calculateBasicPay(
        ?EmployeeMaster $employee,
        float $quantityDaysOrHours,
        ?float $inputAmount = null,
        float $statutoryPeriodFactor = 2.0,
        ?float $rateOverride = null
    ): array {
        if ($inputAmount !== null) {
            $inputAmount = round((float)$inputAmount, 2);

            if ($employee && (float)$employee->daily_rate > 0) {
                $dailyRate = $this->policy->dailyRate($employee);
                $expectedAmount = round($quantityDaysOrHours * $dailyRate, 2);

                if (abs($inputAmount - $expectedAmount) < 0.01) {
                    return [
                        'number_input' => $quantityDaysOrHours,
                        'rate' => $dailyRate,
                        'amount' => $expectedAmount,
                    ];
                }
            } elseif ($employee && (float)$employee->hourly_rate > 0) {
                $hourlyRate = $this->policy->hourlyRate($employee);
                $expectedAmount = round($quantityDaysOrHours * $hourlyRate, 2);

                if (abs($inputAmount - $expectedAmount) < 0.01) {
                    return [
                        'number_input' => $quantityDaysOrHours,
                        'rate' => $hourlyRate,
                        'amount' => $expectedAmount,
                    ];
                }
            }

            // Operator explicit override: quantity 1.00 and unit_rate is inputAmount
            return [
                'number_input' => 1.00,
                'rate' => $inputAmount,
                'amount' => $inputAmount,
            ];
        }

        // Configured Default Income / Recurring Income rate takes precedence if assigned and positive
        if ($rateOverride !== null && (float)$rateOverride > 0) {
            $rate = (float) $rateOverride;
            if ($quantityDaysOrHours > 1.0) {
                $numInput = $quantityDaysOrHours;
            } elseif ($quantityDaysOrHours > 0) {
                if (abs($quantityDaysOrHours - 1.00) < 0.001 && $statutoryPeriodFactor > 1.0) {
                    $numInput = round(1.00 / $statutoryPeriodFactor, 4);
                } else {
                    $numInput = $quantityDaysOrHours;
                }
            } else {
                $numInput = 0.00;
            }

            return [
                'number_input' => $numInput,
                'rate' => $rate,
                'amount' => round($numInput * $rate, 2),
            ];
        }

        // Automatic generation branch: NO HIDDEN FALLBACKS
        // 1. Daily-paid employee (stored daily rate where quantity > 1.0 or no monthly_rate)
        if ($employee && (float)$employee->daily_rate > 0 && ($quantityDaysOrHours > 1.0 || (float)$employee->monthly_rate <= 0)) {
            $dailyRate = (float) $employee->daily_rate;
            return [
                'number_input' => $quantityDaysOrHours,
                'rate' => $dailyRate,
                'amount' => round($quantityDaysOrHours * $dailyRate, 2),
            ];
        }

        // Hourly-paid employee (stored hourly rate with no monthly/daily rate)
        if ($employee && (float)$employee->hourly_rate > 0 && (float)$employee->monthly_rate <= 0 && (float)$employee->daily_rate <= 0) {
            $hourlyRate = (float) $employee->hourly_rate;
            return [
                'number_input' => $quantityDaysOrHours,
                'rate' => $hourlyRate,
                'amount' => round($quantityDaysOrHours * $hourlyRate, 2),
            ];
        }

        // Selector: Use Basic Pay number_input ($quantityDaysOrHours)
        // number_input > 1 → treat as Daily Pay (daily_rate * number_input)
        if ($quantityDaysOrHours > 1.0) {
            $dailyRate = $employee ? (float) $employee->daily_rate : 0.00;
            if ($dailyRate <= 0 && $employee && (float) $employee->monthly_rate > 0) {
                $workdays = (float) ($this->policy->workdays_per_month ?? 26);
                $dailyRate = round((float) $employee->monthly_rate / $workdays, 2);
            }

            return [
                'number_input' => $quantityDaysOrHours,
                'rate' => $dailyRate,
                'amount' => round($quantityDaysOrHours * $dailyRate, 2),
            ];
        }

        // number_input <= 1 → treat employee as Monthly Pay and use monthly rate for period
        if ($employee && (float)$employee->monthly_rate > 0) {
            $monthlyRate = (float) $employee->monthly_rate;

            $numInput = 1.00;
            if ($quantityDaysOrHours > 0) {
                if (abs($quantityDaysOrHours - 1.00) < 0.001 && $statutoryPeriodFactor > 1.0) {
                    $numInput = round(1.00 / $statutoryPeriodFactor, 4);
                } else {
                    $numInput = $quantityDaysOrHours;
                }
            } else {
                $numInput = 0.00;
            }

            return [
                'number_input' => $numInput,
                'rate' => $monthlyRate,
                'amount' => round($numInput * $monthlyRate, 2),
            ];
        }

        // Fallback for daily_rate when quantity <= 1
        if ($employee && (float)$employee->daily_rate > 0) {
            $dailyRate = (float) $employee->daily_rate;
            return [
                'number_input' => $quantityDaysOrHours,
                'rate' => $dailyRate,
                'amount' => round($quantityDaysOrHours * $dailyRate, 2),
            ];
        }

        if ($employee && (float)$employee->hourly_rate > 0) {
            $hourlyRate = $this->policy->hourlyRate($employee);

            if ($quantityDaysOrHours <= 0 && $this->policy->no_attendance_policy === 'fixed_cutoff_salary') {
                $cutoffHours = round(($this->policy->workdays_per_month / $statutoryPeriodFactor) * $this->policy->hours_per_day, 2);
                return [
                    'number_input' => $cutoffHours,
                    'rate' => $hourlyRate,
                    'amount' => round($cutoffHours * $hourlyRate, 2),
                ];
            }

            // Default safe zero-pay policy
            return [
                'number_input' => $quantityDaysOrHours,
                'rate' => $hourlyRate,
                'amount' => round($quantityDaysOrHours * $hourlyRate, 2),
            ];
        }

        return [
            'number_input' => 0.00,
            'rate' => 0.00,
            'amount' => 0.00,
        ];
    }

    /**
     * Calculate overtime pay line item.
     */
    public function calculateOvertimePay(
        ?EmployeeMaster $employee,
        float $overtimeHours,
        ?float $inputOtPay = null,
        string $dayType = 'regular',
        ?float $rateOverride = null
    ): array {
        $otRate = ($rateOverride !== null)
            ? max(0.00, (float) $rateOverride)
            : ($employee ? $this->policy->overtimeRate($employee, $dayType) : 0.00);

        if ($overtimeHours <= 0) {
            return [
                'number_input' => 0.00,
                'rate' => $otRate,
                'amount' => 0.00,
            ];
        }

        if ($inputOtPay !== null) {
            $inputOtPay = round((float)$inputOtPay, 2);
            $expectedAmount = round($overtimeHours * $otRate, 2);

            if (abs($inputOtPay - $expectedAmount) < 0.01 && $otRate > 0) {
                return [
                    'number_input' => $overtimeHours,
                    'rate' => $otRate,
                    'amount' => $expectedAmount,
                ];
            }

            return [
                'number_input' => 1.00,
                'rate' => $inputOtPay,
                'amount' => $inputOtPay,
            ];
        }

        $amount = round($overtimeHours * $otRate, 2);
        return [
            'number_input' => $overtimeHours,
            'rate' => $otRate,
            'amount' => $amount,
        ];
    }
}
