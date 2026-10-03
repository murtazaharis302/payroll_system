<?php

namespace App\Exceptions;

use InvalidArgumentException;

class PayrollBatchChangeValidationException extends InvalidArgumentException
{
    protected array $errors = [];

    public function __construct(array $errors)
    {
        $this->errors = array_values($errors);
        $count = count($this->errors);
        $summary = "Payroll batch changes blocked: {$count} " . ($count === 1 ? 'issue' : 'issues') . " found.\n" . implode("\n", array_map(fn($e) => "• " . $e, $this->errors));
        parent::__construct($summary);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
