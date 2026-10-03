<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BankAdviceExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithTitle, WithStyles, WithEvents
{
    protected array $reportData;
    protected $fromPeriod;
    protected $toPeriod;
    protected $bankSetting;
    protected $company;

    protected int $rowIndex = 0;

    public function __construct(array $reportData, $fromPeriod = null, $toPeriod = null, $bankSetting = null, $company = null)
    {
        $this->reportData = $reportData;
        $this->fromPeriod = $fromPeriod;
        $this->toPeriod = $toPeriod;
        $this->bankSetting = $bankSetting;
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
        $lastName = $row['last_name'] ?? ($employee->last_name ?? '');
        $firstName = $row['first_name'] ?? ($employee->first_name ?? '');
        $middleName = $row['middle_name'] ?? ($employee->middle_name ?? '');
        $empCode = $row['employee_code'] ?? ($employee->employee_code ?? ('EMP-' . ($employee->id ?? '')));
        $atmNum = $row['atm_num'] ?? ($employee->atm_num ?? '—');
        $acctNum = $row['acct_num'] ?? ($employee->acct_num ?? '—');
        $netPay = (float) ($row['net_pay'] ?? 0);

        return [
            $this->rowIndex,
            $lastName,
            $firstName,
            $middleName,
            $empCode,
            $atmNum,
            $acctNum,
            number_format($netPay, 2, '.', ''),
        ];
    }

    public function headings(): array
    {
        return [
            'No.',
            'Last Name',
            'First Name',
            'Middle Name',
            'Employee Code',
            'ATM Card Number',
            'Account Number',
            'Net Pay Amount (PHP)',
        ];
    }

    public function title(): string
    {
        return 'Bank Advice';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 18,
            'C' => 18,
            'D' => 18,
            'E' => 16,
            'F' => 22,
            'G' => 24,
            'H' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('F')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('G')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('H')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => '1A5276'], // Deep navy corporate header
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $bankName = $this->bankSetting?->bank_name ?? 'PARTNER BANK';
                $event->sheet->insertNewRowBefore(1, 2);
                $event->sheet->setCellValue('A1', 'BANK ADVICE DIRECT DEPOSIT TRANSMITTAL – ' . strtoupper($bankName));
                $event->sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 13],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $event->sheet->mergeCells('A1:H1');

                $fromName = $this->fromPeriod ? $this->fromPeriod->period_name : '';
                $toName   = $this->toPeriod ? $this->toPeriod->period_name : '';
                $event->sheet->setCellValue('A2', "Period Range: {$fromName} to {$toName}");
                $event->sheet->getStyle('A2')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $event->sheet->mergeCells('A2:H2');

                $event->sheet->freezePane('A4');
            },
        ];
    }
}
