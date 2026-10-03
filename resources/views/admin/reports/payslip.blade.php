<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payHeader->employee->first_name }} {{ $payHeader->employee->last_name }}</title>
    <style>
        <?php
        // Count income and deduction items dynamically
        $incomeCount = $payHeader->payIns->count();
        $deductionCount = $payHeader->payOuts->count();
        $totalItems = $incomeCount + $deductionCount;
        
        // Dynamic margin calculation
        // Fewer items = smaller margins to fit more payslips per sheet
        if ($totalItems <= 8) {
            $topMargin = '5mm';
            $bottomMargin = '5mm';
            $fontSize = '9px';
            $tablePadding = '4px';
            $headerSize = '14px';
            $titleSize = '12px';
        } elseif ($totalItems <= 12) {
            $topMargin = '8mm';
            $bottomMargin = '8mm';
            $fontSize = '10px';
            $tablePadding = '5px';
            $headerSize = '16px';
            $titleSize = '14px';
        } else {
            $topMargin = '10mm';
            $bottomMargin = '10mm';
            $fontSize = '11px';
            $tablePadding = '6px';
            $headerSize = '18px';
            $titleSize = '16px';
        }
        ?>
        
        @page {
            margin-top: {{ $topMargin }};
            margin-bottom: {{ $bottomMargin }};
            margin-left: 8mm;
            margin-right: 8mm;
        }
        
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: {{ $fontSize }};
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
        }
        .company-name {
            font-size: {{ $headerSize }};
            font-weight: bold;
            margin-bottom: 3px;
        }
        .company-info {
            font-size: calc({{ $fontSize }} - 1px);
            color: #666;
        }
        .payslip-title {
            font-size: {{ $titleSize }};
            font-weight: bold;
            margin: 8px 0;
            text-align: center;
        }
        .employee-info {
            margin-bottom: 8px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 2px;
        }
        .info-label {
            display: table-cell;
            width: 30%;
            font-weight: bold;
        }
        .info-value {
            display: table-cell;
            width: 70%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }
        th, td {
            border: 1px solid #333;
            padding: {{ $tablePadding }};
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .net-pay-section {
            margin-top: 10px;
            border: 2px solid #333;
            padding: 8px;
            text-align: center;
        }
        .net-pay-label {
            font-size: calc({{ $fontSize }} + 2px);
            font-weight: bold;
            margin-bottom: 3px;
        }
        .net-pay-amount {
            font-size: calc({{ $fontSize }} + 6px);
            font-weight: bold;
            color: #2c5f2d;
        }
        .footer {
            margin-top: 10px;
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: calc({{ $fontSize }} - 2px);
            text-align: center;
            color: #666;
        }
        .section-title {
            font-size: calc({{ $fontSize }} + 1px);
            font-weight: bold;
            margin: 8px 0 5px 0;
            padding: 3px;
            background-color: #e0e0e0;
        }
        .compact-table tbody tr {
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <!-- Company Header -->
    <div class="header">
        <div class="company-name">{{ $company->company_name }}</div>
        <div class="company-info">
            {{ $company->company_address }}<br>
            @if($company->company_phone)Phone: {{ $company->company_phone }}@endif
            @if($company->company_email) | Email: {{ $company->company_email }}@endif
        </div>
    </div>

    <div class="payslip-title">PAYSLIP</div>

    <!-- Employee Information -->
    <div class="employee-info">
        <div class="info-row">
            <div class="info-label">Employee Name:</div>
            <div class="info-value">{{ $payHeader->employee->first_name }} {{ $payHeader->employee->middle_name }} {{ $payHeader->employee->last_name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Employee No:</div>
            <div class="info-value">{{ $payHeader->employee->employee_code ?? 'EMP-' . $payHeader->employee->id }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Position:</div>
            <div class="info-value">{{ $payHeader->employee->position ?? 'N/A' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Department:</div>
            <div class="info-value">{{ $payHeader->employee->department->name ?? 'N/A' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Pay Period:</div>
            <div class="info-value">{{ $payHeader->payrollPeriod->period_name }} ({{ \Carbon\Carbon::parse($payHeader->payrollPeriod->period_start)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($payHeader->payrollPeriod->period_end)->format('M d, Y') }})</div>
        </div>
    </div>

    <!-- Earnings Section -->
    <div class="section-title">EARNINGS</div>
    <table class="compact-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount (PHP)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payHeader->payIns as $income)
                @if($income->amount > 0)
                <tr>
                    <td>
                        {{ $income->incomeType->name }}
                        @if($income->incomeType->code === 'OVERTIME' || $income->incomeType->code === 'OT')
                            ({{ number_format($income->number_input, 2) }} hrs)
                        @elseif($income->incomeType->code !== 'BASIC' && $income->number_input > 1)
                            ({{ number_format($income->number_input, 1) }} days)
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($income->amount, 2) }}</td>
                </tr>
                @endif
            @endforeach
            <tr class="total-row">
                <td>GROSS PAY</td>
                <td class="text-right">{{ number_format($payHeader->total_gross_pay, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Deductions Section -->
    <div class="section-title">DEDUCTIONS</div>
    <table class="compact-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount (PHP)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payHeader->payOuts as $deduction)
                @if($deduction->amount > 0)
                <tr>
                    <td>
                        @if($deduction->loan)
                            {{ $deduction->loan->loanType->name ?? $deduction->deductionType->name }} (Loan Payment)
                        @else
                            {{ $deduction->deductionType->name }}
                        @endif
                        @if($deduction->deductionType->code === 'TARDINESS' || $deduction->deductionType->code === 'UNDERTIME')
                            ({{ number_format($deduction->number_input, 2) }} hrs)
                        @elseif($deduction->number_input > 1 && !$deduction->loan)
                            ({{ number_format($deduction->number_input, 1) }} days)
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($deduction->amount, 2) }}</td>
                </tr>
                @endif
            @endforeach
            <tr class="total-row">
                <td>TOTAL DEDUCTIONS</td>
                <td class="text-right">{{ number_format($payHeader->total_deductions, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Net Pay -->
    <div class="net-pay-section">
        <div class="net-pay-label">NET PAY</div>
        <div class="net-pay-amount">PHP {{ number_format($payHeader->net_pay, 2) }}</div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This is a computer-generated payslip and does not require a signature.</p>
        <p>Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}</p>
    </div>
</body>
</html>
