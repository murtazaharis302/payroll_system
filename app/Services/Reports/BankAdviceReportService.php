<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\CompanyBankSetting;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Services\NumberToWordsHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class BankAdviceReportService
{
    /**
     * Build unified Bank Advice data array for PDF and Excel exports.
     *
     * @param PayrollPeriod $fromPeriod
     * @param PayrollPeriod $toPeriod
     * @param Company|null $company
     * @param int|null $companyId
     * @param int|string|null $departmentId
     * @param array $options
     * @return array
     */
    public function build(
        PayrollPeriod $fromPeriod,
        PayrollPeriod $toPeriod,
        ?Company $company = null,
        ?int $companyId = null,
        $departmentId = null,
        array $options = []
    ): array {
        // Ensure chronological ordering
        if ($fromPeriod->period_start > $toPeriod->period_start) {
            $temp = $fromPeriod;
            $fromPeriod = $toPeriod;
            $toPeriod = $temp;
        }

        $companyId = $companyId ?? $fromPeriod->company_id;
        $company = $company ?? Company::find($companyId) ?? $fromPeriod->company ?? Company::first();

        // Periods in range
        $periods = PayrollPeriod::where('period_start', '>=', $fromPeriod->period_start)
            ->where('period_start', '<=', $toPeriod->period_start)
            ->when($companyId !== null, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('period_start')
            ->get();

        $periodIds = $periods->pluck('id')->toArray();

        // Resolve Bank Setting
        $bankSetting = null;
        if (!empty($options['bank_setting_id'])) {
            $settingQuery = CompanyBankSetting::where('id', $options['bank_setting_id']);
            if ($companyId !== null) {
                $settingQuery->where('company_id', $companyId);
            }
            $bankSetting = $settingQuery->first();
        }
        if (!$bankSetting && $companyId) {
            $bankSetting = CompanyBankSetting::where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->first();
        }

        $bankName = $options['bank_name'] ?? ($bankSetting?->bank_name ?: 'Partner Bank');
        $branchName = $options['branch_name'] ?? ($bankSetting?->branch_name ?: 'Main Branch');
        $managerName = $options['manager_name'] ?? ($bankSetting?->manager_name ?: 'The Branch Manager');
        $companyAcct = $options['company_acct'] ?? ($bankSetting?->account_number ?: 'N/A');
        $signatoryName = $options['signatory_name'] ?? ($bankSetting?->corporate_signee_1 ?: 'Authorized Signatory');
        $signatoryPosition = $options['signatory_position'] ?? ($bankSetting?->corporate_signee_1_title ?: 'Payroll Manager');
        $signatory2Name = $options['signatory_2_name'] ?? ($bankSetting?->corporate_signee_2 ?: '');
        $signatory2Position = $options['signatory_2_position'] ?? ($bankSetting?->corporate_signee_2_title ?: '');

        // Query finalized pay headers strictly
        $query = PayHeader::with(['employee.dept'])
            ->whereIn('payroll_period_id', $periodIds)
            ->whereIn('status', PayHeader::FINAL_REPORT_STATUSES);

        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        if ($departmentId && $departmentId !== 'all') {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('dept_id', $departmentId);
            });
        }

        $transactions = $query->get();

        $empTransactions = $transactions->groupBy('employee_id');
        $reportData = [];
        $totalNetPay = 0.0;

        foreach ($empTransactions as $employeeId => $txList) {
            $emp = $txList->first()->employee;
            if (!$emp) {
                continue;
            }

            $netPaySum = (float) $txList->sum('net_pay');
            if ($netPaySum <= 0) {
                continue;
            }

            $totalNetPay += $netPaySum;

            $reportData[] = [
                'employee'      => $emp,
                'last_name'     => $emp->last_name,
                'first_name'    => $emp->first_name,
                'middle_name'   => $emp->middle_name,
                'name'          => $emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . substr($emp->middle_name, 0, 1) . '.' : ''),
                'employee_code' => $emp->employee_code ?? 'EMP' . $emp->id,
                'acct_num'      => $emp->acct_num ?? '—',
                'atm_num'       => $emp->atm_num ?? '—',
                'net_pay'       => $netPaySum,
            ];
        }

        $amountInWords = NumberToWordsHelper::formatPesos($totalNetPay);

        $letterDetails = [
            'bank_name'           => $bankName,
            'branch_name'         => $branchName,
            'manager_name'        => $managerName,
            'company_acct'        => $companyAcct,
            'signatory_name'      => $signatoryName,
            'signatory_position'  => $signatoryPosition,
            'signatory_2_name'    => $signatory2Name,
            'signatory_2_position'=> $signatory2Position,
            'date'                => Carbon::now()->format('F d, Y'),
            'total_net_pay'       => $totalNetPay,
            'amount_in_words'     => $amountInWords,
        ];

        return [
            'fromPeriod'    => $fromPeriod,
            'toPeriod'      => $toPeriod,
            'periods'       => $periods,
            'company'       => $company,
            'bankSetting'   => $bankSetting,
            'reportData'    => $reportData,
            'totalNetPay'   => $totalNetPay,
            'letterDetails' => $letterDetails,
        ];
    }
}
