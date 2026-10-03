<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PayrollPeriod;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\Company;
use App\Models\PayHeader;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SSSReportExport;
use App\Exports\PhilHealthReportExport;
use App\Exports\PagIBIGReportExport;
use App\Exports\WithholdingTaxReportExport;
use App\Exports\PayrollMasterSheetExport;
use ZipArchive;

trait ReportsHubActions
{
    protected function resolveCompanyId(Request $request): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        // SuperAdmin / Product Admin (role 'admin') can select company_id
        if ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN) {
            if ($request->filled('company_id')) {
                session(['company_id' => (int) $request->input('company_id')]);
                return (int) $request->input('company_id');
            }
            return session('company_id') ?? getActiveCompanyId();
        }

        // Company Admin / HR / Employee: strictly locked to their company
        return $user->company_id ? (int) $user->company_id : getActiveCompanyId();
    }

    /**
     * Display reports dashboard
     */
    public function index(Request $request)
    {
        $companies = collect();
        $selectedCompanyId = null;

        if (auth()->user()->role === 'admin') {
            $companies = Company::where('status', '1')->orderBy('name')->get();
            $selectedCompanyId = $request->query('company_id') ?? session('company_id') ?? ($companies->first()?->id);
            if ($selectedCompanyId) {
                session(['company_id' => (int) $selectedCompanyId]);
            }
            $companyId = $selectedCompanyId;
        } else {
            $companyId = getActiveCompanyId() ?? auth()->user()?->company_id;
            $selectedCompanyId = $companyId;
        }

        $company = $companyId ? Company::find($companyId) : Company::first();

        // Get available payroll periods for the selected company
        $payrollPeriodsQuery = PayrollPeriod::query();
        if ($companyId !== null) {
            $payrollPeriodsQuery->where('company_id', $companyId);
        }
        $payrollPeriods = $payrollPeriodsQuery->orderBy('period_start', 'desc')->get();

        // Get active employees and employees with loan records for the selected company
        $employeesQuery = EmployeeMaster::query();
        if ($companyId !== null) {
            $employeesQuery->where('company_id', $companyId);
        }
        $employees = $employeesQuery->where(function ($q) {
            $q->where('status', 'active')
              ->orWhereHas('empLoans');
        })->orderBy('last_name')->orderBy('first_name')->get();

        // Get statistics for dashboard
        $stats = [
            'total_employees' => $employees->count(),
            'total_payroll_periods' => $payrollPeriods->count(),
            'latest_period' => $payrollPeriods->first(),
        ];

        $bankSettings = \App\Models\CompanyBankSetting::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->get();

        $incomeTypes = \App\Models\IncomeType::forCompany($selectedCompanyId)->where('is_active', true)->orderBy('name')->get();
        $deductionTypes = \App\Models\DeductionType::forCompany($selectedCompanyId)->where('is_active', true)->orderBy('name')->get();
        $departments = \App\Models\Department::where('is_active', true)
            ->when($selectedCompanyId !== null, fn($q) => $q->where('company_id', $selectedCompanyId))
            ->orderBy('name')
            ->get();

        $reportMonths = $payrollPeriods->map(function ($period) {
            if (!$period->period_start) {
                return null;
            }
            $date = \Carbon\Carbon::parse($period->period_start);
            return [
                'value' => $date->format('Y-m'),
                'label' => $date->format('F Y'),
            ];
        })->filter()->unique('value')->values();

        return view('admin.reports.index', compact('payrollPeriods', 'employees', 'company', 'stats', 'companies', 'selectedCompanyId', 'bankSettings', 'incomeTypes', 'deductionTypes', 'reportMonths', 'departments'));
    }

    /**
     * Generate individual employee payslip (PDF)
     */
}
