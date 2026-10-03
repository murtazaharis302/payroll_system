<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Alpha List - {{ $period->period_name }}</title>
    <style>
        @page {
            size: portrait;
            margin: 10mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
        }
        .period-info {
            font-size: 10px;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px 6px;
            text-align: left;
        }
        th {
            background-color: #d0d0d0;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
            font-size: 11px;
        }
        .row-number {
            width: 5%;
            text-align: center;
        }
        .col-name {
            width: 60px;
        }
        .col-emp-no {
            width: 10%;
        }
        .col-position {
            width: 15%;
        }
        .col-amount {
            width: 15%;
        }
        .footer {
            margin-top: 20px;
            border-top: 1px solid #333;
            padding-top: 10px;
            font-size: 9px;
            text-align: center;
        }
        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-block {
            display: table-cell;
            width: 50%;
            padding: 10px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: bold;
        }
        .signature-label {
            font-size: 9px;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->company_name }}</div>
        <div class="report-title">ALPHABETICAL LIST OF EMPLOYEES (ALPHA LIST)</div>
        <div class="period-info">
            Period: {{ $period->period_name }}<br>
            {{ \Carbon\Carbon::parse($period->period_start)->format('F d, Y') }} - {{ \Carbon\Carbon::parse($period->period_end)->format('F d, Y') }}
        </div>
    </div>

    <!-- Employee List Table -->
    <table>
        <thead>
            <tr>
                <th class="row-number">#</th>
                <th class="col-name">Employee Name</th>
                <th class="col-emp-no">Employee No.</th>
                <th class="col-position">Position</th>
                <th class="col-amount text-right">Gross Income</th>
                <th class="col-amount text-right">Total Deductions</th>
                <th class="col-amount text-right">Net Pay</th>
                <th class="col-amount text-center">Signature</th>
            </tr>
        </thead>
        <tbody>
            @php $counter = 1; @endphp
            @foreach($payrolls as $payroll)
            <tr>
                <td class="text-center">{{ $counter++ }}</td>
                <td>{{ $payroll->employee->full_name }}</td>
                <td>{{ $payroll->employee->employee_number }}</td>
                <td>{{ $payroll->employee->position ?? 'N/A' }}</td>
                <td class="text-right">PHP {{ number_format($payroll->gross_income, 2) }}</td>
                <td class="text-right">PHP {{ number_format($payroll->total_deductions, 2) }}</td>
                <td class="text-right">PHP {{ number_format($payroll->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            
            <!-- Totals Row -->
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL ({{ $payrolls->count() }} employees)</td>
                <td class="text-right">PHP {{ number_format($totals['gross_income'], 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['total_deductions'], 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['net_pay'], 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-block">
            <div class="signature-line">_____________________________</div>
            <div class="signature-label">Prepared By</div>
        </div>
        <div class="signature-block">
            <div class="signature-line">_____________________________</div>
            <div class="signature-label">Approved By</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This is a computer-generated report. Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}</p>
        <p>Total Employees: {{ $payrolls->count() }} | Total Net Pay: PHP {{ number_format($totals['net_pay'], 2) }}</p>
    </div>
</body>
</html>
