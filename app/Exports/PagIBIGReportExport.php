<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GovernmentContribution;
use App\Models\PayHeader;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pag-IBIG Contribution Report Export
 *
 * Source: government_contributions (type='pagibig') + pay_headers for gross income.
 * Extracts EE and ER Pag-IBIG contribution totals per employee for the target month.
 */
class PagIBIGReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnWidths, WithEvents, WithColumnFormatting
{
    protected $companyId;
    protected $fromMonth;
    protected $toMonth;

    public function __construct($companyId, $fromMonth, $toMonth = null)
    {
        $this->companyId = $companyId;
        $this->fromMonth = $fromMonth;
        $this->toMonth   = $toMonth ?? $fromMonth;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $startDate = Carbon::createFromFormat('Y-m', $this->fromMonth)->startOfMonth();
        $endDate   = Carbon::createFromFormat('Y-m', $this->toMonth)->endOfMonth();

        $companyId = $this->companyId ?? auth()->user()?->company_id ?? session('company_id');

        // 1. Base query on approved/posted/paid PayHeader records for target month
        $payHeaderQuery = PayHeader::with(['employee', 'payOuts.deductionType'])
            ->whereIn('status', PayHeader::FINAL_REPORT_STATUSES)
            ->whereHas('payrollPeriod', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('period_start', [$startDate, $endDate]);
            });

        if ($companyId !== null) {
            $payHeaderQuery->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $payHeaders = $payHeaderQuery->get();
        $finalizedHeaderIds = $payHeaders->pluck('id')->all();

        // 2. Pre-load Pag-IBIG GovernmentContribution records strictly for finalized headers
        $govtContribs = GovernmentContribution::where('type', 'pagibig')
            ->whereIn('payroll_draft_id', $finalizedHeaderIds)
            ->get()
            ->groupBy('payroll_draft_id');

        // 3. Group by employee and aggregate shares
        $grouped = $payHeaders->groupBy('employee_id')->map(function ($headers) use ($govtContribs) {
            $first    = $headers->first();
            $employee = $first->employee;

            $grossPay = (float) $headers->sum('total_gross_pay');
            $totalEE  = 0.00;
            $totalER  = 0.00;

            foreach ($headers as $header) {
                foreach ($header->payOuts as $payOut) {
                    $code = strtoupper($payOut->deductionType->code ?? '');
                    if (in_array($code, ['PAGIBIG', 'HDMF', 'PAG-IBIG'], true)) {
                        $totalEE += (float) ($payOut->rate * ($payOut->number_input ?? 1.00));
                    }
                }

                $contrib = $govtContribs->get($header->id)?->first();
                $totalER += $contrib ? (float) $contrib->employer_share : 0.00;
            }

            return [
                'no'                    => 0,
                'employee_number'       => $employee ? ($employee->employee_code ?? 'N/A') : 'N/A',
                'full_name'             => $employee
                    ? trim(($employee->last_name  ?? '') . ', ' .
                           ($employee->first_name ?? '') . ' ' .
                           ($employee->middle_name ? substr($employee->middle_name, 0, 1) . '.' : ''))
                    : '',
                'pagibig_number'        => $employee ? ($employee->pagibig_number ?? '') : '',
                'gross_income'          => (float) round($grossPay, 2),
                'employee_contribution' => (float) round($totalEE, 2),
                'employer_contribution' => (float) round($totalER, 2),
                'total_contribution'    => (float) round($totalEE + $totalER, 2),
            ];
        });

        return collect($grouped->values())->map(function ($item, $index) {
            $item['no'] = $index + 1;
            return $item;
        });
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
        ];
    }

    public function headings(): array
    {
        return [
            'No.',
            'Employee No.',
            'Employee Name',
            'Pag-IBIG Number',
            'Gross Income',
            'Employee Contribution',
            'Employer Contribution',
            'Total Contribution',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => 'D0D0D0'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Pag-IBIG Report';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 15,
            'C' => 32,
            'D' => 20,
            'E' => 15,
            'F' => 20,
            'G' => 20,
            'H' => 18,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->insertNewRowBefore(1, 2);
                $event->sheet->setCellValue('A1', 'PAG-IBIG CONTRIBUTION REPORT');
                $periodLabel = ($this->fromMonth === $this->toMonth)
                    ? 'Month: ' . Carbon::createFromFormat('Y-m', $this->fromMonth)->format('F Y')
                    : 'Period: ' . Carbon::createFromFormat('Y-m', $this->fromMonth)->format('F Y') . ' - ' . Carbon::createFromFormat('Y-m', $this->toMonth)->format('F Y');
                $event->sheet->setCellValue('A2', $periodLabel);

                $event->sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $event->sheet->mergeCells('A1:H1');

                $event->sheet->getStyle('A2')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $event->sheet->mergeCells('A2:H2');

                // Totals row
                $lastRow = $event->sheet->getHighestRow() + 1;
                $event->sheet->setCellValue('A' . $lastRow, 'TOTAL');
                $event->sheet->mergeCells('A' . $lastRow . ':D' . $lastRow);
                foreach (range('E', 'H') as $col) {
                    $colSum = 0.00;
                    for ($r = 4; $r < $lastRow; $r++) {
                        $rawVal = $event->sheet->getCell($col . $r)->getValue();
                        $numVal = (float) str_replace(',', '', (string) ($rawVal ?? 0));
                        $event->sheet->setCellValueExplicit($col . $r, $numVal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $colSum += $numVal;
                    }
                    $cell = $event->sheet->getCell($col . $lastRow);
                    $cell->setValue('=SUM(' . $col . '4:' . $col . ($lastRow - 1) . ')');
                    $cell->setCalculatedValue(round($colSum, 2));
                }
                $event->sheet->getStyle('E' . $lastRow . ':H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $event->sheet->getStyle('A' . $lastRow . ':H' . $lastRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F0F0F0']],
                ]);
            },
        ];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build employee_id -> total_gross_pay map via direct DB join.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return array<int, float>
     */
    protected function buildGrossPayMap($startDate, $endDate): array
    {
        $companyId = $this->companyId ?? auth()->user()?->company_id ?? session('company_id');

        $rows = DB::table('pay_headers')
            ->join('payroll_periods', 'pay_headers.payroll_period_id', '=', 'payroll_periods.id')
            ->whereIn('pay_headers.status', PayHeader::FINAL_REPORT_STATUSES)
            ->whereBetween('payroll_periods.period_start', [$startDate, $endDate])
            ->when($companyId !== null, function ($q) use ($companyId) {
                $q->join('employees_master', 'pay_headers.employee_id', '=', 'employees_master.id')
                  ->where('employees_master.company_id', $companyId);
            })
            ->select('pay_headers.employee_id', DB::raw('SUM(pay_headers.total_gross_pay) as gross'))
            ->groupBy('pay_headers.employee_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->employee_id] = (float) $row->gross;
        }
        return $map;
    }
}
