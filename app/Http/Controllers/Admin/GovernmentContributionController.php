<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GovernmentContribution;
use App\Models\EmployeeMaster;
use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GovernmentContributionController extends Controller
{
    /**
     * Get company ID for current user
     */
    protected function getCompanyId()
    {
        return getActiveCompanyId();
    }
    
    /**
     * Display a listing of government contributions.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = ($user && ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN));

        $companies = collect();
        if ($isSuperAdmin) {
            $companies = Company::where('status', 1)->orderBy('name')->get();
            $selectedCompanyId = $request->input('company_id');
        } else {
            $selectedCompanyId = getActiveCompanyId() ?? ($user?->company_id);
        }

        $query = GovernmentContribution::with(['employee', 'company', 'payrollPeriod']);

        // Apply company filter if selectedCompanyId is set
        if ($selectedCompanyId) {
            $query->where('company_id', $selectedCompanyId);
        }

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Filter by employee
        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by period
        if ($request->has('period_start') && $request->period_start) {
            $query->whereDate('period_start', '>=', $request->period_start);
        }
        if ($request->has('period_end') && $request->period_end) {
            $query->whereDate('period_end', '<=', $request->period_end);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 50);
        $contributions = $query->orderBy('period_start', 'desc')->paginate($perPage)->withQueryString();

        $employeesQuery = EmployeeMaster::where('status', 'active');
        if ($selectedCompanyId) {
            $employeesQuery->where('company_id', $selectedCompanyId);
        }
        $employees = $employeesQuery->orderBy('last_name')->get();

        return view('admin.government-contributions.index', compact('contributions', 'employees', 'companies', 'selectedCompanyId', 'isSuperAdmin'));
    }

    /**
     * Manual entry disabled — contributions sync from payroll deductions.
     */
    public function create()
    {
        return redirect()->route('government-contributions.index')
            ->with('statusDanger', 'Contributions are auto-tracked from Payroll Input deductions. Manual add is not allowed.');
    }

    /**
     * Manual entry disabled — contributions sync from payroll deductions.
     */
    public function store(Request $request)
    {
        return redirect()->route('government-contributions.index')
            ->with('statusDanger', 'Contributions are auto-tracked from Payroll Input deductions. Manual add is not allowed.');
    }

    /**
     * Display the specified contribution.
     */
    public function show($id)
    {
        $contribution = GovernmentContribution::with(['employee', 'company', 'payrollPeriod'])
            ->findOrFail($id);

        authorizeCompanyAccess($contribution->company_id);

        return view('admin.government-contributions.show', compact('contribution'));
    }

    /**
     * Manual edit disabled — update payroll deductions instead.
     */
    public function edit($id)
    {
        return redirect()->route('government-contributions.show', $id)
            ->with('statusDanger', 'Edit payroll deductions to update contribution amounts.');
    }

    /**
     * Manual edit disabled — update payroll deductions instead.
     */
    public function update(Request $request, $id)
    {
        return redirect()->route('government-contributions.show', $id)
            ->with('statusDanger', 'Edit payroll deductions to update contribution amounts.');
    }

    /**
     * Manual delete disabled — remove deduction from payroll draft instead.
     */
    public function destroy($id)
    {
        return redirect()->route('government-contributions.index')
            ->with('statusDanger', 'Contributions are linked to payroll. Clear the deduction in Payroll Input instead.');
    }
}
