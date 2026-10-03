<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecurringIncomeSummaryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $reportData;
    protected $periods;
    protected array $selectedIncomes;
    protected array $incomeLabels;
    protected int $rowIndex = 0;

    public function __construct(
        array $reportData,
        $periods,
        array $selectedIncomes,
        array $incomeLabels
    ) {
        $this->reportData     = $reportData;
        $this->periods        = $periods;
        $this->selectedIncomes = $selectedIncomes;
        $this->incomeLabels   = $incomeLabels;
    }

    public function collection()
    {
        return collect($this->reportData);
    }

    public function headings(): array
    {
        $headings = ['No.', 'Employee ID', 'Employee Name', 'Department'];

        foreach ($this->periods as $period) {
            $dateStr = \Carbon\Carbon::parse($period->pay_date ?? $period->period_end ?? $period->period_start)
                ->format('m/d/Y');
            $headings[] = $dateStr;
        }

        $headings[] = 'GRAND TOTAL';

        return $headings;
    }

    public function map($row): array
    {
        $this->rowIndex++;

        $employee = $row['employee'];

        $mappedRow = [
            $this->rowIndex,
            $employee->employee_code ?? ('EMP-' . $employee->id),
            $employee->full_name ?? ($employee->last_name . ', ' . $employee->first_name),
            $employee->dept->name ?? $employee->department->name ?? 'N/A',
        ];

        foreach ($this->periods as $period) {
            $sum = $row['period_sums'][$period->id] ?? 0;
            $mappedRow[] = number_format((float) $sum, 2, '.', '');
        }

        $mappedRow[] = number_format((float) ($row['row_total'] ?? 0), 2, '.', '');

        return $mappedRow;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '065F46'],
                ],
            ],
        ];
    }
}
