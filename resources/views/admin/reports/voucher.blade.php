<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cash Vouchers - {{ $period->period_name }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 0.45in 0.25in 0.25in 0.25in;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 9px;
            color: #000000;
        }

        .voucher-page {
            height: 9.90in;
            max-height: 9.90in;
            position: relative;
            box-sizing: border-box;
            page-break-inside: avoid;
            page-break-after: avoid;
            overflow: hidden;
        }

        .voucher-half {
            height: 4.85in;
            max-height: 4.85in;
            position: relative;
            box-sizing: border-box;
            page-break-inside: avoid;
            padding: 12px 12px 12px 12px;
            overflow: hidden;
        }

        .voucher {
            height: 100%;
            width: 100%;
            box-sizing: border-box;
            position: relative;
        }

        .cut-line {
            border-bottom: 1px dashed #718096;
            margin: 0;
            text-align: center;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
        }

        .cut-label {
            position: absolute;
            top: -6px;
            right: 20px;
            background: #fff;
            padding: 0 6px;
            font-size: 7px;
            color: #718096;
        }

        /* Header Area */
        .voucher-header-area {
            padding-top: 4px;
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .voucher-title-main {
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            text-decoration: underline;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .date-label {
            font-size: 9px;
            text-align: right;
            white-space: nowrap;
        }

        .meta-field-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .meta-field-table td {
            font-size: 9px;
            padding: 3px 0;
        }

        /* Particulars Table */
        .particulars-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .particulars-table th {
            border-top: 3px double #000000;
            border-bottom: 1px solid #000000;
            padding: 5px 6px;
            font-size: 9px;
            font-style: italic;
            font-weight: normal;
        }

        .particulars-table td {
            padding: 5px 6px;
            vertical-align: top;
            font-size: 9px;
        }

        .particulars-body-row td {
            min-height: 140px;
            height: 140px;
        }

        .particulars-cell {
            border-right: 2px solid #000000;
            position: relative;
        }

        .amount-cell {
            width: 110px;
            text-align: right;
            position: relative;
        }

        .narrative-text {
            float: left;
            width: 55%;
            font-size: 9px;
            padding-top: 4px;
        }

        .breakdown-box {
            float: right;
            width: 42%;
            text-align: right;
            font-size: 8.5px;
            line-height: 1.35;
            padding-right: 4px;
        }

        .breakdown-item {
            white-space: nowrap;
        }

        .total-row td {
            border-top: 1px solid #000000;
            border-bottom: 2px solid #000000;
            padding: 5px 6px;
            font-weight: bold;
            font-size: 9px;
        }

        /* Receipt Narrative */
        .receipt-section {
            margin-top: 10px;
            margin-bottom: 14px;
            font-size: 9.5px;
            line-height: 1.7;
        }

        .underlined-text {
            border-bottom: 1px solid #000000;
            font-weight: bold;
            padding: 0 4px;
        }

        /* Signature Table */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
            padding-bottom: 2px;
        }

        .signature-table td {
            width: 50%;
            font-size: 9.5px;
            vertical-align: bottom;
            padding-bottom: 2px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @foreach($payrolls->chunk(2) as $voucherPair)
        <div class="voucher-page">
            @foreach($voucherPair as $payroll)
                <div class="voucher-half {{ $loop->first ? 'top-half' : 'bottom-half' }}">
                    <section class="voucher">
                        <div class="voucher-header-area">
                            <!-- Header -->
                            <table class="header-table">
                                <tr>
                                    <td style="width: 20%;"></td>
                                    <td class="voucher-title-main">C A S H &nbsp; V O U C H E R</td>
                                    <td class="date-label">Date: <span style="border-bottom: 1px solid #000; padding: 0 8px; font-weight: bold;">{{ $voucherDate }}</span></td>
                                </tr>
                            </table>

                            <!-- Recipient & Address -->
                            <table class="meta-field-table">
                                <tr>
                                    <td style="width: 60px;">Paid to:</td>
                                    <td style="border-bottom: 1px solid #000; font-weight: bold;">{{ strtoupper($payroll->employee->full_name) }}</td>
                                </tr>
                                <tr>
                                    <td>Address:</td>
                                    <td style="border-bottom: 1px solid #000;">{{ $payroll->employee->address ?? '' }}</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Particulars Grid -->
                        <table class="particulars-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">P A R T I C U L A R S</th>
                                    <th style="width: 110px; text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="particulars-body-row">
                                    <td class="particulars-cell">
                                        <div class="narrative-text">
                                            {{ $description }}
                                        </div>

                                        @if($displayBreakdown && !empty($payroll->voucher_particulars))
                                            <div class="breakdown-box">
                                                @foreach($payroll->voucher_particulars as $item)
                                                    <div class="breakdown-item">
                                                        {{ $item['name'] }} - <strong>P</strong> {{ number_format($item['amount'], 2) }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="amount-cell">
                                        <div style="padding-top: 4px;">
                                            {{ number_format($payroll->voucher_total_amount, 2) }}
                                        </div>
                                    </td>
                                </tr>
                                <tr class="total-row">
                                    <td style="text-align: right; font-style: italic;">Total:</td>
                                    <td style="text-align: right;">{{ number_format($payroll->voucher_total_amount, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Receipt Narrative -->
                        <div class="receipt-section">
                            RECEIVED from <span class="underlined-text">{{ $payroll->employee->dept->name ?? ($company->company_name ?? 'Management') }}</span> the amount of<br>
                            PESOS <span class="underlined-text">{{ $payroll->voucher_amount_words }}</span> (<strong>P</strong> <span class="underlined-text">{{ number_format($payroll->voucher_total_amount, 2) }}</span>)<br>
                            in full payment of amount described above.
                        </div>

                        <!-- Signatures -->
                        <table class="signature-table">
                            <tr>
                                <td style="text-align: left;">
                                    <span style="border-top: 1px solid #000; padding-top: 2px; width: 140px; display: inline-block; text-align: center;">Approved</span>
                                </td>
                                <td style="text-align: right;">
                                    By <span style="border-bottom: 1px solid #000; min-width: 160px; display: inline-block;">&nbsp;</span>
                                </td>
                            </tr>
                        </table>
                    </section>

                    @if(!$loop->last)
                        <div class="cut-line">
                            <span class="cut-label">CUT HERE FOR 2-UP SLIP</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
