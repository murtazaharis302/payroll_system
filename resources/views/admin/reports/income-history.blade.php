<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Single-Period Income History - {{ $period->period_name }}</title>
    <style>
        @page {
            size: legal landscape;
            margin: 8mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #1a202c;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            border-bottom: 2px solid #1e8449;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #1e8449;
            text-transform: uppercase;
        }
        .report-title {
            font-size: 11px;
            font-weight: bold;
            margin: 2px 0 1px;
            color: #1a202c;
        }
        .period-info {
            font-size: 8px;
            color: #4a5568;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
        }
        th, td {
            border: 1px solid #cbd5e0;
            padding: 4px 5px;
            font-size: 7.5px;
        }
        th {
            background-color: #d5f5e3;
            color: #1e8449;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #abebc6;
            color: #1e8449;
        }
        .zero-val {
            color: #a0aec0;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 10px;
            font-size: 7px;
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
        <div class="report-title">SINGLE-PERIOD INCOME HISTORY REPORT</div>
        <div class="period-info">
            Payroll Period: <strong>{{ $period->period_name }}</strong>
            &nbsp;|&nbsp;
            Cutoff Period: {{ \Carbon\Carbon::parse($period->period_start)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($period->period_end)->format('M d, Y') }}
        </div>
    </div>

    <!-- Main Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 25px;">No.</th>
                <th style="width: 60px;" class="text-center">Emp Code</th>
                <th style="width: 130px;">Employee Name</th>
                <th style="width: 90px;">Department</th>
                
                <!-- Dynamic income category columns -->
                @foreach($selectedIncomes as $col)
                    <th class="text-right">
                        {{ $incomeLabels[$col] ?? ucwords(str_replace('_', ' ', (string) $col)) }}
                    </th>
                @endforeach

                <th style="width: 85px;" class="text-right">TOTAL INCOME</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $row['employee_code'] }}</td>
                <td style="font-weight: bold;">{{ $row['name'] }}</td>
                <td>{{ $row['department'] }}</td>
                
                <!-- Dynamic values -->
                @foreach($selectedIncomes as $col)
                    @php $val = (float) ($row['incomes'][$col] ?? 0); @endphp
                    <td class="text-right {{ $val == 0 ? 'zero-val' : '' }}">
                        {{ $val == 0 ? '-' : number_format($val, 2) }}
                    </td>
                @endforeach

                <td class="text-right" style="font-weight: bold;">
                    {{ number_format($row['total_income'], 2) }}
                </td>
            </tr>
            @endforeach
            
            <!-- Grand Totals Row -->
            <tr class="total-row">
                <td colspan="4" class="text-center">GRAND TOTAL INCOME</td>
                @foreach($selectedIncomes as $col)
                    <td class="text-right">
                        {{ number_format($columnTotals[$col] ?? 0, 2) }}
                    </td>
                @endforeach
                <td class="text-right">
                    PHP {{ number_format($grandTotalIncome, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}
        &nbsp;|&nbsp;
        HR &amp; Payroll Management System
        &nbsp;|&nbsp;
        Single-Period Itemized Earnings Breakdown
    </div>

</body>
</html>
