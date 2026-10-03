<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Register - {{ $period->period_name }}</title>
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
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
        }
        .period-info {
            font-size: 9px;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #333;
            padding: 4px 3px;
            text-align: center;
            font-size: 7px;
        }
        th {
            background-color: #d0d0d0;
            font-weight: bold;
            font-size: 7px;
        }
        .text-left {
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
            font-size: 8px;
        }
        .footer {
            margin-top: 15px;
            font-size: 7px;
            text-align: center;
        }
        .col-name {
            width: 60px;
        }
        .col-emp-no {
            width: 6%;
        }
        .col-amount {
            width: 5.5%;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->company_name }}</div>
        <div class="report-title">PAYROLL REGISTER</div>
        <div class="period-info">
            Period: {{ $period->period_name }}<br>
            {{ \Carbon\Carbon::parse($period->period_start)->format('F d, Y') }} - {{ \Carbon\Carbon::parse($period->period_end)->format('F d, Y') }}
        </div>
    </div>

    <!-- Payroll Table -->
    <table>
        <thead>
            <tr>
                <th class="col-emp-no">Emp No.</th>
                <th class="col-name text-left">Employee Name</th>
                <th class="col-amount">Days</th>
                <th class="col-amount">Basic Pay</th>
                @if($totals['overtime_pay'] > 0) <th class="col-amount">OT Pay</th> @endif
                @if($totals['allowances'] > 0) <th class="col-amount">Allow.</th> @endif
                @if($totals['bonuses'] > 0) <th class="col-amount">Bonus</th> @endif
                @if($totals['other_income'] > 0) <th class="col-amount">Other</th> @endif
                <th class="col-amount">Gross Pay</th>
                @if($totals['sss_contribution'] > 0) <th class="col-amount">SSS</th> @endif
                @if($totals['philhealth_contribution'] > 0) <th class="col-amount">PhilHealth</th> @endif
                @if($totals['pagibig_contribution'] > 0) <th class="col-amount">Pag-IBIG</th> @endif
                @if($totals['withholding_tax'] > 0) <th class="col-amount">W/Tax</th> @endif
                @if($totals['sss_loan'] > 0) <th class="col-amount">SSS Loan</th> @endif
                @if($totals['pagibig_loan'] > 0) <th class="col-amount">HDMF Loan</th> @endif
                @if($totals['vale_deduction'] > 0) <th class="col-amount">Vale</th> @endif
                @if($totals['goods_deduction'] > 0) <th class="col-amount">Goods</th> @endif
                @if($totals['late_deduction'] > 0) <th class="col-amount">Late Ded.</th> @endif
                @if($totals['undertime_deduction'] > 0) <th class="col-amount">Undertime Ded.</th> @endif
                @if($totals['other_deductions'] > 0) <th class="col-amount">Other Ded.</th> @endif
                <th class="col-amount">Total Ded.</th>
                <th class="col-amount">Net Pay</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payrolls as $payroll)
            <tr>
                <td>{{ $payroll->employee->employee_code ?? $payroll->employee->employee_number }}</td>
                <td class="text-left">{{ $payroll->employee->full_name }}</td>
                <td>{{ number_format($payroll->total_days_worked, 1) }}</td>
                <td class="text-right">{{ number_format($payroll->basic_pay, 2) }}</td>
                @if($totals['overtime_pay'] > 0) <td class="text-right">{{ number_format($payroll->overtime_pay, 2) }}</td> @endif
                @if($totals['allowances'] > 0) <td class="text-right">{{ number_format($payroll->allowances, 2) }}</td> @endif
                @if($totals['bonuses'] > 0) <td class="text-right">{{ number_format($payroll->bonuses, 2) }}</td> @endif
                @if($totals['other_income'] > 0) <td class="text-right">{{ number_format($payroll->other_income, 2) }}</td> @endif
                <td class="text-right">{{ number_format($payroll->gross_income, 2) }}</td>
                @if($totals['sss_contribution'] > 0) <td class="text-right">{{ number_format($payroll->sss_contribution, 2) }}</td> @endif
                @if($totals['philhealth_contribution'] > 0) <td class="text-right">{{ number_format($payroll->philhealth_contribution, 2) }}</td> @endif
                @if($totals['pagibig_contribution'] > 0) <td class="text-right">{{ number_format($payroll->pagibig_contribution, 2) }}</td> @endif
                @if($totals['withholding_tax'] > 0) <td class="text-right">{{ number_format($payroll->withholding_tax, 2) }}</td> @endif
                @if($totals['sss_loan'] > 0) <td class="text-right">{{ number_format($payroll->sss_loan, 2) }}</td> @endif
                @if($totals['pagibig_loan'] > 0) <td class="text-right">{{ number_format($payroll->pagibig_loan, 2) }}</td> @endif
                @if($totals['vale_deduction'] > 0) <td class="text-right">{{ number_format($payroll->vale_deduction, 2) }}</td> @endif
                @if($totals['goods_deduction'] > 0) <td class="text-right">{{ number_format($payroll->goods_deduction, 2) }}</td> @endif
                @if($totals['late_deduction'] > 0) <td class="text-right">{{ number_format($payroll->late_deduction, 2) }}</td> @endif
                @if($totals['undertime_deduction'] > 0) <td class="text-right">{{ number_format($payroll->undertime_deduction, 2) }}</td> @endif
                @if($totals['other_deductions'] > 0) <td class="text-right">{{ number_format($payroll->other_deductions, 2) }}</td> @endif
                <td class="text-right">{{ number_format($payroll->total_deductions, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->net_pay, 2) }}</td>
            </tr>
            @endforeach
            
            <!-- Totals Row -->
            <tr class="total-row">
                <td colspan="3">TOTAL</td>
                <td class="text-right">{{ number_format($totals['basic_pay'], 2) }}</td>
                @if($totals['overtime_pay'] > 0) <td class="text-right">{{ number_format($totals['overtime_pay'], 2) }}</td> @endif
                @if($totals['allowances'] > 0) <td class="text-right">{{ number_format($totals['allowances'], 2) }}</td> @endif
                @if($totals['bonuses'] > 0) <td class="text-right">{{ number_format($totals['bonuses'], 2) }}</td> @endif
                @if($totals['other_income'] > 0) <td class="text-right">{{ number_format($totals['other_income'], 2) }}</td> @endif
                <td class="text-right">{{ number_format($totals['gross_income'], 2) }}</td>
                @if($totals['sss_contribution'] > 0) <td class="text-right">{{ number_format($totals['sss_contribution'], 2) }}</td> @endif
                @if($totals['philhealth_contribution'] > 0) <td class="text-right">{{ number_format($totals['philhealth_contribution'], 2) }}</td> @endif
                @if($totals['pagibig_contribution'] > 0) <td class="text-right">{{ number_format($totals['pagibig_contribution'], 2) }}</td> @endif
                @if($totals['withholding_tax'] > 0) <td class="text-right">{{ number_format($totals['withholding_tax'], 2) }}</td> @endif
                @if($totals['sss_loan'] > 0) <td class="text-right">{{ number_format($totals['sss_loan'], 2) }}</td> @endif
                @if($totals['pagibig_loan'] > 0) <td class="text-right">{{ number_format($totals['pagibig_loan'], 2) }}</td> @endif
                @if($totals['vale_deduction'] > 0) <td class="text-right">{{ number_format($totals['vale_deduction'], 2) }}</td> @endif
                @if($totals['goods_deduction'] > 0) <td class="text-right">{{ number_format($totals['goods_deduction'], 2) }}</td> @endif
                @if($totals['late_deduction'] > 0) <td class="text-right">{{ number_format($totals['late_deduction'], 2) }}</td> @endif
                @if($totals['undertime_deduction'] > 0) <td class="text-right">{{ number_format($totals['undertime_deduction'], 2) }}</td> @endif
                @if($totals['other_deductions'] > 0) <td class="text-right">{{ number_format($totals['other_deductions'], 2) }}</td> @endif
                <td class="text-right">{{ number_format($totals['total_deductions'], 2) }}</td>
                <td class="text-right">{{ number_format($totals['net_pay'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }} | Total Employees: {{ $payrolls->count() }}</p>
    </div>
</body>
</html>
