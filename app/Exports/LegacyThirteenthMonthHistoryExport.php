<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class LegacyThirteenthMonthHistoryExport implements FromCollection, WithHeadings, WithStyles
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function collection()
    {
        $rows = [];
        foreach ($this->reportData['rows'] ?? [] as $row) {
            $mapped = [
                'NAME' => $row['employee_name'] ?? '—',
            ];

            foreach ($this->reportData['cutoffs'] ?? [] as $pId => $cData) {
                $val = (float) ($row['cutoff_values'][$pId] ?? 0.00);
                $mapped[$cData['short_label']] = $val;
            }

            $mapped['TOTAL'] = (float) ($row['total_basic'] ?? 0.00);
            $mapped['13TH MONTH PAY'] = (float) ($row['thirteenth_month_pay'] ?? 0.00);

            $rows[] = $mapped;
        }

        return collect($rows);
    }

    public function headings(): array
    {
        $headings = ['NAME'];
        foreach ($this->reportData['cutoffs'] ?? [] as $cData) {
            $headings[] = $cData['short_label'];
        }
        $headings[] = 'TOTAL';
        $headings[] = '13TH MONTH PAY';

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        $numCutoffs = count($this->reportData['cutoffs'] ?? []);
        $lastColIndex = 1 + $numCutoffs + 2; // NAME + cutoffs + TOTAL + 13TH MONTH PAY
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);
        $totalRowsCount = count($this->reportData['rows'] ?? []);
        $lastRowIndex = $totalRowsCount > 0 ? $totalRowsCount + 1 : 1;

        // Apply borders and font to header
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        if ($totalRowsCount > 0) {
            // Style all matrix data cells
            $sheet->getStyle("A1:{$lastColLetter}{$lastRowIndex}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);

            // Column widths & number formats
            $sheet->getColumnDimension('A')->setWidth(32);
            for ($i = 2; $i <= $lastColIndex; $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($colLetter)->setWidth(14);
                // Right-align and format numbers with #,##0.00
                $sheet->getStyle("{$colLetter}2:{$colLetter}{$lastRowIndex}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                $sheet->getStyle("{$colLetter}2:{$colLetter}{$lastRowIndex}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        return [];
    }
}

