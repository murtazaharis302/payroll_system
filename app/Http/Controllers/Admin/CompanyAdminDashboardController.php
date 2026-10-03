<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\EmpLoan;
use Illuminate\Http\Request;

class CompanyAdminDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $companyId = getActiveCompanyId();
        
        // Get company data
        $companyData = Company::find($companyId);
        
        // Count active employees in this company
        $totalEmployees = EmployeeMaster::where('company_id', $companyId)
            ->where('status', 'active')
            ->count();
        
        // Count pending payroll drafts waiting for approval
        $pendingPayrollDrafts = PayHeader::pendingApproval()
            ->whereHas('employee', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->count();
        
        // Active loans count and sum balance
        $activeValeLoansQuery = EmpLoan::whereHas('employee', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->where('current_balance', '>', 0);

        $activeValeLoans = (clone $activeValeLoansQuery)->count();
        $totalLoanBalance = (clone $activeValeLoansQuery)->sum('current_balance');
        
        // Total Financial Payroll Expenditure for Company
        $totalPayrollExpenditure = PayHeader::whereHas('employee', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereIn('status', ['approved', 'posted', 'paid'])
            ->sum('net_pay');

        // Recent pending approvals list
        $pendingApprovalsList = PayHeader::with(['employee', 'payrollPeriod'])
            ->pendingApproval()
            ->whereHas('employee', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        // Recent Employees
        $recentEmployees = EmployeeMaster::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('dashboards.company_admin', compact(
            'companyData',
            'totalEmployees',
            'recentEmployees',
            'pendingPayrollDrafts',
            'activeValeLoans',
            'totalLoanBalance',
            'totalPayrollExpenditure',
            'pendingApprovalsList'
        ));
    }
}

