<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Services\Reports\PayrollSummaryReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollMasterSheetExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithTitle, WithStyles, WithEvents
{
    protected $payrollPeriodId;
    protected $companyId;
    protected $departmentId;
    protected array $summaryData;

    public function __construct($payrollPeriodId, $companyId = null, $departmentId = null)
    {
        $this->payrollPeriodId = $payrollPeriodId;
        $this->companyId = $companyId ?? PayrollPeriod::find($payrollPeriodId)?->company_id ?? auth()->user()?->company_id;
        $this->departmentId = ($departmentId === 'all' || empty($departmentId)) ? null : $departmentId;

        $period = PayrollPeriod::find($payrollPeriodId);
        $company = $period?->company ?? Company::find($this->companyId);

        $summaryService = app(PayrollSummaryReportService::class);
        $this->summaryData = $summaryService->build($period, $company, $this->companyId, $this->departmentId, 'legal');
    }

    public function collection()
    {
        return $this->summaryData['payrollRows'];
    }

    public function map($row): array
    {
        $employee = $row['employee'];

        $rowData = [
            $employee ? ($employee->employee_code ?? '') : '',
            $employee ? ($employee->full_name ?? '') : '',
            $employee && $employee->dept ? ($employee->dept->name ?? 'N/A') : 'N/A',
            number_format((float) $row['regular_rate'], 2, '.', ''),
            number_format((float) $row['basic_qty'], 1, '.', ''),
            number_format((float) $row['basic_amount'], 2, '.', ''),
            number_format((float) $row['ot_qty'], 1, '.', ''),
            number_format((float) $row['ot_amount'], 2, '.', ''),
        ];

        foreach ($this->summaryData['displayedIncomes'] as $incType) {
            $data = $row['displayed_incomes'][$incType->id] ?? ['qty' => 0.00, 'amount' => 0.00];
            $rowData[] = number_format((float) $data['qty'], 1, '.', '');
            $rowData[] = number_format((float) $data['amount'], 2, '.', '');
        }

        if ($this->summaryData['hiddenIncomes']->isNotEmpty()) {
            $rowData[] = number_format((float) $row['other_income_amount'], 2, '.', '');
        }

        $rowData[] = number_format((float) $row['total_gross_pay'], 2, '.', '');

        foreach ($this->summaryData['displayedDeductions'] as $dedType) {
            $amt = $row['displayed_deductions'][$dedType->id] ?? 0.00;
            $rowData[] = number_format((float) $amt, 2, '.', '');
        }

        if ($this->summaryData['hiddenDeductions']->isNotEmpty()) {
            $rowData[] = number_format((float) $row['others_deductions_amount'], 2, '.', '');
        }

        $rowData[] = number_format((float) $row['total_deductions'], 2, '.', '');
        $rowData[] = number_format((float) $row['net_pay'], 2, '.', '');

        return $rowData;
    }

    public function headings(): array
    {
        $headings = [
            'Employee ID',
            'Full Name',
            'Department',
            'Regular Rate',
            'Salary Days',
            'Salary Amount',
            'OT Hours',
            'OT Amount',
        ];

        foreach ($this->summaryData['displayedIncomes'] as $incType) {
            $codeOrName = $incType->code ?: $incType->name;
            $headings[] = $codeOrName . ' Qty';
            $headings[] = $codeOrName . ' Amount';
        }

        if ($this->summaryData['hiddenIncomes']->isNotEmpty()) {
            $headings[] = 'Other Income';
        }

        $headings[] = 'Total Gross Pay';

        foreach ($this->summaryData['displayedDeductions'] as $dedType) {
            $headings[] = $dedType->code ?: $dedType->name;
        }

        if ($this->summaryData['hiddenDeductions']->isNotEmpty()) {
            $headings[] = 'Other Deductions';
        }

        $headings[] = 'Total Deductions';
        $headings[] = 'Net Pay';

        return $headings;
    }

    public function title(): string
    {
        return 'Payroll Master Sheet';
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 15,
            'B' => 26,
            'C' => 20,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 15,
        ];

        $totalCols = count($this->headings());
        for ($i = 8; $i < $totalCols; $i++) {
            $colName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $widths[$colName] = 18;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => 'D9EAD3'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->insertNewRowBefore(1, 1);
                $event->sheet->setCellValue('A1', 'PAYROLL MASTER SHEET');
                $event->sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $totalCols = count($this->headings());
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
                $event->sheet->mergeCells('A1:' . $lastColLetter . '1');
                $event->sheet->freezePane('A3');
            },
        ];
    }
}
