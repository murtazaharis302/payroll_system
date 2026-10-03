<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Income Payroll History</title>
    <style>
        @page { size: legal landscape; margin: 8mm 8mm; }
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 7px;
            color: #1a202c;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            border-bottom: 2px solid #2d3748;
            padding-bottom: 4px;
            margin-bottom: 5px;
        }
        .company-name  { font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .report-title  { font-size: 10px; font-weight: bold; margin: 2px 0; color: #2b6cb0; }
        .period-info   { font-size: 7.5px; color: #4a5568; }

        /* ── Filter Info ── */
        .filter-info {
            font-size: 7px;
            color: #2d3748;
            margin-bottom: 5px;
            background: #edf2f7;
            padding: 3px 8px;
            border: 1px solid #cbd5e0;
            border-radius: 2px;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #718096;
            padding: 2px 2px;
            font-size: 6.5px;
            word-break: break-word;
        }

        th {
            background-color: #e2e8f0;
            font-weight: bold;
            text-align: center;
        }

        .th-period {
            background-color: #2b6cb0;
            color: #ffffff;
            font-size: 6.5px;
            text-align: center;
        }

        .th-total-col {
            background-color: #744210;
            color: #ffffff;
            text-align: center;
        }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }

        /* Employee group header row */
        .emp-header-row {
            background-color: #dbeafe;
        }
        .emp-header-row td {
            font-weight: bold;
            font-size: 6.5px;
        }

        /* Income sub-rows */
        .income-row td {
            background-color: #f8fafc;
            font-size: 6px;
        }
        .income-label {
            padding-left: 10px;
            font-style: italic;
            color: #374151;
        }

        /* Employee total row */
        .emp-total-row td {
            background-color: #e0f2fe;
            font-weight: bold;
            font-size: 6.5px;
            border-top: 1.5px solid #2b6cb0;
        }

        /* Grand total footer */
        .grand-total-row td {
            background-color: #1e3a5f;
            color: #ffffff;
            font-weight: bold;
            font-size: 7px;
        }

        .zero-val { color: #a0aec0; }

        .col-no       { width: 18px; }
        .col-emp      { width: 90px; }
        .col-dept     { width: 55px; }
        .col-income   { width: 55px; }

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
        <div class="report-title">INCOME PAYROLL HISTORY MATRIX</div>
        <div class="period-info">
            Period Range: <strong>{{ \Carbon\Carbon::parse($fromPeriod->period_start)->format('m/d/Y') }}</strong>
            &nbsp;to&nbsp;
            <strong>{{ \Carbon\Carbon::parse($toPeriod->period_end)->format('m/d/Y') }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Total Periods: <strong>{{ $periods->count() }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Total Employees: <strong>{{ count($reportData) }}</strong>
        </div>
    </div>

    <!-- Filter Info -->
    <div class="filter-info">
        <strong>Display Mode:</strong>
        @if($displayOption === 'rate_only') Rate Only
        @elseif($displayOption === 'number_only') Number Only
        @else Rate &amp; Number (Computed Amount)
        @endif
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Include Zero Amount:</strong> {{ $includeZero ? 'Yes' : 'No' }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Income Types:</strong>
        @foreach($selectedIncomes as $col)
            {{ $incomeLabels[$col] ?? $col }}{{ !$loop->last ? ', ' : '' }}
        @endforeach
    </div>

    @php
        $periodCount = $periods->count();
        $incomeCount = count($selectedIncomes);

        // Calculate column widths dynamically
        // Fixed cols: No(18) + Employee(90) + Dept(55) + IncomeType(55) = 218px
        // Remaining width shared among period cols + grand total col
        // legal landscape ≈ 355mm usable = ~100% of table
        // We give each period col and total col equal share of remaining space
        $remainingCols = $periodCount + 1; // +1 for Grand Total
        $periodColWidthPct = round((100 - 35) / $remainingCols, 1); // approx

        // Grand totals per period per income type (for footer)
        $periodIncomeTotals = [];
        $grandIncomeTotals  = [];
        foreach ($selectedIncomes as $col) {
            $grandIncomeTotals[$col] = 0;
            foreach ($periods as $period) {
                $periodIncomeTotals[$period->id][$col] = 0;
            }
        }
        $periodGrandSums = [];
        foreach ($periods as $period) {
            $periodGrandSums[$period->id] = 0;
        }
        $overallGrandTotal = 0;

        foreach ($reportData as $row) {
            foreach ($periods as $period) {
                $pv = $row['periods'][$period->id] ?? [];
                foreach ($selectedIncomes as $col) {
                    $v = $pv[$col] ?? 0;
                    $periodIncomeTotals[$period->id][$col] += $v;
                    $grandIncomeTotals[$col] += $v;
                    $periodGrandSums[$period->id] += $v;
                }
            }
            $overallGrandTotal += $row['row_total'];
        }
    @endphp

    <!-- Main Matrix Table -->
    <table>
        <colgroup>
            <col style="width:18px;">
            <col style="width:90px;">
            <col style="width:55px;">
            <col style="width:55px;">
            @foreach($periods as $period)
                <col>
            @endforeach
            <col style="width:50px;">
        </colgroup>

        <thead>
            <tr>
                <th rowspan="2" class="text-center">No.</th>
                <th rowspan="2" class="text-left" style="padding-left:3px;">Employee Name</th>
                <th rowspan="2" class="text-center">Department</th>
                <th rowspan="2" class="text-center">Income Type</th>
                @foreach($periods as $period)
                    <th class="th-period">
                        {{ \Carbon\Carbon::parse($period->period_end ?? $period->period_start)->format('m/d/Y') }}
                    </th>
                @endforeach
                <th rowspan="2" class="th-total-col">GRAND<br>TOTAL</th>
            </tr>
            <tr>
                @foreach($periods as $period)
                    <th style="background:#ebf8ff; color:#1e3a8a; font-size:6px;">
                        {{ $period->period_name ?? \Carbon\Carbon::parse($period->period_start)->format('M d') }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach($reportData as $rowIndex => $row)
                @php
                    $empIncomeRowTotals = [];
                    $empPeriodTotals    = [];
                    foreach ($selectedIncomes as $col) {
                        $empIncomeRowTotals[$col] = 0;
                    }
                    foreach ($periods as $period) {
                        $empPeriodTotals[$period->id] = 0;
                        foreach ($selectedIncomes as $col) {
                            $v = $row['periods'][$period->id][$col] ?? 0;
                            $empIncomeRowTotals[$col] += $v;
                            $empPeriodTotals[$period->id] += $v;
                        }
                    }
                @endphp

                @if($incomeCount === 1)
                    {{-- Single income type: show 1 row per employee --}}
                    <tr>
                        <td class="text-center">{{ $rowIndex + 1 }}</td>
                        <td style="padding-left:3px; font-weight:bold;">{{ $row['employee']->full_name }}</td>
                        <td class="text-center">{{ $row['employee']->dept->name ?? $row['employee']->department->name ?? 'N/A' }}</td>
                        <td class="income-label">{{ $incomeLabels[$selectedIncomes[0]] ?? $selectedIncomes[0] }}</td>
                        @foreach($periods as $period)
                            @php $val = $row['periods'][$period->id][$selectedIncomes[0]] ?? 0; @endphp
                            <td class="text-right {{ $val == 0 ? 'zero-val' : '' }}">
                                {{ $val == 0 ? '-' : number_format($val, 2) }}
                            </td>
                        @endforeach
                        <td class="text-right" style="font-weight:bold;">{{ number_format($row['row_total'], 2) }}</td>
                    </tr>
                @else
                    {{-- Multiple income types: employee header + sub-rows + total row --}}

                    {{-- Income sub-rows --}}
                    @foreach($selectedIncomes as $colIdx => $col)
                        <tr class="{{ $colIdx === 0 ? 'emp-header-row' : 'income-row' }}">
                            @if($colIdx === 0)
                                <td class="text-center" rowspan="{{ $incomeCount + 1 }}">{{ $rowIndex + 1 }}</td>
                                <td style="padding-left:3px; font-weight:bold;" rowspan="{{ $incomeCount + 1 }}">{{ $row['employee']->full_name }}</td>
                                <td class="text-center" rowspan="{{ $incomeCount + 1 }}">{{ $row['employee']->dept->name ?? $row['employee']->department->name ?? 'N/A' }}</td>
                            @endif
                            <td class="income-label">{{ $incomeLabels[$col] ?? $col }}</td>
                            @foreach($periods as $period)
                                @php $val = $row['periods'][$period->id][$col] ?? 0; @endphp
                                <td class="text-right {{ $val == 0 ? 'zero-val' : '' }}">
                                    {{ $val == 0 ? '-' : number_format($val, 2) }}
                                </td>
                            @endforeach
                            <td class="text-right">{{ number_format($empIncomeRowTotals[$col], 2) }}</td>
                        </tr>
                    @endforeach

                    {{-- Employee period totals row --}}
                    <tr class="emp-total-row">
                        <td class="income-label" style="font-weight:bold; padding-left:10px;">▸ Total</td>
                        @foreach($periods as $period)
                            <td class="text-right">{{ number_format($empPeriodTotals[$period->id], 2) }}</td>
                        @endforeach
                        <td class="text-right">{{ number_format($row['row_total'], 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>

        <tfoot>
            @if($incomeCount > 1)
                @foreach($selectedIncomes as $col)
                    <tr style="background:#374151; color:#fff; font-size:6.5px;">
                        @if($loop->first)
                            <td colspan="3" rowspan="{{ $incomeCount }}" class="text-center" style="font-weight:bold; vertical-align:middle;">GRAND TOTAL</td>
                        @endif
                        <td style="font-weight:bold; padding-left:4px;">{{ $incomeLabels[$col] ?? $col }}</td>
                        @foreach($periods as $period)
                            <td class="text-right">{{ number_format($periodIncomeTotals[$period->id][$col] ?? 0, 2) }}</td>
                        @endforeach
                        <td class="text-right">{{ number_format($grandIncomeTotals[$col] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @endif

            <!-- Overall Grand Total row -->
            <tr class="grand-total-row">
                <td colspan="{{ $incomeCount > 1 ? 4 : 4 }}" class="text-center">
                    {{ $incomeCount > 1 ? 'OVERALL TOTAL' : 'GRAND TOTAL' }}
                </td>
                @foreach($periods as $period)
                    <td class="text-right">{{ number_format($periodGrandSums[$period->id] ?? 0, 2) }}</td>
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
