<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>13th Month Pay Computation</title>
    <style>
        @page { size: legal landscape; margin: 8mm 8mm; }
        * { box-sizing: border-box; }

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
            border-bottom: 2px solid #1a5276;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #1a5276;
            text-transform: uppercase;
        }
        .report-title {
            font-size: 12px;
            font-weight: bold;
            margin: 3px 0 2px;
            color: #2e4057;
        }
        .period-info {
            font-size: 8.5px;
            color: #4a5568;
        }

        /* ── Formula Box ── */
        .formula-box {
            background: #eaf4fb;
            border: 1px solid #aed6f1;
            padding: 5px 10px;
            margin-bottom: 8px;
            font-size: 8px;
            border-radius: 3px;
        }
        .formula-box strong { color: #1a5276; }

        /* ── Summary Boxes ── */
        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .summary-box {
            display: table-cell;
            width: 25%;
            border: 1px solid #aed6f1;
            background: #d6eaf8;
            padding: 6px 8px;
            text-align: center;
        }
        .summary-box .label { font-size: 7.5px; color: #4a5568; text-transform: uppercase; }
        .summary-box .value { font-size: 12px; font-weight: bold; color: #1a5276; margin-top: 2px; }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        th, td {
            border: 1px solid #4a5568;
            padding: 4px 5px;
            font-size: 8px;
        }

        thead tr:first-child th {
            background-color: #1a5276;
            color: #ffffff;
            font-size: 8.5px;
            text-align: center;
        }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }

        .total-row {
            font-weight: bold;
            background-color: #d4e6f1;
        }

        .highlight-13 {
            background-color: #fef9e7;
            color: #7d6608;
            font-weight: bold;
        }

        .dept-badge {
            font-size: 7.5px;
            color: #4a5568;
        }

        /* ── Certification / Signatures ── */
        .cert-section {
            margin-top: 25px;
            font-size: 8px;
        }
        .sig-line {
            display: table;
            width: 100%;
            margin-top: 20px;
        }
        .sig-cell {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 0 10px;
        }
        .sig-cell .line {
            border-top: 1px solid #2d3748;
            margin-top: 25px;
            padding-top: 3px;
            font-size: 8px;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 12px;
            font-size: 7px;
            text-align: center;
            color: #718096;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->company_name ?? $company->name ?? 'COMPANY NAME' }}</div>
        <div class="report-title">13TH MONTH PAY COMPUTATION MATRIX</div>
        <div class="period-info">
            Period Covered: <strong>{{ \Carbon\Carbon::parse($fromPeriod->period_start)->format('m/d/Y') }}</strong>
            to
            <strong>{{ \Carbon\Carbon::parse($toPeriod->period_end)->format('m/d/Y') }}</strong>
            &nbsp;|&nbsp;
            <strong>{{ $periodCount }}</strong> payroll period(s) covered
            &nbsp;|&nbsp;
            Generated: {{ \Carbon\Carbon::now()->format('F d, Y') }}
        </div>
    </div>

    <!-- Formula Box -->
    <div class="formula-box">
        <strong>Statutory Formula Used:</strong>
        13th Month Pay = <strong>Total Qualifying Basic Pay Earned ÷ 12</strong>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <em>Based on Philippine Presidential Decree No. 851</em>
    </div>

    <!-- Summary Cards -->
    <div class="summary-row">
        <div class="summary-box">
            <div class="label">Total Employees</div>
            <div class="value">{{ count($reportData) }}</div>
        </div>
        <div class="summary-box">
            <div class="label">Total Basic Pay (All Periods)</div>
            <div class="value">{{ number_format($grandTotal['total_basic_pay'], 2) }}</div>
        </div>
        <div class="summary-box">
            <div class="label">Total Gross Income</div>
            <div class="value">{{ number_format($grandTotal['total_gross'], 2) }}</div>
        </div>
        <div class="summary-box" style="background:#fef9e7; border-color:#f9ca24;">
            <div class="label" style="color:#7d6608;">TOTAL 13TH MONTH PAY</div>
            <div class="value" style="color:#7d6608; font-size:13px;">
                {{ number_format($grandTotal['thirteenth_month'], 2) }}
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <table>
        <thead>
            <tr>
                <th style="width:25px;">No.</th>
                <th style="width:140px;">Employee Name</th>
                <th style="width:110px;">Department</th>
                <th style="width:75px;">Periods Worked</th>
                <th style="width:100px;">Total Basic Pay</th>
                <th style="width:100px;">Total Overtime</th>
                <th style="width:100px;">Total Gross Income</th>
                <th style="width:120px; background:#f39c12; color:#fff;">13th Month Pay<br><span style="font-size:6.5px; font-weight:normal;">(Basic ÷ 12)</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $i => $row)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $row['employee']->full_name }}</strong>
                    <br>
                    <span style="font-size:6.5px; color:#718096;">{{ $row['employee']->employee_code ?? 'EMP-'.$row['employee']->id }}</span>
                </td>
                <td class="dept-badge text-center">
                    {{ $row['employee']->dept->name ?? $row['employee']->department->name ?? 'N/A' }}
                </td>
                <td class="text-center">{{ $row['periods_worked'] }} / {{ $periodCount }}</td>
                <td class="text-right">{{ number_format($row['total_basic_pay'], 2) }}</td>
                <td class="text-right">{{ number_format($row['total_overtime'], 2) }}</td>
                <td class="text-right">{{ number_format($row['total_gross'], 2) }}</td>
                <td class="text-right highlight-13">
                    <strong>{{ number_format($row['thirteenth_month'], 2) }}</strong>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="text-center">GRAND TOTAL</td>
                <td class="text-center">—</td>
                <td class="text-right">{{ number_format($grandTotal['total_basic_pay'], 2) }}</td>
                <td class="text-right">{{ number_format($grandTotal['total_overtime'], 2) }}</td>
                <td class="text-right">{{ number_format($grandTotal['total_gross'], 2) }}</td>
                <td class="text-right highlight-13">
                    <strong>{{ number_format($grandTotal['thirteenth_month'], 2) }}</strong>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Certification / Signatures -->
    <div class="cert-section">
        <p>I hereby certify that the above 13th Month Pay computation is correct and accurate.</p>

        <div class="sig-line">
            <div class="sig-cell">
                <div class="line">
                    Prepared By<br>HR Officer
                </div>
            </div>
            <div class="sig-cell">
                <div class="line">
                    Reviewed By<br>Accounting
                </div>
            </div>
            <div class="sig-cell">
                <div class="line">
                    Approved By<br>General Manager
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}
        &nbsp;|&nbsp;
        HR &amp; Payroll Management System
        &nbsp;|&nbsp;
        P.D. No. 851 (13th Month Pay Law)
    </div>

</body>
</html>
