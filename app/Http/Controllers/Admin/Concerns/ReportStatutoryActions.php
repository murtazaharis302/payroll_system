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

trait ReportStatutoryActions
{
    public function payslip(Request $request)
    {
        try {
            $request->validate([
                'payroll_period_id' => 'required|exists:payroll_periods,id',
                'employee_id' => 'required|exists:employees_master,id',
            ]);

            $period = PayrollPeriod::findOrFail($request->payroll_period_id);
            $companyId = $this->resolveCompanyId($request) ?? $period->company_id;

            $service = app(\App\Services\Reports\PayslipReportService::class);
            $data = $service->buildSingle((int)$request->payroll_period_id, (int)$request->employee_id, $companyId);

            if (!$data) {
                return back()->with('error', 'No payroll data found for selected employee and period.');
            }

            $payHeader = $data['payHeader'];
            $company = $data['company'];

            $pdf = app('dompdf.wrapper')->loadView('admin.reports.payslip', compact('payHeader', 'company'));
            $filename = $this->sanitizeFilename('Payslip_' . $payHeader->employee->first_name . '_' . $payHeader->employee->last_name . '_' . $payHeader->payrollPeriod->period_name . '.pdf');

            return $pdf->stream($filename);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Payslip generation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate payslip: ' . $e->getMessage());
        }
    }

    /**
     * Generate payroll register for all employees (PDF)
     */
    public function payrollRegister(Request $request)
    {
        try {
            $request->validate([
                'payroll_period_id' => 'required|exists:payroll_periods,id',
            ]);

            $period = PayrollPeriod::findOrFail($request->payroll_period_id);
            $companyId = $this->resolveCompanyId($request) ?? $period->company_id;
            $company = Company::find($companyId) ?? $period->company ?? Company::first();

            $service = app(\App\Services\Reports\PayrollRegisterReportService::class);
            $data = $service->build($period, $company, $companyId);

            if ($data['payrolls']->isEmpty()) {
                return back()->with('error', 'No payroll data found for selected period.');
            }

            if ($request->input('export_format') === 'html') {
                return view('admin.reports.payroll-register', [
                    'payrolls' => $data['payrolls'],
                    'company' => $data['company'],
                    'period' => $data['period'],
                    'totals' => $data['totals'],
                ]);
            }

            $pdf = app('dompdf.wrapper')->loadView('admin.reports.payroll-register', [
                'payrolls' => $data['payrolls'],
                'company' => $data['company'],
                'period' => $data['period'],
                'totals' => $data['totals'],
            ])->setPaper('legal', 'landscape');

            $filename = $this->sanitizeFilename('Payroll_Register_' . $period->period_name . '.pdf');

            return $pdf->stream($filename);
        } catch (\Exception $e) {
            \Log::error('Payroll register generation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate payroll register. Please try again.');
        }
    }

    /**
     * Helper to resolve start and end month strings from request (month range or single month).
     */
    protected function parseStatutoryMonthRange(Request $request): array
    {
        $request->validate([
            'from_month' => 'nullable|date_format:Y-m',
            'to_month'   => 'nullable|date_format:Y-m',
            'month'      => 'nullable|date_format:Y-m',
        ]);

        $fromMonth = $request->input('from_month') ?? $request->input('month');
        $toMonth   = $request->input('to_month') ?? $fromMonth;

        if (!$fromMonth) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'from_month' => ['Please select a valid month or month range.'],
            ]);
        }

        if ($fromMonth > $toMonth) {
            $temp = $fromMonth;
            $fromMonth = $toMonth;
            $toMonth = $temp;
        }

        return [$fromMonth, $toMonth];
    }

    /**
     * Generate SSS contribution report (Excel)
     */
    public function sssReport(Request $request)
    {
        [$fromMonth, $toMonth] = $this->parseStatutoryMonthRange($request);
        $companyId = $this->resolveCompanyId($request);

        $dateStr = ($fromMonth === $toMonth) ? $fromMonth : "{$fromMonth}_to_{$toMonth}";
        $filename = $this->sanitizeFilename('SSS_Report_' . $dateStr . '.xlsx');

        return Excel::download(new SSSReportExport($companyId, $fromMonth, $toMonth), $filename);
    }

    /**
     * Generate PhilHealth contribution report (Excel)
     */
    public function philhealthReport(Request $request)
    {
        [$fromMonth, $toMonth] = $this->parseStatutoryMonthRange($request);
        $companyId = $this->resolveCompanyId($request);

        $dateStr = ($fromMonth === $toMonth) ? $fromMonth : "{$fromMonth}_to_{$toMonth}";
        $filename = $this->sanitizeFilename('PhilHealth_Report_' . $dateStr . '.xlsx');

        return Excel::download(new PhilHealthReportExport($companyId, $fromMonth, $toMonth), $filename);
    }

    /**
     * Generate Pag-IBIG contribution report (Excel)
     */
    public function pagibigReport(Request $request)
    {
        [$fromMonth, $toMonth] = $this->parseStatutoryMonthRange($request);
        $companyId = $this->resolveCompanyId($request);

        $dateStr = ($fromMonth === $toMonth) ? $fromMonth : "{$fromMonth}_to_{$toMonth}";
        $filename = $this->sanitizeFilename('PagIBIG_Report_' . $dateStr . '.xlsx');

        return Excel::download(new PagIBIGReportExport($companyId, $fromMonth, $toMonth), $filename);
    }

    /**
     * Generate withholding tax report (Excel)
     */
    public function withholdingTaxReport(Request $request)
    {
        [$fromMonth, $toMonth] = $this->parseStatutoryMonthRange($request);
        $companyId = $this->resolveCompanyId($request);

        $dateStr = ($fromMonth === $toMonth) ? $fromMonth : "{$fromMonth}_to_{$toMonth}";
        $filename = $this->sanitizeFilename('Withholding_Tax_Report_' . $dateStr . '.xlsx');

        return Excel::download(new WithholdingTaxReportExport($companyId, $fromMonth, $toMonth), $filename);
    }

    /**
     * Generate loan ledger report (PDF)
     */
    public function loanLedger(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees_master,id',
            'loan_type' => 'required|in:vale,goods,sss,pagibig,all',
        ]);

        $companyId = getCompanyId();
        $company = Company::find($companyId) ?? Company::first();

        // Build employee query with conditional company filter
        $employeeQuery = EmployeeMaster::where('id', $request->employee_id);
        if ($companyId !== null) {
            $employeeQuery->where('company_id', $companyId);
        }
        $employee = $employeeQuery->firstOrFail();

        $data = [];
        $loanType = $request->loan_type;

        $data['vale_loans'] = collect();
        $data['goods_purchases'] = collect();
        $data['government_loans'] = collect();

        $loansQuery = \App\Models\EmpLoan::with(['payments.payHeader.payrollPeriod', 'loanType'])
            ->where('employee_id', $request->employee_id);

        if ($companyId !== null) {
            $loansQuery->whereHas('employee', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $allLoans = $loansQuery->get();

        foreach ($allLoans as $loan) {
            $code = $loan->loanType ? strtolower($loan->loanType->name ?? '') : '';
            if (str_contains($code, 'vale')) {
                if ($loanType === 'vale' || $loanType === 'all') {
                    $data['vale_loans']->push($loan);
                }
            } elseif (str_contains($code, 'goods')) {
                if ($loanType === 'goods' || $loanType === 'all') {
                    $data['goods_purchases']->push($loan);
                }
            } else {
                if ($loanType === 'sss' || $loanType === 'pagibig' || $loanType === 'all') {
                    if ($loanType === 'sss' && !str_contains($code, 'sss')) {
                        continue;
                    }
                    if ($loanType === 'pagibig' && !str_contains($code, 'pagibig') && !str_contains($code, 'pag-ibig')) {
                        continue;
                    }
                    $data['government_loans']->push($loan);
                }
            }
        }

        $pdf = app('dompdf.wrapper')->loadView('admin.reports.loan-ledger', compact('employee', 'company', 'data', 'loanType'));

        $filename = $this->sanitizeFilename('Loan_Ledger_' . $employee->first_name . '_' . $employee->last_name . '.pdf');

        return $pdf->stream($filename);
    }

    /**
     * Generate alpha list (alphabetical employee list with pay) (PDF)
     */
    public function alphaList(Request $request)
    {
        $request->validate([
            'payroll_period_id' => 'required|exists:payroll_periods,id',
        ]);

        $companyId = getCompanyId();
        $company = Company::find($companyId) ?? Company::first();

        $period = PayrollPeriod::findOrFail($request->payroll_period_id);

        // Build query with conditional company filter
        $query = PayHeader::with(['employee'])->where('payroll_period_id', $request->payroll_period_id);
        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $payrolls = $query->get()->sortBy(function($payroll) {
            return $payroll->employee->last_name . ' ' . $payroll->employee->first_name;
        });

        // Calculate totals
        $totals = [
            'gross_income' => $payrolls->sum('gross_income'),
            'total_deductions' => $payrolls->sum('total_deductions'),
            'net_pay' => $payrolls->sum('net_pay'),
        ];

        $pdf = app('dompdf.wrapper')->loadView('admin.reports.alpha-list', compact('payrolls', 'company', 'period', 'totals'));

        $filename = $this->sanitizeFilename('Alpha_List_' . $period->period_name . '.pdf');

        return $pdf->stream($filename);
    }

    /**
     * Generate payroll summary 1 report (PDF)
     * Shows Basic Pay, OT, Other Income columns
     * Dynamic deduction columns based on what's active in the period
     */
}
