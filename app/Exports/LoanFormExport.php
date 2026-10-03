<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\EmpLoan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LoanFormExport implements FromArray, WithStyles, WithTitle
{
    protected EmpLoan $loan;

    public function __construct(EmpLoan $loan)
    {
        $this->loan = $loan;
    }

    public function array(): array
    {
        $companyName = $this->loan->employee?->company?->name ?? 'Company Payroll';
        $refNo = 'LN-' . str_pad((string) $this->loan->id, 5, '0', STR_PAD_LEFT);
        $docDate = $this->loan->start_date
            ? \Carbon\Carbon::parse($this->loan->start_date)->format('M d, Y')
            : date('M d, Y');
        $loanDate = $this->loan->start_date
            ? \Carbon\Carbon::parse($this->loan->start_date)->format('F d, Y')
            : '—';
        $empName = $this->loan->employee?->full_name ?? '—';
        $empCode = $this->loan->employee?->employee_code ?? '—';
        $purpose = $this->loan->purpose ?: ($this->loan->loanType?->name ?? 'Cash Advance');
        $termsMonths = (int) ($this->loan->terms_months ?? 1);
        $principal = (float) $this->loan->principal_amount;
        $total = (float) $this->loan->total_amount;
        $cutoff = (float) $this->loan->per_cutoff_amount;

        $promiseText = "For value received ₱" . number_format($principal, 2) . ", I, " . $empName .
            ", promise to pay every payday the amount of ₱" . number_format($cutoff, 2) .
            " twice a month until the loan is settled. Any default in payment will cause an additional penalty charge of 1% per month on the principal amount.";

        return [
            [$companyName, ''],
            ['CASH LOAN APPLICATION & SUMMARY', ''],
            ['Reference: ' . $refNo, 'Date: ' . $docDate],
            ['', ''],
            ['PART 1: LOAN DETAILS & SUMMARY', ''],
            ['Borrower Employee', $empName . ' (' . $empCode . ')'],
            ['Loan Type / Purpose', $purpose],
            ['Loan Date', $loanDate],
            ['Principal Amount', '₱' . number_format($principal, 2)],
            ['Loan Term', $termsMonths . ' Month(s) (' . ($termsMonths * 2) . ' Paydays)'],
            ['Total Loan Amount', '₱' . number_format($total, 2)],
            ['Deduction Per Payday (Cutoff)', '₱' . number_format($cutoff, 2)],
            ['', ''],
            ['PART 2: PROMISSORY NOTE & AGREEMENT', ''],
            ['PROMISE TO PAY', ''],
            [$promiseText, ''],
            ['', ''],
            ['Prepared by', 'Signature'],
            ['_______________________', '_______________________'],
            ['', ''],
            ['Approved by', 'Checked by'],
            ['_______________________', '_______________________'],
        ];
    }

    public function title(): string
    {
        return 'Loan Form LN-' . str_pad((string) $this->loan->id, 5, '0', STR_PAD_LEFT);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(48);

        // Header styles
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1A5276');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('4A5568');
        $sheet->getStyle('A3:B3')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('4A5568');

        // Section headers
        $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('1A5276');
        $sheet->getStyle('A14')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('1A5276');
        $sheet->getStyle('A15')->getFont()->setBold(true)->setSize(9.5)->getColor()->setRGB('1A5276');

        // Part 1 table rows borders & styles
        $sheet->getStyle('A6:A12')->getFont()->setBold(true)->setSize(9.5);
        $sheet->getStyle('B6:B12')->getFont()->setSize(9.5);
        $sheet->getStyle('A6:B12')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Promissory text wrapping
        $sheet->getStyle('A16')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(16)->setRowHeight(40);
        $sheet->mergeCells('A16:B16');

        // Signature headers
        $sheet->getStyle('A18:B18')->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('4A5568');
        $sheet->getStyle('A21:B21')->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('4A5568');

        return [];
    }
}
