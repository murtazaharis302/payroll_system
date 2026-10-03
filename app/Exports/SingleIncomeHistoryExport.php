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

class SingleIncomeHistoryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle, WithStyles
{
    protected array $reportData;
    protected array $selectedIncomes;
    protected array $incomeLabels;
    protected $period;
    protected $company;
    protected int $rowIndex = 0;

    public function __construct(array $reportData, array $selectedIncomes, array $incomeLabels, $period = null, $company = null)
    {
        $this->reportData = $reportData;
        $this->selectedIncomes = $selectedIncomes;
        $this->incomeLabels = $incomeLabels;
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

        $incomes = $row['incomes'] ?? [];
        foreach ($this->selectedIncomes as $key) {
            $mapped[] = number_format((float) ($incomes[$key] ?? 0), 2, '.', '');
        }

        $mapped[] = number_format((float) ($row['total_income'] ?? 0), 2, '.', '');

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

        foreach ($this->selectedIncomes as $key) {
            $headings[] = $this->incomeLabels[$key] ?? ucwords(str_replace('_', ' ', (string) $key));
        }

        $headings[] = 'TOTAL INCOME';

        return $headings;
    }

    public function title(): string
    {
        return 'Income History';
    }

    public function styles(Worksheet $sheet)
    {
        $lastColIndex = 4 + count($this->selectedIncomes) + 1;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);

        // Format numeric income columns as 2-decimal numbers
        for ($col = 5; $col <= $lastColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle($colLetter)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
        }

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => '1E8449'], // Deep green header
                ],
            ],
        ];
    }
}
