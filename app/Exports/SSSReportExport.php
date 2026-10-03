<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GovernmentContribution;
use App\Models\PayHeader;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SSS Contribution Report Export
 *
 * Source: government_contributions (type='sss') + pay_headers for gross income.
 * Aggregates EE share, reconstructed ER share, and EC share per employee for the target month.
 */
class SSSReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnWidths, WithEvents, WithColumnFormatting
{
    protected $companyId;
    protected $fromMonth;
    protected $toMonth;

    public function __construct($companyId, $fromMonth, $toMonth = null)
    {
        $this->companyId = $companyId;
        $this->fromMonth = $fromMonth;
        $this->toMonth   = $toMonth ?? $fromMonth;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $startDate = Carbon::createFromFormat('Y-m', $this->fromMonth)->startOfMonth();
        $endDate   = Carbon::createFromFormat('Y-m', $this->toMonth)->endOfMonth();

        $companyId = $this->companyId ?? auth()->user()?->company_id ?? session('company_id');

        // 1. Base query on approved/posted/paid PayHeader records for target month
        $payHeaderQuery = PayHeader::with(['employee', 'payOuts.deductionType', 'payrollPeriod'])
            ->whereIn('status', PayHeader::FINAL_REPORT_STATUSES)
            ->whereHas('payrollPeriod', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('period_start', [$startDate, $endDate]);
            });

        if ($companyId !== null) {
            $payHeaderQuery->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $payHeaders = $payHeaderQuery->get();
        $finalizedHeaderIds = $payHeaders->pluck('id')->all();

        // 2. Pre-load SSS GovernmentContribution records strictly for finalized headers
        $govtContribs = GovernmentContribution::where('type', 'sss')
            ->whereIn('payroll_draft_id', $finalizedHeaderIds)
            ->get()
            ->groupBy('payroll_draft_id');

        $statutoryService = app(\App\Services\StatutoryCalculationService::class);
        $ruleCache = [];

        // 3. Group by employee and resolve rule-based ER/EC per finalized contribution
        $grouped = $payHeaders->groupBy('employee_id')->map(function ($headers) use ($govtContribs, $statutoryService, &$ruleCache) {
            $first    = $headers->first();
            $employee = $first->employee;

            $grossPay = (float) $headers->sum('total_gross_pay');
            $totalEE  = 0.00;
            $totalER  = 0.00;
            $totalEC  = 0.00;

            foreach ($headers as $header) {
                foreach ($header->payOuts as $payOut) {
                    $sysKey = $payOut->deductionType->system_key ?? null;
                    if ($sysKey === 'sss') {
                        $totalEE += (float) ($payOut->rate * ($payOut->number_input ?? 1.00));
                    }
                }

                $contrib = $govtContribs->get($header->id)?->first();
                $storedErPlusEc = $contrib ? (float) $contrib->employer_share : 0.00;

                if ($storedErPlusEc > 0.00) {
                    $period = $header->payrollPeriod;
                    $payDate = $period?->pay_date
                        ? Carbon::parse($period->pay_date)->toDateString()
                        : Carbon::parse($period?->period_end ?? $period?->period_start ?? now())->toDateString();

                    if (!array_key_exists($payDate, $ruleCache)) {
                        try {
                            $ruleCache[$payDate] = $statutoryService->resolveRule('sss', $payDate);
                        } catch (\Throwable $e) {
                            $ruleCache[$payDate] = null;
                        }
                    }

                    $rule = $ruleCache[$payDate];

                    if ($rule && $period) {
                        $pStart = Carbon::parse($period->period_start);
                        $pEnd = Carbon::parse($period->period_end);
                        $days = $pStart->diffInDays($pEnd) + 1;
                        $factor = ($period->frequency === 'monthly' || $days > 16) ? 1.0 : 2.0;

                        $hGross = (float) $header->total_gross_pay;
                        $emp = $header->employee;
                        if ($hGross > 0) {
                            $salaryBasis = (float) ($hGross * $factor);
                        } elseif ($emp && (float) $emp->monthly_rate > 0) {
                            $salaryBasis = (float) $emp->monthly_rate;
                        } elseif ($emp && (float) $emp->daily_rate > 0) {
                            $salaryBasis = (float) $emp->daily_rate * 26;
                        } elseif ($emp && (float) $emp->hourly_rate > 0) {
                            $salaryBasis = (float) $emp->hourly_rate * 8 * 26;
                        } else {
                            $salaryBasis = 0.00;
                        }

                        $calc = $statutoryService->calculateSSS($salaryBasis, $rule);
                        $monthlyEc = (float) ($calc['monthly_ec'] ?? 0.00);
                        $periodEc = round($monthlyEc / $factor, 2);

                        $headerEc = min($storedErPlusEc, $periodEc);
                        $headerEr = max(0.00, $storedErPlusEc - $headerEc);

                        $totalEC += $headerEc;
                        $totalER += $headerEr;
                    } else {
                        $totalER += $storedErPlusEc;
                    }
                }
            }

            return [
                'no'                    => 0,
                'employee_number'       => $employee ? ($employee->employee_code ?? 'N/A') : 'N/A',
                'full_name'             => $employee
                    ? trim(($employee->last_name  ?? '') . ', ' .
                           ($employee->first_name ?? '') . ' ' .
                           ($employee->middle_name ? substr($employee->middle_name, 0, 1) . '.' : ''))
                    : '',
                'sss_number'            => $employee ? ($employee->sss_number ?? '') : '',
                'gross_income'          => (float) round($grossPay, 2),
                'employee_contribution' => (float) round($totalEE, 2),
                'employer_contribution' => (float) round($totalER, 2),
                'ec_contribution'       => (float) round($totalEC, 2),
                'total_contribution'    => (float) round($totalEE + $totalER + $totalEC, 2),
            ];
        });

        return collect($grouped->values())->map(function ($item, $index) {
            $item['no'] = $index + 1;
            return $item;
        });
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
        ];
    }

    public function headings(): array
    {
        return [
            'No.',
            'Employee No.',
            'Employee Name',
            'SSS Number',
            'Gross Income',
            'Employee Contribution',
            'Employer Contribution',
            'EC Contribution',
            'Total Contribution',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => 'D0D0D0'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'SSS Report';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 15,
            'C' => 32,
            'D' => 15,
            'E' => 15,
            'F' => 20,
            'G' => 20,
            'H' => 15,
            'I' => 18,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->insertNewRowBefore(1, 2);
                $event->sheet->setCellValue('A1', 'SSS CONTRIBUTION REPORT');
                $periodLabel = ($this->fromMonth === $this->toMonth)
                    ? 'Month: ' . Carbon::createFromFormat('Y-m', $this->fromMonth)->format('F Y')
                    : 'Period: ' . Carbon::createFromFormat('Y-m', $this->fromMonth)->format('F Y') . ' - ' . Carbon::createFromFormat('Y-m', $this->toMonth)->format('F Y');
                $event->sheet->setCellValue('A2', $periodLabel);

                $event->sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $event->sheet->mergeCells('A1:I1');

                $event->sheet->getStyle('A2')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $event->sheet->mergeCells('A2:I2');

                // Totals row
                $lastRow = $event->sheet->getHighestRow() + 1;
                $event->sheet->setCellValue('A' . $lastRow, 'TOTAL');
                $event->sheet->mergeCells('A' . $lastRow . ':D' . $lastRow);
                foreach (range('E', 'I') as $col) {
                    $colSum = 0.00;
                    for ($r = 4; $r < $lastRow; $r++) {
                        $rawVal = $event->sheet->getCell($col . $r)->getValue();
                        $numVal = (float) str_replace(',', '', (string) ($rawVal ?? 0));
                        $event->sheet->setCellValueExplicit($col . $r, $numVal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $colSum += $numVal;
                    }
                    $cell = $event->sheet->getCell($col . $lastRow);
                    $cell->setValue('=SUM(' . $col . '4:' . $col . ($lastRow - 1) . ')');
                    $cell->setCalculatedValue(round($colSum, 2));
                }
                $event->sheet->getStyle('E' . $lastRow . ':I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $event->sheet->getStyle('A' . $lastRow . ':I' . $lastRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F0F0F0']],
                ]);
            },
        ];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a map of employee_id -> total gross pay from approved pay_headers
     * for the target month, without loading lazy relations.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return array<int, float>
     */
    protected function buildGrossPayMap($startDate, $endDate): array
    {
        $companyId = $this->companyId ?? auth()->user()?->company_id ?? session('company_id');

        $rows = DB::table('pay_headers')
            ->join('payroll_periods', 'pay_headers.payroll_period_id', '=', 'payroll_periods.id')
            ->whereIn('pay_headers.status', PayHeader::FINAL_REPORT_STATUSES)
            ->whereBetween('payroll_periods.period_start', [$startDate, $endDate])
            ->when($companyId !== null, function ($q) use ($companyId) {
                $q->join('employees_master', 'pay_headers.employee_id', '=', 'employees_master.id')
                  ->where('employees_master.company_id', $companyId);
            })
            ->select('pay_headers.employee_id', DB::raw('SUM(pay_headers.total_gross_pay) as gross'))
            ->groupBy('pay_headers.employee_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->employee_id] = (float) $row->gross;
        }
        return $map;
    }
}
