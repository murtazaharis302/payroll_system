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

trait ReportSummaryActions
{
    public function payrollSummary1(Request $request)
    {
        $request->validate([
            'payroll_period_id' => 'required|exists:payroll_periods,id',
            'department_id' => 'nullable',
            'export_format' => 'nullable|string',
        ]);

        $period = PayrollPeriod::findOrFail($request->payroll_period_id);
        $companyId = $this->resolveCompanyId($request) ?? $period->company_id;
        $company = Company::find($companyId) ?? $period->company ?? Company::first();

        $deptFilter = ($request->filled('department_id') && $request->department_id !== 'all') ? $request->department_id : null;

        $exportFormat = strtolower((string) $request->input('export_format', 'pdf'));
        if ($exportFormat === 'xlsx' || $exportFormat === 'excel') {
            $filename = $this->sanitizeFilename('Payroll_Summary_1_' . $period->period_name . '.xlsx');
            return Excel::download(new PayrollMasterSheetExport($period->id, $companyId, $deptFilter), $filename);
        }

        if ($exportFormat === 'csv') {
            $filename = $this->sanitizeFilename('Payroll_Summary_1_' . $period->period_name . '.csv');
            return Excel::download(new PayrollMasterSheetExport($period->id, $companyId, $deptFilter), $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        $paperSize = $request->input('paper_size', 'legal');
        $summaryService = app(\App\Services\Reports\PayrollSummaryReportService::class);
        $summaryData = $summaryService->build($period, $company, $companyId, $deptFilter, $paperSize);

        $payrolls = $summaryData['payrolls'];
        if ($payrolls->isEmpty()) {
            return back()->with('error', 'No payroll data found for selected criteria.');
        }

        if ($exportFormat === 'html') {
            return view('admin.reports.payroll-summary-1', $summaryData);
        }

        $pdf = app('dompdf.wrapper')->loadView('admin.reports.payroll-summary-1', $summaryData)
            ->setPaper($paperSize, 'landscape');

        $filename = $this->sanitizeFilename('Payroll_Summary_1_' . $period->period_name . '.pdf');

        return $pdf->stream($filename);
    }

    /**
     * Generate payroll summary 2 report (PDF)
     * Similar to Summary 1 but different format/layout
     */
    public function payrollSummary2(Request $request)
    {
        $request->validate([
            'payroll_period_id' => 'required|exists:payroll_periods,id',
            'department_id' => 'nullable',
            'export_format' => 'nullable|string',
        ]);

        $period = PayrollPeriod::findOrFail($request->payroll_period_id);
        $companyId = $this->resolveCompanyId($request) ?? $period->company_id;
        $company = Company::find($companyId) ?? $period->company ?? Company::first();

        $deptFilter = ($request->filled('department_id') && $request->department_id !== 'all') ? $request->department_id : null;

        if ($deptFilter) {
            $deptObj = \App\Models\Department::find($deptFilter);
            $selectedDepartmentName = $deptObj ? $deptObj->name : 'Department ' . $deptFilter;
        } else {
            $selectedDepartmentName = 'All Departments';
        }

        $exportFormat = strtolower((string) $request->input('export_format', 'pdf'));
        if ($exportFormat === 'xlsx' || $exportFormat === 'excel') {
            $filename = $this->sanitizeFilename('Payroll_Summary_2_' . $period->period_name . '.xlsx');
            return Excel::download(new PayrollMasterSheetExport($period->id, $companyId, $deptFilter), $filename);
        }

        if ($exportFormat === 'csv') {
            $filename = $this->sanitizeFilename('Payroll_Summary_2_' . $period->period_name . '.csv');
            return Excel::download(new PayrollMasterSheetExport($period->id, $companyId, $deptFilter), $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        $paperSize = $request->input('paper_size', 'legal');
        $summaryService = app(\App\Services\Reports\PayrollSummaryReportService::class);
        $summaryData = $summaryService->build($period, $company, $companyId, $deptFilter, $paperSize);

        $payrolls = $summaryData['payrolls'];
        if ($payrolls->isEmpty()) {
            return back()->with('error', 'No payroll data found for selected criteria.');
        }

        $payrollRowsGrouped = $summaryData['payrollRows']->groupBy(function ($row) {
            return $row['employee'] && $row['employee']->dept ? $row['employee']->dept->name : 'Unassigned';
        });

        $summaryData['payrollRowsGrouped'] = $payrollRowsGrouped;
        $summaryData['selectedDepartmentName'] = $selectedDepartmentName;

        if ($exportFormat === 'html') {
            return view('admin.reports.payroll-summary-2', $summaryData);
        }

        $pdf = app('dompdf.wrapper')->loadView('admin.reports.payroll-summary-2', $summaryData)
            ->setPaper($paperSize, 'landscape');

        $filename = $this->sanitizeFilename('Payroll_Summary_2_' . $period->period_name . '.pdf');

        return $pdf->stream($filename);
    }

    /**
     * Generate cash voucher report (PDF) - 2 vouchers per short bond paper (Letter size)
     * Reflects gross applicable earnings only (no deductions)
     */
}
