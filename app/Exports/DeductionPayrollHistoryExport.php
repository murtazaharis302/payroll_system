<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class DeductionPayrollHistoryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $reportData;
    protected $periods;
    protected array $selectedDeductions;
    protected array $deductionLabels;
    protected string $displayMode;
    protected int $rowIndex = 0;

    public function __construct(
        array $reportData,
        $periods,
        array $selectedDeductions,
        array $deductionLabels,
        string $displayMode = 'rate_and_number'
    ) {
        $this->reportData = $reportData;
        $this->periods = $periods;
        $this->selectedDeductions = $selectedDeductions;
        $this->deductionLabels = $deductionLabels;
        $this->displayMode = $displayMode;
    }

    public function collection()
    {
        return collect($this->reportData);
    }

    public function headings(): array
    {
        $headings = [
            'No.',
            'Employee ID',
            'Employee Name',
            'Department',
        ];

        foreach ($this->periods as $period) {
            $dateStr = \Carbon\Carbon::parse($period->period_end ?? $period->period_start)->format('m/d/Y');
            foreach ($this->selectedDeductions as $col) {
                $label = $this->deductionLabels[$col] ?? (is_numeric($col) ? 'Deduction #' . $col : ucfirst(str_replace('_', ' ', $col)));
                $headings[] = $dateStr . ' (' . $label . ')';
            }
        }

        $headings[] = 'TOTAL DEDUCTIONS';

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
            $pv = $row['periods'][$period->id] ?? [];
            foreach ($this->selectedDeductions as $col) {
                $val = $pv[$col] ?? 0;
                $mappedRow[] = number_format((float) $val, 2, '.', '');
            }
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
                    'startColor' => ['rgb' => '8B0000'], // Dark Red Header for Deductions
                ],
            ],
        ];
    }
}
