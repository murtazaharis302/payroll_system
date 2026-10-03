<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Controllers\Controller;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\EmpIncome;
use App\Models\EmpDeduction;
use App\Models\EmpLoan;
use App\Models\AttendanceLog;
use App\Models\ActivityLog;
use App\Models\IncomeType;
use App\Models\DeductionType;
use App\Models\PayrollPolicy;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use App\Services\PayrollProcessingService;
use App\Services\PayrollLineCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Requests\AddPayrollDraftEmployeeRequest;
use App\Http\Requests\StorePayrollDraftRequest;
use Carbon\Carbon;

trait PayrollDraftUpdateActions
{
    public function update(Request $request, $id)
    {
        $preflightResult = require app_path('Http/Controllers/Admin/PayrollDraftUpdate/preflight.php');
        if ($preflightResult !== null) {
            return $preflightResult;
        }

        DB::beginTransaction();
        try {
            require app_path('Http/Controllers/Admin/PayrollDraftUpdate/calculation_setup.php');
            return require app_path('Http/Controllers/Admin/PayrollDraftUpdate/persist.php');
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Failed to save payroll draft: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('statusDanger', 'Failed to save payroll draft: ' . $e->getMessage());
        }
    }
}
