<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Loan Ledger - {{ $employee->first_name }} {{ $employee->last_name }}</title>
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
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
        }
        .employee-info {
            margin: 15px 0;
            padding: 10px 12px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
        }
        .info-row {
            margin-bottom: 4px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin: 18px 0 8px 0;
            padding: 5px 8px;
            background-color: #e6ecf2;
            border-left: 4px solid #0275d8;
            color: #111;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 16px 0;
        }
        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .summary-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .payment-container {
            padding: 8px 12px !important;
            background-color: #f8fafc !important;
        }
        .payment-table {
            width: 100% !important;
            margin: 4px 0 0 0 !important;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        .payment-table th {
            background-color: #e5e9f0 !important;
            font-weight: bold;
            border: 1px solid #b0bec5 !important;
            padding: 4px 6px !important;
        }
        .payment-table td {
            border: 1px solid #cfd8dc !important;
            padding: 4px 6px !important;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .status-active {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-payroll {
            color: #155724;
            font-weight: bold;
        }
        .badge-manual {
            color: #004085;
            font-weight: bold;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #333;
            padding-top: 10px;
            font-size: 9px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->company_name ?? $company->name ?? 'Company' }}</div>
        <div class="report-title">LOAN LEDGER REPORT</div>
    </div>

    <!-- Employee Information -->
    <div class="employee-info">
        <div class="info-row"><strong>Employee Name:</strong> {{ $employee->first_name }} {{ $employee->middle_name }} {{ $employee->last_name }}</div>
        <div class="info-row"><strong>Employee No:</strong> {{ $employee->employee_code }}</div>
        <div class="info-row"><strong>Position:</strong> {{ $employee->position ?? 'N/A' }}</div>
        <div class="info-row"><strong>Department:</strong> {{ $employee->dept->name ?? $employee->department ?? 'N/A' }}</div>
        <div class="info-row"><strong>Report Date:</strong> {{ \Carbon\Carbon::now()->format('F d, Y') }}</div>
    </div>

    <!-- Vale Loans Section -->
    @if(isset($data['vale_loans']) && $data['vale_loans']->count() > 0)
    <div class="section-title">VALE / CASH ADVANCE LOANS</div>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Loan Date</th>
                <th style="width: 25%;">Description</th>
                <th style="width: 18%;" class="text-right">Principal Amount</th>
                <th style="width: 18%;" class="text-right">Current Balance</th>
                <th style="width: 14%;" class="text-right">Deduction/Cutoff</th>
                <th style="width: 10%;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['vale_loans'] as $loan)
            <tr>
                <td>{{ \Carbon\Carbon::parse($loan->loan_date)->format('M d, Y') }}</td>
                <td>{{ $loan->loanType->name ?? $loan->notes ?? 'Vale/Cash Advance' }}</td>
                <td class="text-right">PHP {{ number_format((float)$loan->principal_amount, 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$loan->balance, 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$loan->installment_amount, 2) }}</td>
                <td class="text-center">
                    <span class="status-badge {{ $loan->status == 'paid' || $loan->balance <= 0 ? 'status-paid' : 'status-active' }}">
                        {{ strtoupper((string)$loan->status) }}
                    </span>
                </td>
            </tr>
            @if($loan->payments->count() > 0)
            <tr>
                <td colspan="6" class="payment-container">
                    <div style="font-weight: bold; font-size: 9px; color: #333; margin-bottom: 3px;">Payment History Breakdown:</div>
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Payment Date</th>
                                <th style="width: 30%; text-align: right;" class="text-right">Amount Paid</th>
                                <th style="width: 45%;">Payment Source / Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->payments as $payment)
                            <tr>
                                <td>
                                    {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                                    @if($payment->payroll_period_name)
                                        <br><small style="color: #6c757d; font-size: 8px;">({{ $payment->payroll_period_name }})</small>
                                    @endif
                                </td>
                                <td class="text-right">PHP {{ number_format((float)$payment->amount, 2) }}</td>
                                <td>
                                    <span class="{{ $payment->pay_id ? 'badge-payroll' : 'badge-manual' }}">
                                        {{ $payment->notes }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
            @endif
            @endforeach
            <tr class="summary-row">
                <td colspan="2">TOTAL VALE LOANS</td>
                <td class="text-right">PHP {{ number_format((float)$data['vale_loans']->sum('principal_amount'), 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$data['vale_loans']->sum('balance'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
    @endif

    <!-- Goods Purchase Section -->
    @if(isset($data['goods_purchases']) && $data['goods_purchases']->count() > 0)
    <div class="section-title">GOODS PURCHASE</div>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Purchase Date</th>
                <th style="width: 25%;">Description</th>
                <th style="width: 18%;" class="text-right">Total Amount</th>
                <th style="width: 18%;" class="text-right">Paid Amount</th>
                <th style="width: 14%;" class="text-right">Balance</th>
                <th style="width: 10%;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['goods_purchases'] as $goods)
            <tr>
                <td>{{ \Carbon\Carbon::parse($goods->purchase_date)->format('M d, Y') }}</td>
                <td>{{ $goods->loanType->name ?? $goods->item_description ?? $goods->notes ?? 'Goods Purchase' }}</td>
                <td class="text-right">PHP {{ number_format((float)$goods->total_amount, 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$goods->total_paid, 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$goods->balance, 2) }}</td>
                <td class="text-center">
                    <span class="status-badge {{ $goods->status == 'paid' || $goods->balance <= 0 ? 'status-paid' : 'status-active' }}">
                        {{ strtoupper((string)$goods->status) }}
                    </span>
                </td>
            </tr>
            @if($goods->payments->count() > 0)
            <tr>
                <td colspan="6" class="payment-container">
                    <div style="font-weight: bold; font-size: 9px; color: #333; margin-bottom: 3px;">Payment History Breakdown:</div>
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Payment Date</th>
                                <th style="width: 30%; text-align: right;" class="text-right">Amount Paid</th>
                                <th style="width: 45%;">Payment Source / Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($goods->payments as $payment)
                            <tr>
                                <td>
                                    {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                                    @if($payment->payroll_period_name)
                                        <br><small style="color: #6c757d; font-size: 8px;">({{ $payment->payroll_period_name }})</small>
                                    @endif
                                </td>
                                <td class="text-right">PHP {{ number_format((float)$payment->amount, 2) }}</td>
                                <td>
                                    <span class="{{ $payment->pay_id ? 'badge-payroll' : 'badge-manual' }}">
                                        {{ $payment->notes }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
            @endif
            @endforeach
            <tr class="summary-row">
                <td colspan="2">TOTAL GOODS PURCHASE</td>
                <td class="text-right">PHP {{ number_format((float)$data['goods_purchases']->sum('total_amount'), 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$data['goods_purchases']->sum('total_paid'), 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$data['goods_purchases']->sum('balance'), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endif

    <!-- Government Loans Section -->
    @if(isset($data['government_loans']) && $data['government_loans']->count() > 0)
    <div class="section-title">GOVERNMENT LOANS (SSS / Pag-IBIG)</div>
    <table>
        <thead>
            <tr>
                <th style="width: 20%;">Loan Type</th>
                <th style="width: 15%;">Loan Date</th>
                <th style="width: 20%;" class="text-right">Total Amount</th>
                <th style="width: 20%;" class="text-right">Monthly Amortization</th>
                <th style="width: 15%;" class="text-right">Balance</th>
                <th style="width: 10%;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['government_loans'] as $loan)
            <tr>
                <td>{{ strtoupper($loan->loanType->name ?? $loan->type ?? $loan->notes ?? 'Government Loan') }}</td>
                <td>{{ \Carbon\Carbon::parse($loan->start_date)->format('M d, Y') }}</td>
                <td class="text-right">PHP {{ number_format((float)$loan->principal_amount, 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$loan->monthly_deduction, 2) }}</td>
                <td class="text-right">PHP {{ number_format((float)$loan->balance, 2) }}</td>
                <td class="text-center">
                    <span class="status-badge {{ $loan->status == 'paid' || $loan->balance <= 0 ? 'status-paid' : 'status-active' }}">
                        {{ strtoupper((string)$loan->status) }}
                    </span>
                </td>
            </tr>
            @if($loan->payments->count() > 0)
            <tr>
                <td colspan="6" class="payment-container">
                    <div style="font-weight: bold; font-size: 9px; color: #333; margin-bottom: 3px;">Payment History Breakdown:</div>
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Payment Date</th>
                                <th style="width: 30%; text-align: right;" class="text-right">Amount Paid</th>
                                <th style="width: 45%;">Payment Source / Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->payments as $payment)
                            <tr>
                                <td>
                                    {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                                    @if($payment->payroll_period_name)
                                        <br><small style="color: #6c757d; font-size: 8px;">({{ $payment->payroll_period_name }})</small>
                                    @endif
                                </td>
                                <td class="text-right">PHP {{ number_format((float)$payment->amount, 2) }}</td>
                                <td>
                                    <span class="{{ $payment->pay_id ? 'badge-payroll' : 'badge-manual' }}">
                                        {{ $payment->notes }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
            @endif
            @endforeach
            <tr class="summary-row">
                <td colspan="2">TOTAL GOVERNMENT LOANS</td>
                <td class="text-right">PHP {{ number_format((float)$data['government_loans']->sum('principal_amount'), 2) }}</td>
                <td class="text-right">-</td>
                <td class="text-right">PHP {{ number_format((float)$data['government_loans']->sum('balance'), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endif

    <!-- No Data Message -->
    @if(
        (!isset($data['vale_loans']) || $data['vale_loans']->count() == 0) && 
        (!isset($data['goods_purchases']) || $data['goods_purchases']->count() == 0) && 
        (!isset($data['government_loans']) || $data['government_loans']->count() == 0)
    )
    <div class="no-data">
        No active or history loan records found for this employee.
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This is a computer-generated report. Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}</p>
    </div>
</body>
</html>
