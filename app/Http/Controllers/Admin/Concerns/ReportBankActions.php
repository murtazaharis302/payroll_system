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

trait ReportBankActions
{
    public function bankReport(Request $request)
    {
        try {
            $fromPeriodId = $request->input('from_period_id', $request->input('payroll_period_id'));
            $toPeriodId   = $request->input('to_period_id', $fromPeriodId);

            if (!$fromPeriodId) {
                return back()->with('error', 'Please select both Start Period and End Period.');
            }

            $companyId = $this->resolveCompanyId($request);

            $fromPeriodQuery = \App\Models\PayrollPeriod::where('id', $fromPeriodId);
            $toPeriodQuery   = \App\Models\PayrollPeriod::where('id', $toPeriodId ?? $fromPeriodId);
            if ($companyId !== null) {
                $fromPeriodQuery->where('company_id', $companyId);
                $toPeriodQuery->where('company_id', $companyId);
            }
            $fromPeriod = $fromPeriodQuery->firstOrFail();
            $toPeriod   = $toPeriodQuery->firstOrFail();

            $company = \App\Models\Company::find($companyId) ?? $fromPeriod->company ?? \App\Models\Company::first();

            $departmentId = $request->input('department_id');
            if ($departmentId === 'all') {
                $departmentId = null;
            }

            $options = [
                'bank_setting_id'    => $request->input('bank_setting_id'),
                'bank_name'          => $request->input('bank_name'),
                'branch_name'        => $request->input('branch_name'),
                'manager_name'       => $request->input('manager_name'),
                'company_acct'       => $request->input('company_acct'),
                'signatory_name'     => $request->input('signatory_name'),
                'signatory_position' => $request->input('signatory_position'),
                'signatory_2_name'   => $request->input('signatory_2_name'),
                'signatory_2_position'=> $request->input('signatory_2_position'),
            ];

            $service = app(\App\Services\Reports\BankAdviceReportService::class);
            $adviceData = $service->build($fromPeriod, $toPeriod, $company, $companyId, $departmentId, $options);

            $reportData    = $adviceData['reportData'];
            $periods       = $adviceData['periods'];
            $fromPeriod    = $adviceData['fromPeriod'];
            $toPeriod      = $adviceData['toPeriod'];
            $bankSetting   = $adviceData['bankSetting'];
            $letterDetails = $adviceData['letterDetails'];

            if (empty($reportData)) {
                return back()->with('error', 'No finalized net pay amounts found for the selected criteria.');
            }

            $exportFormat = strtolower($request->input('export_format', 'pdf'));
            $bankName = $letterDetails['bank_name'] ?? 'Bank';
            $filenameBase = "Bank_Advice_{$bankName}_{$fromPeriod->period_name}_to_{$toPeriod->period_name}";
            $safeFilename = $this->sanitizeFilename($filenameBase);

            if ($exportFormat === 'xlsx') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\BankAdviceExport($reportData, $fromPeriod, $toPeriod, $bankSetting, $company),
                    $safeFilename . '.xlsx'
                );
            }

            if ($exportFormat === 'csv') {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\BankAdviceExport($reportData, $fromPeriod, $toPeriod, $bankSetting, $company),
                    $safeFilename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }

            $period = $fromPeriod;

            if ($exportFormat === 'html') {
                return view('admin.reports.bank-report', compact(
                    'company', 'period', 'fromPeriod', 'toPeriod', 'periods',
                    'reportData', 'letterDetails'
                ));
            }

            $pdf = app('dompdf.wrapper')
                ->loadView('admin.reports.bank-report', compact(
                    'company', 'period', 'fromPeriod', 'toPeriod', 'periods',
                    'reportData', 'letterDetails'
                ))
                ->setPaper('letter', 'portrait');

            return $pdf->stream($safeFilename . '.pdf');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Payroll period not found.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Bank Report failed: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return back()->with('error', 'Failed to generate bank report: ' . $e->getMessage());
        }
    }

    /**
     * Report #9: Single-Period Deduction History Report
     * Itemizes deduction categories for a single specific payroll period.
     */
}
