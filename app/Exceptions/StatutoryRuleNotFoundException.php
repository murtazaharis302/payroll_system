<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class StatutoryRuleNotFoundException extends RuntimeException
{
    public function __construct(string $statutoryType, string $payDate)
    {
        $typeUpper = strtoupper($statutoryType);
        parent::__construct(
            "Statutory rule configuration for {$typeUpper} not found or expired for pay date {$payDate}. " .
            "Payroll processing blocked to prevent silent stale fallback."
        );
    }
}
