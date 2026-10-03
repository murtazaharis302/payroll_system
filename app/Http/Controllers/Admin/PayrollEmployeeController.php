<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\PayrollEmployeeSetupActions;
use App\Http\Controllers\Admin\Concerns\PayrollEmployeeCrudActions;
use App\Http\Controllers\Admin\Concerns\PayrollEmployeeMasterActions;
use App\Http\Controllers\Admin\Concerns\PayrollEmployeeQuickRecordActions;

class PayrollEmployeeController extends Controller
{
    use PayrollEmployeeSetupActions,
        PayrollEmployeeCrudActions,
        PayrollEmployeeMasterActions,
        PayrollEmployeeQuickRecordActions;
}
