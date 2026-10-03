<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\HRAdjustment;
use Illuminate\Http\Request;

class HRDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $companyId = getActiveCompanyId();
        $companyData = $companyId ? Company::find($companyId) : null;
        
        // Count active employees
        $totalEmployees = EmployeeMaster::where('status', 'active')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->count();
        
        // Count draft payrolls
        $draftPayrolls = PayHeader::draft()
            ->when($companyId, function($q) use ($companyId) {
                $q->whereHas('employee', fn($emp) => $emp->where('company_id', $companyId));
            })->count();
        
        // Count pending approval payrolls
        $pendingPayrolls = PayHeader::pendingApproval()
            ->when($companyId, function($q) use ($companyId) {
                $q->whereHas('employee', fn($emp) => $emp->where('company_id', $companyId));
            })->count();

        // Pending HR Adjustments count
        $pendingAdjustments = HRAdjustment::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'pending')
            ->count();
        
        // Recent payroll drafts
        $recentDrafts = PayHeader::with(['employee', 'payrollPeriod'])
            ->when($companyId, function($q) use ($companyId) {
                $q->whereHas('employee', fn($emp) => $emp->where('company_id', $companyId));
            })
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Recent HR Adjustments
        $recentAdjustments = HRAdjustment::with('employee')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Active payroll periods
        $activePeriods = PayrollPeriod::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('period_start', 'desc')
            ->take(5)
            ->get();

        return view('dashboards.hr', compact(
            'companyData',
            'totalEmployees',
            'draftPayrolls',
            'pendingPayrolls',
            'pendingAdjustments',
            'recentDrafts',
            'recentAdjustments',
            'activePeriods'
        ));
    }
}

