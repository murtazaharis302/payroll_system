<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DeductionLedgerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle, WithStyles
{
    protected array $reportData;
    protected string $deductionLabel;
    protected $fromPeriod;
    protected $toPeriod;
    protected $company;

    public function __construct(array $reportData, string $deductionLabel, $fromPeriod = null, $toPeriod = null, $company = null)
    {
        $this->reportData = $reportData;
        $this->deductionLabel = $deductionLabel;
        $this->fromPeriod = $fromPeriod;
        $this->toPeriod = $toPeriod;
        $this->company = $company;
    }

    public function collection()
    {
        $flattened = [];
        static $seq = 0;

        foreach ($this->reportData as $empGroup) {
            $seq++;
            $empCode = $empGroup['employee_code'];
            $empName = $empGroup['name'];
            $dept = $empGroup['department'];

            foreach ($empGroup['rows'] as $r) {
                $flattened[] = [
                    'seq'             => $seq,
                    'employee_code'   => $empCode,
                    'name'            => $empName,
                    'department'      => $dept,
                    'deduction_type'  => $this->deductionLabel,
                    'period_name'     => $r['period_name'],
                    'period_range'    => $r['period_range'],
                    'amount'          => $r['amount'],
                    'row_type'        => 'detail',
                ];
            }

            // Employee Sub-total row
            $flattened[] = [
                'seq'             => $seq,
                'employee_code'   => $empCode,
                'name'            => $empName . ' (TOTAL)',
                'department'      => $dept,
                'deduction_type'  => $this->deductionLabel,
                'period_name'     => 'SUMMARY TOTAL',
                'period_range'    => 'ACCUMULATED LEDGER TOTAL',
                'amount'          => $empGroup['employee_total'],
                'row_type'        => 'subtotal',
            ];
        }

        return collect($flattened);
    }

    public function map($row): array
    {
        return [
            $row['seq'],
            $row['employee_code'],
            $row['name'],
            $row['department'],
            $row['deduction_type'],
            $row['period_name'],
            $row['period_range'],
            number_format((float) $row['amount'], 2, '.', ''),
        ];
    }

    public function headings(): array
    {
        return [
            'No.',
            'Employee Code',
            'Employee Name',
            'Department',
            'Deduction Item',
            'Payroll Period Name',
            'Period Cutoff Range',
            'Amount Deducted (PHP)',
        ];
    }

    public function title(): string
    {
        return 'Deduction Ledger';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('H')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E8449'],
                ],
            ],
        ];
    }
}
