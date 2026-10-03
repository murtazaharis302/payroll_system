<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Cash Loan Form - LN-{{ str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1a202c;
            background-color: #f1f5f9;
        }

        .no-print {
            text-align: center;
            padding: 10px;
            background-color: #1e293b;
            color: #ffffff;
            margin-bottom: 15px;
        }

        .no-print button, .no-print a {
            cursor: pointer;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 4px;
            border: 1px solid transparent;
            margin: 0 4px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-print {
            background-color: #0284c7;
            color: #ffffff;
        }

        .btn-excel {
            background-color: #16a34a;
            color: #ffffff;
        }

        .btn-close {
            background-color: transparent;
            color: #e2e8f0;
            border-color: #64748b !important;
        }

        .paper-container {
            max-width: 780px;
            margin: 20px auto 40px;
            background: #ffffff;
            padding: 24px 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-radius: 6px;
        }

        /* ── Header / Letterhead ── */
        .header-section {
            border-bottom: 2px solid #1a5276;
            padding-bottom: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .company-title {
            font-size: 16px;
            font-weight: bold;
            color: #1a5276;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-title {
            font-size: 11.5px;
            font-weight: bold;
            color: #4a5568;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .doc-meta {
            text-align: right;
            font-size: 9px;
            color: #4a5568;
        }

        .doc-ref {
            font-weight: bold;
            color: #1a5276;
            font-size: 10.5px;
        }

        /* ── Section Titles ── */
        .section-title {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1a5276;
            background-color: #eaf4fb;
            border-left: 3.5px solid #1a5276;
            padding: 3px 8px;
            margin: 10px 0 5px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* ── Summary Table ── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table.data-table th,
        table.data-table td {
            border: 1px solid #cbd5e0;
            padding: 4.5px 8px;
            font-size: 9px;
            line-height: 1.3;
        }

        table.data-table th {
            background-color: #f1f4f9;
            width: 34%;
            font-weight: 600;
            color: #2d3748;
            text-align: left;
        }

        table.data-table td {
            color: #1a202c;
        }

        .highlight-val {
            font-weight: bold;
            color: #1a5276;
        }

        /* ── Promissory Card ── */
        .promissory-card {
            background-color: #fafbfc;
            border: 1px solid #cbd5e0;
            border-left: 3.5px solid #1a5276;
            padding: 10px 12px;
            margin: 5px 0 12px;
            border-radius: 3px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .promissory-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #1a5276;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .promissory-text {
            font-size: 8.8px;
            line-height: 1.45;
            color: #2d3748;
            text-align: justify;
        }

        /* ── 2x2 Signatures ── */
        .signature-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            row-gap: 20px;
            margin-top: 16px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signature-col {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            border-top: 1.5px solid #2d3748;
            margin-top: 28px;
            padding-top: 4px;
            min-height: 1px;
        }

        .signature-title {
            font-size: 8.5px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            margin-top: 2px;
        }

        @media print {
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .paper-container {
                margin: 0 !important;
                padding: 10mm 12mm !important;
                max-width: 100% !important;
                width: 100% !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                box-sizing: border-box !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">Print Loan Form</button>
        <a href="{{ panelRoute('loan-monitor.export-excel', $loan->id) }}" class="btn-excel">Download Excel</a>
        <button onclick="window.close()" class="btn-close">Close</button>
    </div>

    <div class="paper-container">
        <!-- Part 1: Header & Summary Details -->
        <div class="header-section">
            <div>
                <div class="company-title">{{ $loan->employee?->company?->name ?? 'Company Payroll' }}</div>
                <div class="form-title">Cash Loan Application & Summary</div>
            </div>
            <div class="doc-meta">
                <div class="doc-ref">LN-{{ str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT) }}</div>
                <div>Date: {{ $loan->start_date ? \Carbon\Carbon::parse($loan->start_date)->format('M d, Y') : date('M d, Y') }}</div>
            </div>
        </div>

        <div class="section-title">Part 1: Loan Details & Summary</div>
        <table class="data-table">
            <tbody>
                <tr>
                    <th>Borrower Employee</th>
                    <td><strong>{{ $loan->employee?->full_name }}</strong> ({{ $loan->employee?->employee_code }})</td>
                </tr>
                <tr>
                    <th>Loan Type / Purpose</th>
                    <td>{{ $loan->purpose ?: ($loan->loanType?->name ?? 'Cash Advance') }}</td>
                </tr>
                <tr>
                    <th>Loan Date</th>
                    <td>{{ $loan->start_date ? \Carbon\Carbon::parse($loan->start_date)->format('F d, Y') : '—' }}</td>
                </tr>
                <tr>
                    <th>Principal Amount</th>
                    <td>₱{{ number_format((float) $loan->principal_amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Loan Term</th>
                    <td>{{ $loan->terms_months ?? 1 }} Month(s) ({{ ((int)($loan->terms_months ?? 1)) * 2 }} Paydays)</td>
                </tr>
                <tr>
                    <th>Total Loan Amount</th>
                    <td><strong>₱{{ number_format((float) $loan->total_amount, 2) }}</strong></td>
                </tr>
                <tr>
                    <th>Deduction Per Payday (Cutoff)</th>
                    <td><span class="highlight-val">₱{{ number_format((float) $loan->per_cutoff_amount, 2) }}</span></td>
                </tr>
            </tbody>
        </table>

        <!-- Part 2: Promissory Note & Signature Section -->
        <div class="section-title">Part 2: Promissory Note & Agreement</div>
        <div class="promissory-card">
            <div class="promissory-title">PROMISE TO PAY</div>
            <p class="promissory-text">
                For value received ₱{{ number_format((float) $loan->principal_amount, 2) }}, I, <strong>{{ $loan->employee?->full_name }}</strong>, promise to pay every payday the amount of ₱{{ number_format((float) $loan->per_cutoff_amount, 2) }} twice a month until the loan is settled. Any default in payment will cause an additional penalty charge of 1% per month on the principal amount.
            </p>
        </div>

        <div class="signature-grid">
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="signature-title">Prepared by</div>
            </div>
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="signature-title">Signature</div>
            </div>
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="signature-title">Approved by</div>
            </div>
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="signature-title">Checked by</div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            // Auto-trigger print dialog if opened in a dedicated print window
            if (window.location.search.includes('autoprint=1')) {
                window.print();
            }
        });
    </script>
</body>
</html>
