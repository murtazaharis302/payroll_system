<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Summary 1 - {{ $period->period_name }}</title>
    <style>
        @page {
            margin: 10mm 8mm 10mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
            color: #333333;
            line-height: 1.1;
        }
        .header {
            text-align: center;
            margin-bottom: 6px;
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 4px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #1b5e20;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-title {
            font-size: 11px;
            font-weight: bold;
            color: #333333;
            margin: 2px 0;
            text-transform: uppercase;
        }
        .period-info {
            font-size: 9px;
            color: #555555;
            font-weight: bold;
        }
        
        /* Table Layout */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 7.5px;
            page-break-inside: auto;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        th, td {
            border: 0.5px solid #666666;
            padding: 3px 2px;
            vertical-align: middle;
        }
        th {
            background-color: #f0f4f0;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 7px;
        }
        .employee-name {
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 90px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        
        .narrow-col {
            font-size: 6.5px;
            padding: 2px 1px;
        }
        
        /* Section Header Styles */
        .total-row {
            background-color: #e8f5e9;
            font-weight: bold;
            border-top: 1.5px solid #2e7d32;
            border-bottom: 1.5px solid #2e7d32;
        }
        .total-row td {
            font-size: 8px;
        }
        
        /* Legal Disclaimer Box Above Table */
        .disclaimer-box {
            border: 1px solid #b2dfdb;
            background-color: #e0f2f1;
            padding: 4px 6px;
            margin-bottom: 5px;
            font-size: 7px;
            color: #004d40;
            line-height: 1.2;
        }
        
        /* Signatures Block at Bottom */
        .signatures {
            margin-top: 12px;
            width: 100%;
            page-break-inside: avoid;
        }
        .sig-table {
            width: 100%;
            border: none;
        }
        .sig-table td {
            border: none;
            padding: 10px 5px 2px 5px;
            text-align: center;
            vertical-align: bottom;
            font-size: 7.5px;
        }
        .sig-line {
            border-bottom: 1px solid #333333;
            width: 80%;
            margin: 0 auto 3px auto;
        }
        
        .footer {
            margin-top: 8px;
            font-size: 6.5px;
            color: #777777;
            text-align: center;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->name ?? 'PAYROLL MASTER SHEET' }}</div>
        <div class="report-title">PAYROLL SUMMARY 1 MASTER SHEET</div>
        <div class="period-info">
            PAYROLL PERIOD: {{ strtoupper($period->period_name) }} 
            ({{ \Carbon\Carbon::parse($period->period_start)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($period->period_end)->format('M d, Y') }})
            | PAY DATE: {{ \Carbon\Carbon::parse($period->pay_date)->format('M d, Y') }}
        </div>
    </div>

    <!-- Mandatory Legal Disclaimer Above Table -->
    <div class="disclaimer-box">
        <strong>EMPLOYEE ACKNOWLEDGMENT / LEGAL DISCLAIMER:</strong> By signing below, each employee acknowledges receipt of the net amount paid as full and final settlement of all wages, allowances, and compensation due for the specified payroll period, with all authorized deductions itemized accordingly.
    </div>

    @php
        $earningsColspan = 1 + 2 + 2 + ($displayedIncomes->count() * 2) + ($hiddenIncomes->isNotEmpty() ? 1 : 0) + 1;
        $deductionsColspan = $displayedDeductions->count() + ($hiddenDeductions->isNotEmpty() ? 1 : 0) + 1;
    @endphp

    <!-- Payroll Summary Table -->
    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 20px;">No.</th>
                <th rowspan="2" style="width: 75px;">Employee Name</th>
                <th colspan="{{ $earningsColspan }}" style="background-color: #d0e0d0;">EARNINGS</th>
                <th colspan="{{ $deductionsColspan }}" style="background-color: #e0d0d0;">DEDUCTIONS</th>
                <th rowspan="2" class="narrow-col">NET AMOUNT PAID</th>
                <th rowspan="2" style="width: 60px;">EMPLOYEE SIGNATURE</th>
            </tr>
            <tr>
                <!-- Earnings Columns -->
                <th class="narrow-col">Regular Rate</th>
                <th class="narrow-col">Salary Days</th>
                <th class="narrow-col">Salary Amount</th>
                <th class="narrow-col">OT Hours</th>
                <th class="narrow-col">OT Amount</th>

                @foreach($displayedIncomes as $incType)
                <th class="narrow-col">{{ $incType->code ?: $incType->name }} Qty</th>
                <th class="narrow-col">{{ $incType->code ?: $incType->name }} Amt</th>
                @endforeach

                @if($hiddenIncomes->isNotEmpty())
                <th class="narrow-col">Other Income</th>
                @endif

                <th class="narrow-col">Total Earnings</th>

                <!-- Dynamic Deduction Columns -->
                @foreach($displayedDeductions as $dedType)
                <th class="narrow-col">{{ $dedType->code ?: $dedType->name }}</th>
                @endforeach

                @if($hiddenDeductions->isNotEmpty())
                <th class="narrow-col">Other Deductions</th>
                @endif

                <th class="narrow-col">Total Ded.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payrollRows as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="employee-name">{{ $row['employee']->full_name }}</td>
                <td class="text-right">{{ number_format($row['regular_rate'], 2) }}</td>
                <td class="text-right">{{ number_format($row['basic_qty'], 1) }}</td>
                <td class="text-right">{{ number_format($row['basic_amount'], 2) }}</td>
                <td class="text-right">{{ number_format($row['ot_qty'], 1) }}</td>
                <td class="text-right">{{ number_format($row['ot_amount'], 2) }}</td>

                @foreach($displayedIncomes as $incType)
                @php $incData = $row['displayed_incomes'][$incType->id] ?? ['qty' => 0.00, 'amount' => 0.00]; @endphp
                <td class="text-right">{{ number_format($incData['qty'], 1) }}</td>
                <td class="text-right">{{ number_format($incData['amount'], 2) }}</td>
                @endforeach

                @if($hiddenIncomes->isNotEmpty())
                <td class="text-right">{{ number_format($row['other_income_amount'], 2) }}</td>
                @endif

                <td class="text-right">{{ number_format($row['total_gross_pay'], 2) }}</td>

                @foreach($displayedDeductions as $dedType)
                <td class="text-right">{{ number_format($row['displayed_deductions'][$dedType->id] ?? 0.00, 2) }}</td>
                @endforeach

                @if($hiddenDeductions->isNotEmpty())
                <td class="text-right">{{ number_format($row['others_deductions_amount'], 2) }}</td>
                @endif

                <td class="text-right">{{ number_format($row['total_deductions'], 2) }}</td>
                <td class="text-right">{{ number_format($row['net_pay'], 2) }}</td>
                <td class="text-center"></td>
            </tr>
            @endforeach

            <!-- Totals Row -->
            <tr class="total-row">
                <td colspan="2" class="text-center">TOTAL</td>
                <td class="text-right">{{ number_format($totals['regular_rate'] ?? 0.00, 2) }}</td>
                <td class="text-right">{{ number_format($totals['basic_qty'] ?? 0.00, 1) }}</td>
                <td class="text-right">{{ number_format($totals['basic_amount'] ?? 0.00, 2) }}</td>
                <td class="text-right">{{ number_format($totals['ot_qty'] ?? 0.00, 1) }}</td>
                <td class="text-right">{{ number_format($totals['ot_amount'] ?? 0.00, 2) }}</td>

                @foreach($displayedIncomes as $incType)
                @php $tData = $totals['displayed_incomes'][$incType->id] ?? ['qty' => 0.00, 'amount' => 0.00]; @endphp
                <td class="text-right">{{ number_format($tData['qty'], 1) }}</td>
                <td class="text-right">{{ number_format($tData['amount'], 2) }}</td>
                @endforeach

                @if($hiddenIncomes->isNotEmpty())
                <td class="text-right">{{ number_format($totals['other_income_amount'] ?? 0.00, 2) }}</td>
                @endif

                <td class="text-right">{{ number_format($totals['total_gross_pay'] ?? 0.00, 2) }}</td>

                @foreach($displayedDeductions as $dedType)
                <td class="text-right">{{ number_format($totals['displayed_deductions'][$dedType->id] ?? 0.00, 2) }}</td>
                @endforeach

                @if($hiddenDeductions->isNotEmpty())
                <td class="text-right">{{ number_format($totals['others_deductions_amount'] ?? 0.00, 2) }}</td>
                @endif

                <td class="text-right">{{ number_format($totals['total_deductions'] ?? 0.00, 2) }}</td>
                <td class="text-right">{{ number_format($totals['net_pay'] ?? 0.00, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- Bottom Signatures Block -->
    <div class="signatures">
        <table class="sig-table">
            <tr>
                <td style="width: 33%;">
                    <div class="sig-line"></div>
                    <strong>Approved for Payment (Manager)</strong>
                </td>
                <td style="width: 33%;">
                    <div class="sig-line"></div>
                    <strong>Paymaster Certification</strong>
                </td>
                <td style="width: 33%;">
                    <div class="sig-line"></div>
                    <strong>Date of Payment</strong>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Generated on {{ \Carbon\Carbon::parse(now())->format('F d, Y h:i A') }}</p>
        @if($hiddenIncomes->isNotEmpty() || $hiddenDeductions->isNotEmpty())
        <p>
            @if($hiddenIncomes->isNotEmpty())
                Note: "Other Income" includes: {{ $hiddenIncomes->map(fn($t) => ($t->code ? $t->code . ' (' . $t->name . ')' : $t->name))->implode(', ') }}.
            @endif
            @if($hiddenDeductions->isNotEmpty())
                Note: "Other Deductions" includes: {{ $hiddenDeductions->map(fn($t) => ($t->code ? $t->code . ' (' . $t->name . ')' : $t->name))->implode(', ') }}.
            @endif
        </p>
        @endif
    </div>
</body>
</html>
