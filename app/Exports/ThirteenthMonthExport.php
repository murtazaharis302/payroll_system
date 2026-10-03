<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\EmployeeMaster;
use App\Services\ThirteenthMonthService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ThirteenthMonthExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $reportData;
    protected $fromPeriod;
    protected $toPeriod;
    protected $periods;
    protected int $rowIndex = 0;

    public function __construct(array $reportData, $fromPeriod = null, $toPeriod = null, $periods = null)
    {
        $this->reportData = $reportData;
        $this->fromPeriod = $fromPeriod;
        $this->toPeriod = $toPeriod;
        $this->periods = $periods ?? collect();
    }

    public static function forCompany(?int $companyId, int $year): static
    {
        $service = new ThirteenthMonthService();
        $targetCompanyId = $companyId ?? auth()->user()?->company_id ?? session('company_id');

        $query = EmployeeMaster::where('status', 'active');
        if ($targetCompanyId !== null) {
            $query->where('company_id', $targetCompanyId);
        }
        $employees = $query->get();

        $rows = [];
        foreach ($employees as $employee) {
            $rows[] = $service->computeThirteenthMonthPay($employee, $year);
        }

        return new static($rows);
    }

    public function collection()
    {
        return collect($this->reportData);
    }

    public function headings(): array
    {
        $headings = [
            'No.',
            'Employee Code',
            'Employee Name',
            'Department',
            'Periods Worked',
            'Total Basic Pay',
            'Total Overtime',
            'Total Gross Income',
            '13th Month Pay (Basic ÷ 12)',
        ];

        return $headings;
    }

    public function map($row): array
    {
        $this->rowIndex++;

        $employee = $row['employee'] ?? null;

        $empCode = $row['employee_code'] ?? ($employee->employee_code ?? ('EMP-' . ($employee->id ?? '')));
        $empName = $row['employee_name'] ?? ($employee->full_name ?? '');
        $deptName = $row['department_name'] ?? ($employee->dept->name ?? $employee->department->name ?? 'N/A');

        return [
            $this->rowIndex,
            $empCode,
            $empName,
            $deptName,
            $row['periods_worked'] ?? ($row['months_eligible'] ?? 12),
            number_format((float) ($row['total_basic_pay'] ?? ($row['total_ytd_basic_pay'] ?? 0)), 2, '.', ''),
            number_format((float) ($row['total_overtime'] ?? 0), 2, '.', ''),
            number_format((float) ($row['total_gross'] ?? 0), 2, '.', ''),
            number_format((float) ($row['thirteenth_month'] ?? ($row['thirteenth_month_pay'] ?? 0)), 2, '.', ''),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1A5276'], // Corporate navy header
                ],
            ],
        ];
    }
}
