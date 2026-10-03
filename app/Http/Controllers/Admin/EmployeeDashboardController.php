<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\EmpLoan;
use App\Models\AttendanceLog;
use App\Models\HRAdjustment;
use Illuminate\Http\Request;

class EmployeeDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $employeeData = $user->employee;
        $companyId = getActiveCompanyId() ?? ($employeeData ? $employeeData->company_id : null);
        $companyData = $companyId ? Company::find($companyId) : null;
        $employeeId = $employeeData ? $employeeData->id : 0;

        // Recent payslips for this employee
        $recentPayslips = PayHeader::with('payrollPeriod')
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'posted', 'paid'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $latestPayslip = $recentPayslips->first();

        // Employee's active loans
        $myValeLoans = EmpLoan::where('employee_id', $employeeId)
            ->where('current_balance', '>', 0)
            ->get();

        $totalActiveLoanBalance = $myValeLoans->sum('current_balance');

        // Attendance summary for current month
        $attendanceCountThisMonth = AttendanceLog::where('employee_id', $employeeId)
            ->whereMonth('log_date', now()->month)
            ->whereYear('log_date', now()->year)
            ->count();

        $recentAttendance = AttendanceLog::where('employee_id', $employeeId)
            ->orderBy('log_datetime', 'desc')
            ->take(5)
            ->get();

        // Employee HR adjustments
        $recentAdjustments = HRAdjustment::where('employee_id', $employeeId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('dashboards.employee', compact(
            'companyData',
            'employeeData',
            'recentPayslips',
            'latestPayslip',
            'myValeLoans',
            'totalActiveLoanBalance',
            'attendanceCountThisMonth',
            'recentAttendance',
            'recentAdjustments'
        ));
    }

    public function downloadMyPayslip($id)
    {
        $user = auth()->user();
        $employeeData = $user->employee;

        // If admin/company/hr role, allow fetching pay header directly
        if (in_array($user->role, ['admin', 'company', 'hr'])) {
            $payHeader = PayHeader::with(['payIns.incomeType', 'payOuts.deductionType', 'employee', 'payrollPeriod'])
                ->findOrFail($id);
        } else {
            if (!$employeeData) {
                abort(403, 'No employee record linked to account.');
            }
            $payHeader = PayHeader::with(['payIns.incomeType', 'payOuts.deductionType', 'employee', 'payrollPeriod'])
                ->where('id', $id)
                ->where('employee_id', $employeeData->id)
                ->firstOrFail();
        }

        $company = $payHeader->employee->company ?? Company::find($payHeader->employee->company_id) ?? Company::first();

        $pdf = app('dompdf.wrapper')->loadView('admin.reports.payslip', compact('payHeader', 'company'));
        
        $empFirstName = $payHeader->employee->first_name ?? 'Employee';
        $empLastName = $payHeader->employee->last_name ?? '';
        $periodName = $payHeader->payrollPeriod->period_name ?? 'Period';
        $filename = 'Payslip_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $empFirstName . '_' . $empLastName . '_' . $periodName) . '.pdf';

        return $pdf->stream($filename);
    }
}

