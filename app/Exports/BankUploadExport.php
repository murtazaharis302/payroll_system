<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Direct Deposit Bank Upload CSV/Excel Export Class
 *
 * Strictly maps output to 5-column bank template:
 * Column A: Last Name
 * Column B: First Name
 * Column C: Middle Name
 * Column D: Employee Account Number
 * Column E: Amount (Total Net Pay across selected period range)
 */
class BankUploadExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithTitle, WithStyles
{
    protected $fromPeriodId;
    protected $toPeriodId;
    protected $companyId;
    protected $departmentId;
    protected $precomputedData;

    public function __construct($fromPeriodId, $toPeriodId = null, $companyId = null, $departmentId = null)
    {
        if (is_array($fromPeriodId) || $fromPeriodId instanceof \Illuminate\Support\Collection) {
            $this->precomputedData = collect($fromPeriodId);
        } else {
            $this->fromPeriodId = $fromPeriodId;
            $this->toPeriodId = $toPeriodId ?? $fromPeriodId;
            $this->companyId = $companyId;
            $this->departmentId = $departmentId;
        }
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        if ($this->precomputedData) {
            return $this->precomputedData;
        }

        $fromPeriod = PayrollPeriod::find($this->fromPeriodId);
        $toPeriod   = PayrollPeriod::find($this->toPeriodId ?? $this->fromPeriodId);

        if (!$fromPeriod) {
            return collect();
        }

        $companyId = $this->companyId ?? $fromPeriod->company_id ?? auth()->user()?->company_id;

        $periods = PayrollPeriod::where('period_start', '>=', $fromPeriod->period_start)
            ->where('period_start', '<=', ($toPeriod ? $toPeriod->period_start : $fromPeriod->period_start))
            ->where('company_id', $companyId)
            ->pluck('id');

        $query = PayHeader::with('employee')
            ->whereIn('payroll_period_id', $periods)
            ->whereIn('status', PayHeader::FINAL_REPORT_STATUSES);

        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        if ($this->departmentId && $this->departmentId !== 'all') {
            $query->whereHas('employee', function ($q) {
                $q->where('dept_id', $this->departmentId);
            });
        }

        $transactions = $query->get();

        // Group transactions by employee and aggregate net pay across selected period range
        $empGrouped = $transactions->groupBy('employee_id');
        $rows = collect();

        foreach ($empGrouped as $employeeId => $txList) {
            $emp = $txList->first()->employee;
            if (!$emp) continue;

            $netPayTotal = (float) $txList->sum('net_pay');

            $rows->push([
                'employee'     => $emp,
                'last_name'    => $emp->last_name,
                'first_name'   => $emp->first_name,
                'middle_name'  => $emp->middle_name,
                'acct_num'     => $emp->acct_num ?? $emp->bank_account_number ?? $emp->account_number ?? $emp->atm_num ?? '',
                'net_pay'      => $netPayTotal,
            ]);
        }

        return $rows;
    }

    /**
     * Map each row strictly to the 5-column bank template.
     *
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        if (is_array($row)) {
            $emp = $row['employee'] ?? null;
            $lastName   = $row['last_name'] ?? ($emp->last_name ?? '');
            $firstName  = $row['first_name'] ?? ($emp->first_name ?? '');
            $middleName = $row['middle_name'] ?? ($emp->middle_name ?? '');
            $acctNum    = $row['acct_num'] ?? ($emp->acct_num ?? $emp->bank_account_number ?? $emp->account_number ?? $emp->atm_num ?? '');
            $netPay     = (float) ($row['net_pay'] ?? 0);
        } else {
            $emp = $row->employee ?? null;
            $lastName   = $emp ? ($emp->last_name ?? '') : '';
            $firstName  = $emp ? ($emp->first_name ?? '') : '';
            $middleName = $emp ? ($emp->middle_name ?? '') : '';
            $acctNum    = $emp ? trim((string) ($emp->acct_num ?? $emp->bank_account_number ?? $emp->account_number ?? $emp->atm_num ?? '')) : '';
            $netPay     = (float) ($row->net_pay ?? 0);
        }

        return [
            $lastName,
            $firstName,
            $middleName,
            $acctNum,
            number_format($netPay, 2, '.', ''),
        ];
    }

    public function headings(): array
    {
        return [
            'Last Name',
            'First Name',
            'Middle Name',
            'Employee Account Number',
            'Amount',
        ];
    }

    public function title(): string
    {
        return 'Bank Direct Deposit Upload';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 20,
            'C' => 20,
            'D' => 25,
            'E' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => 'BDD7EE'],
                ],
            ],
        ];
    }
}
