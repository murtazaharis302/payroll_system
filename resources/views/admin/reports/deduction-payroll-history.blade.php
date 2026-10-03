<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Deduction Payroll History</title>
    <style>
        @page { size: legal landscape; margin: 8mm 8mm; }
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 7.5px;
            color: #1a202c;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            border-bottom: 2px solid #2d3748;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .company-name  { font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .report-title  { font-size: 11px; font-weight: bold; margin: 2px 0; color: #c53030; }
        .period-info   { font-size: 8px; color: #4a5568; }

        /* ── Filters Badge ── */
        .filter-info {
            font-size: 7.5px;
            color: #2d3748;
            margin-bottom: 6px;
            background: #fff5f5;
            padding: 4px 8px;
            border: 1px solid #feb2b2;
            border-radius: 2px;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #4a5568;
            padding: 3px 2px;
            font-size: 6.5px;
        }

        th {
            background-color: #e2e8f0;
            font-weight: bold;
            text-align: center;
        }

        .th-period {
            background-color: #c53030;
            color: #ffffff;
            font-size: 7px;
        }

        .th-deduction {
            background-color: #fff5f5;
            color: #9b2c2c;
            font-size: 6.5px;
        }

        .th-total-col {
            background-color: #feebc8;
            color: #742a2a;
        }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }

        .total-row {
            font-weight: bold;
            background-color: #f7fafc;
        }

        .grand-total-row {
            font-weight: bold;
            background-color: #2d3748;
            color: #ffffff;
        }

        .emp-name {
            white-space: nowrap;
            max-width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 8px;
            font-size: 7px;
            text-align: center;
            color: #718096;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }

        .zero-val { color: #a0aec0; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->company_name ?? $company->name ?? 'COMPANY NAME' }}</div>
        <div class="report-title">DEDUCTION PAYROLL HISTORY MATRIX</div>
        <div class="period-info">
            Period Range: <strong>{{ \Carbon\Carbon::parse($fromPeriod->period_start)->format('m/d/Y') }}</strong>
            &nbsp;to&nbsp;
            <strong>{{ \Carbon\Carbon::parse($toPeriod->period_end)->format('m/d/Y') }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Total Periods: <strong>{{ $periods->count() }}</strong>
        </div>
    </div>

    <!-- Filter Badge -->
    <div class="filter-info">
        <strong>Display Mode:</strong>
        @if($displayOption === 'rate_only') Rate Only
        @elseif($displayOption === 'number_only') Number Only
        @else Rate & Number (Total Amount)
        @endif
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Include Zero Amount:</strong> {{ $includeZero ? 'Yes' : 'No' }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Selected Deduction Types:</strong>
        @foreach($selectedDeductions as $col)
            {{ $deductionLabels[$col] ?? $col }}{{ !$loop->last ? ', ' : '' }}
        @endforeach
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Total Employees:</strong> {{ count($reportData) }}
    </div>

    <!-- Main Matrix Table -->
    @php
        $dedCount = count($selectedDeductions);
        $periodColspan = $dedCount;
    @endphp

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:20px;">No.</th>
                <th rowspan="2" style="width:70px;">Employee Name</th>
                <th rowspan="2" style="width:60px;">Department</th>
                @foreach($periods as $period)
                    <th colspan="{{ $periodColspan }}" class="th-period">
                        {{ \Carbon\Carbon::parse($period->period_end ?? $period->period_start)->format('m/d/Y') }}
                    </th>
                @endforeach

                <th rowspan="2" class="th-total-col" style="width:55px;">GRAND<br>TOTAL</th>
            </tr>

            <tr>
                @foreach($periods as $period)
                    @foreach($selectedDeductions as $col)
                        <th class="th-deduction" style="width:38px;">
                            {{ $deductionLabels[$col] ?? $col }}
                        </th>
                    @endforeach
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach($reportData as $rowIndex => $row)
                <tr>
                    <td class="text-center">{{ $rowIndex + 1 }}</td>
                    <td class="emp-name">
                        <strong>{{ $row['employee']->full_name }}</strong>
                    </td>
                    <td>{{ $row['employee']->dept->name ?? $row['employee']->department->name ?? 'N/A' }}</td>

                    @foreach($periods as $period)
                        @php $pv = $row['periods'][$period->id] ?? []; @endphp
                        @foreach($selectedDeductions as $col)
                            @php $val = $pv[$col] ?? 0; @endphp
                            <td class="text-right {{ $val == 0 ? 'zero-val' : '' }}">
                                {{ $val == 0 ? '-' : number_format($val, 2) }}
                            </td>
                        @endforeach
                    @endforeach

                    <td class="text-right total-row">
                        {{ number_format($row['row_total'], 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>

        <tfoot>
            <tr class="grand-total-row">
                <td colspan="3" class="text-center">GRAND TOTAL</td>

                @foreach($periods as $period)
                    @foreach($selectedDeductions as $col)
                        @php
                            $periodColTotal = 0;
                            foreach($reportData as $row) {
                                $periodColTotal += $row['periods'][$period->id][$col] ?? 0;
                            }
                        @endphp
                        <td class="text-right">{{ number_format($periodColTotal, 2) }}</td>
                    @endforeach
                @endforeach

                <td class="text-right">
                    {{ number_format($grandTotals['row_total'], 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        HR &amp; Payroll Management System
    </div>

</body>
</html>
