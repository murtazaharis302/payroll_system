<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recurring Income Summary by Payroll Period</title>
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
            margin-bottom: 5px;
        }
        .company-name { font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .report-title { font-size: 10px; font-weight: bold; margin: 2px 0; color: #065f46; }
        .period-info  { font-size: 7.5px; color: #4a5568; }

        /* ── Filter Info ── */
        .filter-info {
            font-size: 7px;
            color: #2d3748;
            margin-bottom: 5px;
            background: #ecfdf5;
            padding: 3px 8px;
            border: 1px solid #6ee7b7;
            border-radius: 2px;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #6b7280;
            padding: 2px 3px;
            font-size: 6.5px;
            word-break: break-word;
        }

        th {
            background-color: #d1fae5;
            font-weight: bold;
            text-align: center;
        }

        .th-date {
            background-color: #065f46;
            color: #ffffff;
            font-size: 6.5px;
            text-align: center;
        }

        .th-total {
            background-color: #78350f;
            color: #ffffff;
            text-align: center;
        }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }

        .data-row:nth-child(even) td { background-color: #f0fdf4; }
        .data-row:nth-child(odd) td  { background-color: #ffffff; }

        .grand-total-row td {
            background-color: #064e3b;
            color: #ffffff;
            font-weight: bold;
            font-size: 7px;
        }

        .zero-val { color: #9ca3af; }

        /* ── Footer ── */
        .footer {
            margin-top: 6px;
            font-size: 6.5px;
            text-align: center;
            color: #718096;
            border-top: 1px solid #e2e8f0;
            padding-top: 3px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->company_name ?? $company->name ?? 'COMPANY NAME' }}</div>
        <div class="report-title">RECURRING INCOME SUMMARY BY PAYROLL PERIOD</div>
        <div class="period-info">
            Payroll Date: <strong>{{ \Carbon\Carbon::parse($selectedPeriod->pay_date ?? $selectedPeriod->period_end)->format('m/d/Y') }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Periods Shown: <strong>{{ $periods->count() }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Total Employees: <strong>{{ count($reportData) }}</strong>
        </div>
    </div>

    <!-- Filter Info -->
    <div class="filter-info">
        <strong>Display Mode:</strong> Rate &amp; Number (Computed Amount)
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Income Types Summed:</strong>
        @foreach($selectedIncomes as $col)
            {{ $incomeLabels[$col] ?? $col }}{{ !$loop->last ? ', ' : '' }}
        @endforeach
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Include Zero Amount:</strong> {{ $includeZero ? 'Yes' : 'No' }}
    </div>

    @php
        // Grand totals per period column and overall
        $periodColTotals = [];
        foreach ($periods as $period) {
            $periodColTotals[$period->id] = 0;
        }
        $overallGrandTotal = 0;
        foreach ($reportData as $row) {
            foreach ($periods as $period) {
                $periodColTotals[$period->id] += $row['period_sums'][$period->id] ?? 0;
            }
            $overallGrandTotal += $row['row_total'];
        }
    @endphp

    <!-- Summary Table -->
    <table>
        <colgroup>
            <col style="width:18px;">
            <col style="width:100px;">
            <col style="width:60px;">
            @foreach($periods as $period)
                <col>
            @endforeach
            <col style="width:55px;">
        </colgroup>

        <thead>
            <tr>
                <th rowspan="2" class="text-center">No.</th>
                <th rowspan="2" class="text-left" style="padding-left:3px;">Employee Name</th>
                <th rowspan="2" class="text-center">Department</th>
                @foreach($periods as $period)
                    <th class="th-date">
                        {{ \Carbon\Carbon::parse($period->pay_date ?? $period->period_end ?? $period->period_start)->format('m/d/Y') }}
                    </th>
                @endforeach
                <th rowspan="2" class="th-total">GRAND<br>TOTAL</th>
            </tr>
            <tr>
                @foreach($periods as $period)
                    <th style="background:#d1fae5; color:#065f46; font-size:6px; text-align:center;">
                        {{ $period->period_name ?? \Carbon\Carbon::parse($period->period_start)->format('M d') }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach($reportData as $rowIdx => $row)
                <tr class="data-row">
                    <td class="text-center">{{ $rowIdx + 1 }}</td>
                    <td style="padding-left:3px;">{{ $row['employee']->full_name }}</td>
                    <td class="text-center">{{ $row['employee']->dept->name ?? $row['employee']->department->name ?? 'N/A' }}</td>
                    @foreach($periods as $period)
                        @php $sum = $row['period_sums'][$period->id] ?? 0; @endphp
                        <td class="text-right {{ $sum == 0 ? 'zero-val' : '' }}">
                            {{ $sum == 0 ? '-' : number_format($sum, 2) }}
                        </td>
                    @endforeach
                    <td class="text-right" style="font-weight:bold;">
                        {{ number_format($row['row_total'], 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>

        <tfoot>
            <tr class="grand-total-row">
                <td colspan="3" class="text-center">GRAND TOTAL</td>
                @foreach($periods as $period)
                    <td class="text-right">{{ number_format($periodColTotals[$period->id] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right">{{ number_format($overallGrandTotal, 2) }}</td>
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
