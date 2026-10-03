<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Concerns\PayrollEmployeeDraftActions;
use App\Services\Concerns\PayrollDraftCalculationActions;

class PayrollProcessingService
{
    use PayrollEmployeeDraftActions, PayrollDraftCalculationActions;
}
