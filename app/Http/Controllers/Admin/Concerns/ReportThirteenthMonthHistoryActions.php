<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Exports\LegacyThirteenthMonthHistoryExport;
use App\Models\Company;
use App\Services\LegacyThirteenthMonthHistoryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

trait ReportThirteenthMonthHistoryActions
{
    /**
     * Display the Legacy 13th Month Payroll History Report.
     */
    public function thirteenthMonthHistory(Request $request)
    {
        $year = (int) ($request->input('year') ?: date('Y'));
        $user = auth()->user();
        $isSuperAdmin = ($user->role_id ?? null) === \App\Models\Role::SUPER_ADMIN || ($user->role ?? null) === 'admin';

        if ($isSuperAdmin) {
            $companyId = (int) ($request->input('company_id') ?: ($user->company_id ?: Company::first()?->id ?: 1));
            $companies = Company::orderBy('name')->get();
        } else {
            $companyId = (int) (session('company_id') ?? $user->company_id ?? 1);
            $companies = collect();
        }

        $service = app(LegacyThirteenthMonthHistoryService::class);
        $reportData = $service->generateReport($companyId, $year);

        $exportFormat = strtolower((string) $request->input('export_format'));
        if ($exportFormat === 'pdf') {
            return view('admin.reports.pdf.thirteenth_month_history', compact('reportData', 'year'));
        }
        if ($exportFormat === 'xlsx') {
            $fileName = '13th_Month_Payroll_History_' . $year . '.xlsx';
            return Excel::download(new LegacyThirteenthMonthHistoryExport($reportData), $fileName);
        }
        if ($exportFormat === 'csv') {
            $fileName = '13th_Month_Payroll_History_' . $year . '.csv';
            return Excel::download(new LegacyThirteenthMonthHistoryExport($reportData), $fileName, \Maatwebsite\Excel\Excel::CSV);
        }

        return view('admin.reports.thirteenth_month_history', compact('reportData', 'year', 'companyId', 'companies', 'isSuperAdmin'));
    }

    /**
     * Export Legacy 13th Month Payroll History Report as PDF / Printable View.
     */
    public function exportThirteenthMonthHistoryPdf(Request $request)
    {
        $year = (int) ($request->input('year') ?: date('Y'));
        $user = auth()->user();
        $isSuperAdmin = ($user->role_id ?? null) === \App\Models\Role::SUPER_ADMIN || ($user->role ?? null) === 'admin';

        if ($isSuperAdmin) {
            $companyId = (int) ($request->input('company_id') ?: ($user->company_id ?: Company::first()?->id ?: 1));
        } else {
            $companyId = (int) (session('company_id') ?? $user->company_id ?? 1);
        }

        $service = app(LegacyThirteenthMonthHistoryService::class);
        $reportData = $service->generateReport($companyId, $year);

        return view('admin.reports.pdf.thirteenth_month_history', compact('reportData', 'year'));
    }

    /**
     * Export Legacy 13th Month Payroll History Report as Excel.
     */
    public function exportThirteenthMonthHistoryExcel(Request $request)
    {
        $year = (int) ($request->input('year') ?: date('Y'));
        $user = auth()->user();
        $isSuperAdmin = ($user->role_id ?? null) === \App\Models\Role::SUPER_ADMIN || ($user->role ?? null) === 'admin';

        if ($isSuperAdmin) {
            $companyId = (int) ($request->input('company_id') ?: ($user->company_id ?: Company::first()?->id ?: 1));
        } else {
            $companyId = (int) (session('company_id') ?? $user->company_id ?? 1);
        }

        $service = app(LegacyThirteenthMonthHistoryService::class);
        $reportData = $service->generateReport($companyId, $year);

        $fileName = '13th_Month_Payroll_History_' . $year . '.xlsx';

        return Excel::download(new LegacyThirteenthMonthHistoryExport($reportData), $fileName);
    }
}
