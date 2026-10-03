<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\PayrollDraftGenerationActions;
use App\Http\Controllers\Admin\Concerns\PayrollDraftListingActions;
use App\Http\Controllers\Admin\Concerns\PayrollDraftUpdateActions;
use App\Http\Controllers\Admin\Concerns\PayrollDraftLineActions;
use App\Http\Controllers\Admin\Concerns\PayrollDraftLineCalculationActions;
use App\Http\Controllers\Admin\Concerns\PayrollDraftWorkflowActions;
use App\Http\Controllers\Admin\Concerns\PayrollDraftBatchChangeActions;

class PayrollDraftController extends Controller
{
    use PayrollDraftListingActions,
        PayrollDraftGenerationActions,
        PayrollDraftLineActions,
        PayrollDraftLineCalculationActions,
        PayrollDraftUpdateActions,
        PayrollDraftWorkflowActions,
        PayrollDraftBatchChangeActions;
}
