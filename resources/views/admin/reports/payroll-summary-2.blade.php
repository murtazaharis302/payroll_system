<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Summary 2 - {{ $period->period_name }}</title>
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
            border-bottom: 2px solid #1565c0;
            padding-bottom: 4px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #0d47a1;
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
        
        .dept-block {
            margin-bottom: 12px;
            page-break-inside: auto;
        }
        .dept-header-banner {
            background-color: #1565c0;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 3px 6px;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
            margin-bottom: 4px;
        }
        tr {
            page-break-inside: avoid;
        }
        th, td {
            border: 0.5px solid #666666;
            padding: 3px 2px;
            vertical-align: middle;
        }
        th {
            background-color: #f0f4f8;
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
            max-width: 95px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        
        .narrow-col {
            font-size: 6.5px;
            padding: 2px 1px;
        }
        
        .subtotal-row {
            background-color: #e3f2fd;
            font-weight: bold;
            border-top: 1px solid #1565c0;
            border-bottom: 1px solid #1565c0;
        }
        .subtotal-row td {
            font-size: 7.5px;
        }
        
        .footer {
            margin-top: 10px;
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
        <div class="company-name">{{ $company->name ?? 'PAYROLL SUMMARY' }}</div>
        <div class="report-title">PAYROLL SUMMARY 2 (DEPARTMENTAL BREAKDOWN)</div>
        <div class="period-info">
            PAYROLL PERIOD: {{ strtoupper($period->period_name) }} 
            ({{ \Carbon\Carbon::parse($period->period_start)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($period->period_end)->format('M d, Y') }})
            | PAY DATE: {{ \Carbon\Carbon::parse($period->pay_date)->format('M d, Y') }}
            | SCOPE: {{ strtoupper($selectedDepartmentName) }}
        </div>
    </div>

    @php
        $earningsColspan = 1 + 2 + 2 + ($displayedIncomes->count() * 2) + ($hiddenIncomes->isNotEmpty() ? 1 : 0) + 1;
        $deductionsColspan = $displayedDeductions->count() + ($hiddenDeductions->isNotEmpty() ? 1 : 0) + 1;
        $groupedList = $payrollRowsGrouped ?? collect();
    @endphp

    @foreach($groupedList as $deptName => $deptRows)
    <div class="dept-block">
        <div class="dept-header-banner">
            DEPARTMENT: {{ strtoupper($deptName) }} ({{ count($deptRows) }} Employees)
        </div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width: 20px;">No.</th>
                    <th rowspan="2" style="width: 75px;">Employee Name</th>
                    <th colspan="{{ $earningsColspan }}" style="background-color: #d1fae5;">EARNINGS</th>
                    <th colspan="{{ $deductionsColspan }}" style="background-color: #fee2e2;">DEDUCTIONS</th>
                    <th rowspan="2" class="narrow-col">NET AMOUNT PAID</th>
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
                @php
                    $dTotals = [
                        'regular_rate' => 0.00,
                        'basic_qty' => 0.00,
                        'basic_amount' => 0.00,
                        'ot_qty' => 0.00,
                        'ot_amount' => 0.00,
                        'displayed_incomes' => [],
                        'other_income_amount' => 0.00,
                        'total_gross_pay' => 0.00,
                        'displayed_deductions' => [],
                        'others_deductions_amount' => 0.00,
                        'total_deductions' => 0.00,
                        'net_pay' => 0.00,
                    ];
                    foreach ($displayedIncomes as $incType) {
                        $dTotals['displayed_incomes'][$incType->id] = ['qty' => 0.00, 'amount' => 0.00];
                    }
                    foreach ($displayedDeductions as $dedType) {
                        $dTotals['displayed_deductions'][$dedType->id] = 0.00;
                    }
                @endphp

                @foreach($deptRows as $index => $row)
                @php
                    $dTotals['regular_rate'] += $row['regular_rate'];
                    $dTotals['basic_qty'] += $row['basic_qty'];
                    $dTotals['basic_amount'] += $row['basic_amount'];
                    $dTotals['ot_qty'] += $row['ot_qty'];
                    $dTotals['ot_amount'] += $row['ot_amount'];
                    foreach ($displayedIncomes as $incType) {
                        $idata = $row['displayed_incomes'][$incType->id] ?? ['qty' => 0.00, 'amount' => 0.00];
                        $dTotals['displayed_incomes'][$incType->id]['qty'] += $idata['qty'];
                        $dTotals['displayed_incomes'][$incType->id]['amount'] += $idata['amount'];
                    }
                    $dTotals['other_income_amount'] += $row['other_income_amount'];
                    $dTotals['total_gross_pay'] += $row['total_gross_pay'];

                    foreach ($displayedDeductions as $dedType) {
                        $dTotals['displayed_deductions'][$dedType->id] += ($row['displayed_deductions'][$dedType->id] ?? 0.00);
                    }
                    $dTotals['others_deductions_amount'] += $row['others_deductions_amount'];
                    $dTotals['total_deductions'] += $row['total_deductions'];
                    $dTotals['net_pay'] += $row['net_pay'];
                @endphp
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
                    <td class="text-right"><strong>{{ number_format($row['net_pay'], 2) }}</strong></td>
                </tr>
                @endforeach
                
                <!-- Department Subtotal Row -->
                <tr class="subtotal-row">
                    <td colspan="2" class="text-center">DEPARTMENT SUBTOTAL ({{ strtoupper($deptName) }})</td>
                    <td class="text-right">{{ number_format($dTotals['regular_rate'], 2) }}</td>
                    <td class="text-right">{{ number_format($dTotals['basic_qty'], 1) }}</td>
                    <td class="text-right">{{ number_format($dTotals['basic_amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($dTotals['ot_qty'], 1) }}</td>
                    <td class="text-right">{{ number_format($dTotals['ot_amount'], 2) }}</td>

                    @foreach($displayedIncomes as $incType)
                    @php $tData = $dTotals['displayed_incomes'][$incType->id] ?? ['qty' => 0.00, 'amount' => 0.00]; @endphp
                    <td class="text-right">{{ number_format($tData['qty'], 1) }}</td>
                    <td class="text-right">{{ number_format($tData['amount'], 2) }}</td>
                    @endforeach

                    @if($hiddenIncomes->isNotEmpty())
                    <td class="text-right">{{ number_format($dTotals['other_income_amount'], 2) }}</td>
                    @endif

                    <td class="text-right">{{ number_format($dTotals['total_gross_pay'], 2) }}</td>

                    @foreach($displayedDeductions as $dedType)
                    <td class="text-right">{{ number_format($dTotals['displayed_deductions'][$dedType->id] ?? 0.00, 2) }}</td>
                    @endforeach

                    @if($hiddenDeductions->isNotEmpty())
                    <td class="text-right">{{ number_format($dTotals['others_deductions_amount'], 2) }}</td>
                    @endif

                    <td class="text-right">{{ number_format($dTotals['total_deductions'], 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($dTotals['net_pay'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    @endforeach

    <!-- Summary Cards -->
    <div style="display: table; width: 100%; margin-top: 10px; page-break-inside: avoid;">
        <div style="display: table-cell; width: 33%; padding-right: 5px;">
            <div style="border: 2px solid #2e7d32; padding: 8px; background-color: #e8f5e9;">
                <div style="font-size: 8px; color: #2e7d32; font-weight: bold; margin-bottom: 3px;">GRAND TOTAL EARNINGS</div>
                <div style="font-size: 12px; font-weight: bold; color: #1b5e20;">PHP {{ number_format($totals['total_gross_pay'] ?? 0.00, 2) }}</div>
            </div>
        </div>
        <div style="display: table-cell; width: 33%; padding: 0 2.5px;">
            <div style="border: 2px solid #c62828; padding: 8px; background-color: #ffebee;">
                <div style="font-size: 8px; color: #c62828; font-weight: bold; margin-bottom: 3px;">GRAND TOTAL DEDUCTIONS</div>
                <div style="font-size: 12px; font-weight: bold; color: #b71c1c;">PHP {{ number_format($totals['total_deductions'] ?? 0.00, 2) }}</div>
            </div>
        </div>
        <div style="display: table-cell; width: 33%; padding-left: 5px;">
            <div style="border: 2px solid #1565c0; padding: 8px; background-color: #e3f2fd;">
                <div style="font-size: 8px; color: #1565c0; font-weight: bold; margin-bottom: 3px;">GRAND NET PAYROLL</div>
                <div style="font-size: 12px; font-weight: bold; color: #0d47a1;">PHP {{ number_format($totals['net_pay'] ?? 0.00, 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p><strong>Total Employees:</strong> {{ count($payrolls) }} | <strong>Generated on:</strong> {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}</p>
        @if($hiddenIncomes->isNotEmpty() || $hiddenDeductions->isNotEmpty())
        <p style="margin-top: 3px;">
            @if($hiddenIncomes->isNotEmpty())
                Note: "Other Income" includes: {{ $hiddenIncomes->map(fn($t) => ($t->code ? $t->code . ' (' . $t->name . ')' : $t->name))->implode(', ') }}.
            @endif
            @if($hiddenDeductions->isNotEmpty())
                Note: "Other Deductions" includes: {{ $hiddenDeductions->map(fn($t) => ($t->code ? $t->code . ' (' . $t->name . ')' : $t->name))->implode(', ') }}.
            @endif
        </p>
        @endif
        <p style="margin-top: 2px;">Computer-generated departmental payroll summary. All amounts in PHP.</p>
    </div>
</body>
</html>
