<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Deduction Ledger - {{ $deductionLabel }}</title>
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
            border-bottom: 2px solid #c0392b;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #c0392b;
            text-transform: uppercase;
        }
        .report-title {
            font-size: 11px;
            font-weight: bold;
            margin: 2px 0 1px;
            color: #1a202c;
        }
        .period-info {
            font-size: 8.5px;
            color: #4a5568;
        }

        /* ── Employee Block ── */
        .employee-block {
            margin-bottom: 12px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            overflow: hidden;
        }
        .employee-header {
            background-color: #fadbd8;
            color: #78281f;
            padding: 4px 8px;
            font-size: 9px;
            font-weight: bold;
            border-bottom: 1px solid #cbd5e0;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #cbd5e0;
            padding: 4px 6px;
            font-size: 7.5px;
        }
        th {
            background-color: #f2d7d5;
            color: #78281f;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .subtotal-row {
            font-weight: bold;
            background-color: #f5b7b1;
            color: #78281f;
        }
        .grand-total-box {
            margin-top: 10px;
            padding: 6px;
            background-color: #e6b0aa;
            border: 1px solid #c0392b;
            font-size: 10px;
            font-weight: bold;
            text-align: right;
            color: #78281f;
        }
        .zero-val {
            color: #a0aec0;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 12px;
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
        <div class="report-title">DEDUCTION LEDGER REPORT &mdash; {{ strtoupper($deductionLabel) }}</div>
        <div class="period-info">
            Period Covered: <strong>{{ $fromPeriod->period_name }}</strong> to <strong>{{ $toPeriod->period_name }}</strong>
            &nbsp;|&nbsp;
            Cutoff Dates: {{ \Carbon\Carbon::parse($fromPeriod->period_start)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($toPeriod->period_end)->format('M d, Y') }}
        </div>
    </div>

    <!-- Employee Ledger Blocks (Vertical Rows Grouped per Employee) -->
    @foreach($reportData as $empGroup)
    <div class="employee-block">
        <div class="employee-header">
            Employee: {{ $empGroup['name'] }} &nbsp;&nbsp;|&nbsp;&nbsp; Code: {{ $empGroup['employee_code'] }} &nbsp;&nbsp;|&nbsp;&nbsp; Department: {{ $empGroup['department'] }}
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">No.</th>
                    <th style="width: 180px;">Payroll Period Name</th>
                    <th>Pay Period Range Cutoff</th>
                    <th style="width: 120px;" class="text-right">Deducted Amount (PHP)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($empGroup['rows'] as $idx => $r)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td style="font-weight: bold;">{{ $r['period_name'] }}</td>
                    <td class="text-center">{{ $r['period_range'] }}</td>
                    <td class="text-right {{ $r['amount'] == 0 ? 'zero-val' : '' }}">
                        {{ $r['amount'] == 0 ? '-' : number_format($r['amount'], 2) }}
                    </td>
                </tr>
                @endforeach
                <!-- Employee Summary Total Row -->
                <tr class="subtotal-row">
                    <td colspan="3" class="text-center">SUMMARY TOTAL ACCUMULATED {{ strtoupper($deductionLabel) }}</td>
                    <td class="text-right">PHP {{ number_format($empGroup['employee_total'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endforeach

    <!-- Grand Summary Total -->
    <div class="grand-total-box">
        GRAND TOTAL {{ strtoupper($deductionLabel) }} (ALL EMPLOYEES): PHP {{ number_format($grandTotalLedger, 2) }}
    </div>

    <!-- Footer -->
    <div class="footer">
        Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}
        &nbsp;|&nbsp;
        HR &amp; Payroll Management System
        &nbsp;|&nbsp;
        Deduction Ledger (Vertical Period History)
    </div>

</body>
</html>
