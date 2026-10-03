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

class SingleDeductionHistoryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle, WithStyles
{
    protected array $reportData;
    protected array $selectedDeductions;
    protected array $deductionLabels;
    protected $period;
    protected $company;
    protected int $rowIndex = 0;

    public function __construct(array $reportData, array $selectedDeductions, array $deductionLabels, $period = null, $company = null)
    {
        $this->reportData = $reportData;
        $this->selectedDeductions = $selectedDeductions;
        $this->deductionLabels = $deductionLabels;
        $this->period = $period;
        $this->company = $company;
    }

    public function collection()
    {
        return collect($this->reportData);
    }

    public function map($row): array
    {
        $this->rowIndex++;

        $employee = $row['employee'] ?? null;
        $empCode = $row['employee_code'] ?? ($employee->employee_code ?? ('EMP-' . ($employee->id ?? '')));
        $empName = $row['name'] ?? ($employee ? ($employee->last_name . ', ' . $employee->first_name) : '');
        $deptName = $row['department'] ?? ($employee->dept->name ?? '—');

        $mapped = [
            $this->rowIndex,
            $empCode,
            $empName,
            $deptName,
        ];

        $deductions = $row['deductions'] ?? [];
        foreach ($this->selectedDeductions as $key) {
            $mapped[] = number_format((float) ($deductions[$key] ?? 0), 2, '.', '');
        }

        $mapped[] = number_format((float) ($row['total_deductions'] ?? 0), 2, '.', '');

        return $mapped;
    }

    public function headings(): array
    {
        $headings = [
            'No.',
            'Employee Code',
            'Employee Name',
            'Department',
        ];

        foreach ($this->selectedDeductions as $key) {
            $headings[] = $this->deductionLabels[$key] ?? ucwords(str_replace('_', ' ', (string) $key));
        }

        $headings[] = 'TOTAL DEDUCTION';

        return $headings;
    }

    public function title(): string
    {
        return 'Deduction History';
    }

    public function styles(Worksheet $sheet)
    {
        $lastColIndex = 4 + count($this->selectedDeductions) + 1;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);

        // Format numeric deduction columns as 2-decimal numbers
        for ($col = 5; $col <= $lastColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle($colLetter)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
        }

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => '78281F'], // Dark burgundy header
                ],
            ],
        ];
    }
}
