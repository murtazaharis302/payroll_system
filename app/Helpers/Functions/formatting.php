<?php


use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\Notification;
use App\Models\User;

/**
 * Format Philippine currency
 */
function formatPeso($amount)
{
    return '₱'.number_format($amount, 2);
}

/**
 * Calculate SSS contribution based on salary
 * (Simplified - actual rates should be from config/database)
 */
function calculateSSSContribution($monthlySalary)
{
    // This is a simplified example - implement actual SSS table
    if ($monthlySalary <= 4250) {
        return ['employee' => 180, 'employer' => 400, 'total' => 580];
    } elseif ($monthlySalary <= 4750) {
        return ['employee' => 202.50, 'employer' => 450, 'total' => 652.50];
    }
    // Add more brackets...

    // Maximum
    return ['employee' => 900, 'employer' => 2000, 'total' => 2900];
}

/**
 * Calculate PhilHealth contribution
 */
function calculatePhilHealthContribution($monthlySalary)
{
    $rate = 0.05; // 5% total (2.5% each for employee and employer)
    $total = $monthlySalary * $rate;
    $employeeShare = $total / 2;
    $employerShare = $total / 2;

    // Apply minimum and maximum
    $employeeShare = max(500, min($employeeShare, 5000));
    $employerShare = $employeeShare;

    return [
        'employee' => $employeeShare,
        'employer' => $employerShare,
        'total' => $employeeShare + $employerShare,
    ];
}

/**
 * Calculate Pag-IBIG contribution
 */
function calculatePagIbigContribution($monthlySalary)
{
    if ($monthlySalary <= 1500) {
        $employeeRate = 0.01; // 1%
        $employerRate = 0.02; // 2%
    } else {
        $employeeRate = 0.02; // 2%
        $employerRate = 0.02; // 2%
    }

    $employeeShare = $monthlySalary * $employeeRate;
    $employerShare = $monthlySalary * $employerRate;

    // Apply maximum of 100 for employee
    $employeeShare = min($employeeShare, 100);
    $employerShare = min($employerShare, 100);

    return [
        'employee' => $employeeShare,
        'employer' => $employerShare,
        'total' => $employeeShare + $employerShare,
    ];
}

/**
 * Alias for getActiveCompanyId() - shorter name for convenience
 */
function getCompanyId()
{
    return getActiveCompanyId();
}

/**
 * Convert number to spelled-out English words with safe fallback if ext-intl NumberFormatter is not installed.
 */
function numberToWords($number)
{
    if (class_exists('\NumberFormatter')) {
        try {
            $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);

            return $formatter->format($number);
        } catch (\Throwable $e) {
            // Fallback to pure PHP converter
        }
    }

    $number = (float) $number;
    $whole = (int) floor($number);
    $fraction = (int) round(($number - $whole) * 100);

    $units = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    $convertNumber = function ($n) use (&$convertNumber, $units, $tens) {
        if ($n < 20) {
            return $units[$n];
        }
        if ($n < 100) {
            return $tens[(int) ($n / 10)].($n % 10 ? '-'.$units[$n % 10] : '');
        }
        if ($n < 1000) {
            return $units[(int) ($n / 100)].' hundred'.($n % 100 ? ' '.$convertNumber($n % 100) : '');
        }
        if ($n < 1000000) {
            return $convertNumber((int) ($n / 1000)).' thousand'.($n % 1000 ? ' '.$convertNumber($n % 1000) : '');
        }
        if ($n < 1000000000) {
            return $convertNumber((int) ($n / 1000000)).' million'.($n % 1000000 ? ' '.$convertNumber($n % 1000000) : '');
        }

        return (string) $n;
    };

    $words = [];
    if ($whole == 0) {
        $words[] = 'zero';
    } else {
        $words[] = $convertNumber($whole);
    }

    if ($fraction > 0) {
        $words[] = 'and '.sprintf('%02d', $fraction).'/100';
    }

    return implode(' ', $words);
}
