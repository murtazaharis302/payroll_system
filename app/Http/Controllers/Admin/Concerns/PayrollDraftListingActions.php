<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use Illuminate\Http\Request;

trait PayrollDraftListingActions
{
    /**
     * Get company ID for current user
     */
    protected function getCompanyId()
    {
        return getActiveCompanyId(); // Uses helper function
    }

    /**
     * Display a listing of payroll drafts for HR.
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN;
        $isHr = $currentUser->role === 'hr' || $currentUser->role_id === Role::HR;
        $canViewIncomeColumn = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_column_visibility');

        $companies = collect();
        if ($isSuperAdmin) {
            $companies = Company::where('status', '1')->orderBy('name')->get();
            $selectedCompanyId = $request->filled('company_id') ? (int) $request->input('company_id') : getActiveCompanyId();
        } else {
            $selectedCompanyId = $currentUser->company_id ?? getActiveCompanyId();
        }

        $query = PayHeader::with(['employee.dept', 'payrollPeriod', 'approvedBy']);

        if ($selectedCompanyId) {
            $query->whereHas('employee', function ($q) use ($selectedCompanyId) {
                $q->where('company_id', $selectedCompanyId);
            });
        }

        // Scope payroll periods to active company and validate requested period
        $payrollPeriodsQuery = PayrollPeriod::query();
        if ($selectedCompanyId) {
            $payrollPeriodsQuery->where('company_id', $selectedCompanyId);
        }
        $payrollPeriods = $payrollPeriodsQuery->orderBy('period_start', 'desc')->get();

        $selectedPayrollPeriod = null;
        if ($request->filled('payroll_period_id')) {
            $selectedPayrollPeriod = $payrollPeriods->firstWhere('id', (int) $request->input('payroll_period_id'));
            if ($selectedPayrollPeriod) {
                $query->where('payroll_period_id', $selectedPayrollPeriod->id);
            }
        }

        // Scope active departments (by selected company, selected period, or all active departments)
        $departmentsQuery = Department::query();
        if ($selectedCompanyId) {
            $departmentsQuery->where('company_id', $selectedCompanyId);
        } elseif ($selectedPayrollPeriod && $selectedPayrollPeriod->company_id) {
            $departmentsQuery->where('company_id', $selectedPayrollPeriod->company_id);
        }

        $departments = $departmentsQuery
            ->where(function ($q) {
                $q->where('is_active', true)->orWhere('is_active', 1)->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->get();

        $selectedDeptId = null;
        if ($request->filled('dept_id')) {
            $requestedDeptId = (int) $request->input('dept_id');
            if ($departments->contains('id', $requestedDeptId)) {
                $selectedDeptId = $requestedDeptId;
                $query->whereHas('employee', function ($q) use ($selectedDeptId) {
                    $q->where('dept_id', $selectedDeptId);
                });
            }
        }

        // Scope active employees (by selected company, selected period, or all active employees)
        $employeesQuery = EmployeeMaster::query();
        if ($selectedCompanyId) {
            $employeesQuery->where('company_id', $selectedCompanyId);
        } elseif ($selectedPayrollPeriod && $selectedPayrollPeriod->company_id) {
            $employeesQuery->where('company_id', $selectedPayrollPeriod->company_id);
        }

        $employees = $employeesQuery
            ->where(function ($q) {
                $q->where('status', 'active')->orWhere('status', 1)->orWhereNull('status');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $selectedEmployeeId = null;
        if ($request->filled('employee_id')) {
            $requestedEmpId = (int) $request->input('employee_id');
            if ($requestedEmpId > 0) {
                $selectedEmployeeId = $requestedEmpId;
                $query->where('employee_id', $selectedEmployeeId);
                if (!$employees->contains('id', $selectedEmployeeId)) {
                    $selectedEmpModel = EmployeeMaster::find($selectedEmployeeId);
                    if ($selectedEmpModel) {
                        $employees->push($selectedEmpModel);
                    }
                }
            }
        }

        // Filter by real workflow status
        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if (in_array($status, ['draft', 'pending_approval', 'approved', 'rejected'], true)) {
                $query->where('status', $status);
            }
        }

        $perPage = (int) $request->input('per_page', 50);
        $drafts = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        return view('admin.payroll-drafts.index', compact(
            'drafts',
            'payrollPeriods',
            'departments',
            'selectedDeptId',
            'employees',
            'selectedEmployeeId',
            'companies',
            'selectedCompanyId',
            'selectedPayrollPeriod',
            'canViewIncomeColumn',
            'isHr'
        ));
    }
}
