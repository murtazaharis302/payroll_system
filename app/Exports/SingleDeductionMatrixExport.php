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
use Carbon\Carbon;

class SingleDeductionMatrixExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle, WithStyles
{
    protected array $reportData;
    protected $periods;
    protected string $deductionLabel;
    protected $fromPeriod;
    protected $toPeriod;
    protected $company;
    protected int $rowIndex = 0;

    public function __construct(array $reportData, $periods, string $deductionLabel, $fromPeriod = null, $toPeriod = null, $company = null)
    {
        $this->reportData = $reportData;
        $this->periods = $periods;
        $this->deductionLabel = $deductionLabel;
        $this->fromPeriod = $fromPeriod;
        $this->toPeriod = $toPeriod;
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
        $empCode  = $row['employee_code'] ?? ($employee->employee_code ?? ('EMP' . ($employee->id ?? '')));
        $empName  = $row['name'] ?? ($employee ? ($employee->last_name . ', ' . $employee->first_name) : '');
        $deptName = $row['department'] ?? ($employee->dept->name ?? '—');

        $mapped = [
            $this->rowIndex,
            $empCode,
            $empName,
            $deptName,
        ];

        $amounts = $row['amounts'] ?? [];
        foreach ($this->periods as $p) {
            $val = (float) ($amounts[$p->id] ?? 0);
            $mapped[] = number_format($val, 2, '.', '');
        }

        $mapped[] = number_format((float) ($row['row_total'] ?? 0), 2, '.', '');

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

        foreach ($this->periods as $p) {
            $headings[] = Carbon::parse($p->period_end)->format('m/d/Y');
        }

        $headings[] = 'TOTAL DEDUCTED';

        return $headings;
    }

    public function title(): string
    {
        return 'Single Deduction Matrix';
    }

    public function styles(Worksheet $sheet)
    {
        $periodCount = count($this->periods);
        $lastColIndex = 4 + $periodCount + 1;

        // Format numeric deduction columns as 2-decimal numbers
        for ($col = 5; $col <= $lastColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle($colLetter)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
        }

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7D3C98'],
                ],
            ],
        ];
    }
}
