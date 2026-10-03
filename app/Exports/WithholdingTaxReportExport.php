<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PayHeader;
use App\Models\PayrollPeriod;
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

/**
 * Withholding Tax Report Export (BIR Form 1601-C support)
 *
 * Source: pay_headers -> pay_outs (with deductionType) for the target month.
 * Aggregates non-taxable statutory deductions (SSS, PhilHealth, Pag-IBIG)
 * alongside taxable income and withholding tax withheld per employee.
 */
class WithholdingTaxReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnWidths, WithEvents, WithColumnFormatting
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

        // --- Load all approved/posted/paid pay_headers in month ---
        $query = PayHeader::with(['employee', 'payOuts.deductionType', 'payrollPeriod'])
            ->whereIn('status', PayHeader::FINAL_REPORT_STATUSES)
            ->whereHas('payrollPeriod', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('period_start', [$startDate, $endDate]);
            });

        if ($companyId !== null) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $headers = $query->get();

        // Group by employee
        $grouped = $headers->groupBy('employee_id')->map(function ($items) {
            $first    = $items->first();
            $employee = $first->employee;

            $totalGross           = (float) $items->sum('total_gross_pay');
            $totalTax             = 0.00;
            $totalStatutoryNonTax = 0.00;

            foreach ($items as $header) {
                foreach ($header->payOuts as $payOut) {
                    $sysKey = $payOut->deductionType->system_key ?? null;
                    $amount = (float) $payOut->amount;

                    if ($sysKey === 'tax') {
                        $totalTax += $amount;
                    } elseif (in_array($sysKey, ['sss', 'philhealth', 'pagibig'], true)) {
                        $totalStatutoryNonTax += $amount;
                    }
                }
            }

            // Taxable income = Gross Pay minus non-taxable statutory deductions
            $taxableIncome = max(0.00, $totalGross - $totalStatutoryNonTax);

            return [
                'no'               => 0,
                'employee_number'  => $employee ? ($employee->employee_code ?? 'N/A') : 'N/A',
                'full_name'        => $employee
                    ? trim(($employee->last_name  ?? '') . ', ' .
                           ($employee->first_name ?? '') . ' ' .
                           ($employee->middle_name ? substr($employee->middle_name, 0, 1) . '.' : ''))
                    : '',
                'tin_number'       => $employee ? ($employee->tin_number ?? '') : '',
                'gross_income'     => (float) round($totalGross, 2),
                'total_deductions' => (float) round($totalStatutoryNonTax, 2),
                'taxable_income'   => (float) round($taxableIncome, 2),
                'withholding_tax'  => (float) round($totalTax, 2),
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
            'TIN',
            'Gross Income',
            'Non-Taxable Deductions',
            'Taxable Income',
            'Withholding Tax',
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
        return 'Withholding Tax Report';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 15,
            'C' => 32,
            'D' => 18,
            'E' => 15,
            'F' => 22,
            'G' => 15,
            'H' => 18,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->insertNewRowBefore(1, 2);
                $event->sheet->setCellValue('A1', 'WITHHOLDING TAX REPORT (BIR Form 1601-C)');
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
}
