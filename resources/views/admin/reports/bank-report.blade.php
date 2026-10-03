<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bank Transmittal Letter & Direct Deposit Advice</title>
    <style>
        @page {
            size: letter portrait;
            margin: 1.5cm;
        }

        html, body, div, p, table, thead, tbody, tr, th, td {
            margin: 0;
            padding: 0;
        }

        * {
            box-sizing: border-box;
        }

        .report-page {
            padding: 0;
        }

        @media print {
            .report-page {
                padding: 0;
            }
        }

        body {
            font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #1a202c;
        }

        /* ── Letterhead Header ── */
        .letterhead {
            text-align: center;
            border-bottom: 2px solid #1a5276;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .letterhead-title {
            font-size: 14px;
            font-weight: bold;
            color: #1a5276;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .letterhead-subtitle {
            font-size: 8px;
            color: #4a5568;
            margin-top: 1px;
        }

        /* ── Metadata / Dates ── */
        .date-line {
            margin-bottom: 6px;
            font-weight: bold;
            color: #2d3748;
        }

        /* ── Recipient Block ── */
        .recipient {
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .recipient div {
            font-size: 9px;
        }
        .recipient .name {
            font-weight: bold;
            font-size: 9.5px;
        }

        /* ── Subject Line ── */
        .subject {
            font-weight: bold;
            font-size: 9.5px;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: #1a5276;
            background: #eaf4fb;
            padding: 3px 6px;
            border-left: 3px solid #1a5276;
        }

        /* ── Letter Body ── */
        .body-text {
            text-align: justify;
            margin-bottom: 8px;
            font-size: 9px;
            line-height: 1.3;
        }

        /* ── Account Table ── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            page-break-inside: auto;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e0;
            padding: 3px 5px;
            font-size: 8px;
            line-height: 1.2;
        }
        table.data-table th {
            background-color: #1a5276;
            color: #ffffff;
            font-weight: bold;
            font-size: 8.5px;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .account-number, .atm-number, .amount {
            white-space: nowrap;
        }
        .amount {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background-color: #feebc8;
            color: #742a2a;
            font-size: 8.5px;
        }

        /* ── Sign-off ── */
        .closing {
            margin-top: 10px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .signatory-section {
            margin-top: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .signatory-name {
            font-weight: bold;
            text-decoration: underline;
            font-size: 9.5px;
        }
        .signatory-pos {
            color: #4a5568;
            font-size: 8px;
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
<div class="report-page">

    <!-- Letterhead -->
    <div class="letterhead">
        <div class="letterhead-title">{{ $company->company_name ?? $company->name ?? 'COMPANY NAME' }}</div>
        <div class="letterhead-subtitle">
            {{ $company->address ?? 'Corporate Office Address' }} &nbsp;|&nbsp; 
            Tel: {{ $company->phone ?? 'N/A' }} &nbsp;|&nbsp; 
            Email: {{ $company->email ?? 'N/A' }}
        </div>
    </div>

    <!-- Date -->
    <div class="date-line">
        Date: {{ $letterDetails['date'] }}
    </div>

    <!-- Recipient Address -->
    <div class="recipient">
        <div class="name">{{ $letterDetails['manager_name'] }}</div>
        <div>Branch Manager / Officer-in-Charge</div>
        <div style="font-weight: bold; color:#1a5276;">{{ $letterDetails['bank_name'] }}</div>
        <div>{{ $letterDetails['branch_name'] ?? 'Main Branch Address' }}</div>
    </div>

    <!-- Subject -->
    <div class="subject">
        SUBJECT: AUTHORIZATION FOR BULK DIRECT PAYROLL DEPOSIT ({{ $fromPeriod->period_name }} @if(isset($toPeriod) && $toPeriod->id !== $fromPeriod->id) to {{ $toPeriod->period_name }} @endif)
    </div>

    <!-- Letter Body -->
    <div class="body-text">
        Dear Sir / Madam,<br><br>
        This serves as formal authorization to debit our corporate account 
        <strong>#{{ $letterDetails['company_acct'] }}</strong> maintained at your branch in the total amount of 
        <strong>PHP {{ number_format($letterDetails['total_net_pay'], 2) }}</strong> 
        (<em>{{ $letterDetails['amount_in_words'] }}</em>) and credit the individual net salaries into our employees' bank accounts listed below:
    </div>

    <!-- Employee Accounts Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No.</th>
                <th style="width: 140px;">Employee Account Name</th>
                <th style="width: 70px;" class="text-center">Employee ID</th>
                <th style="width: 90px;" class="text-center">ATM Card No.</th>
                <th style="width: 110px;" class="text-center">Bank Account Number</th>
                <th style="width: 95px;" class="text-right">Net Pay Amount (PHP)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ $row['name'] }}</strong></td>
                <td class="text-center">{{ $row['employee_code'] }}</td>
                <td class="text-center atm-number">{{ $row['atm_num'] }}</td>
                <td class="text-center account-number"><strong>{{ $row['acct_num'] }}</strong></td>
                <td class="amount"><strong>{{ number_format($row['net_pay'], 2) }}</strong></td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5" class="text-right">GRAND TOTAL AUTHORIZED DEBIT:</td>
                <td class="amount"><strong>PHP {{ number_format($letterDetails['total_net_pay'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Closing Paragraph -->
    <div class="body-text">
        Please find attached the certified payroll transmittal register for your reference. 
        Kindly acknowledge receipt of this letter and execute the fund transfer at your earliest convenience.
    </div>

    <!-- Sign-off Block -->
    <div class="closing">
        Sincerely yours,<br><br>
        
        <table class="signatory-section" style="width: 100%; border: none; margin: 0; padding: 0;">
            <tr style="border: none;">
                <td style="width: 50%; border: none; padding: 0; text-align: left; vertical-align: top;">
                    <div>
                        <span class="signatory-name">{{ $letterDetails['signatory_name'] }}</span><br>
                        <span class="signatory-pos">{{ $letterDetails['signatory_position'] }}</span><br>
                        <strong>{{ $company->company_name ?? $company->name ?? 'Company Name' }}</strong>
                    </div>
                </td>
                @if(!empty($letterDetails['signatory_2_name']))
                <td style="width: 50%; border: none; padding: 0; text-align: left; vertical-align: top;">
                    <div>
                        <span class="signatory-name">{{ $letterDetails['signatory_2_name'] }}</span><br>
                        <span class="signatory-pos">{{ $letterDetails['signatory_2_position'] }}</span><br>
                        <strong>{{ $company->company_name ?? $company->name ?? 'Company Name' }}</strong>
                    </div>
                </td>
                @endif
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}
        &nbsp;|&nbsp;
        HR &amp; Payroll Management System
        &nbsp;|&nbsp;
        Bank Direct Deposit Advice Form
    </div>
</div>
</body>
</html>
