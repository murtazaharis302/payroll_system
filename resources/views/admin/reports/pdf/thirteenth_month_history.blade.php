<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>13th Month Payroll History - {{ $year }}</title>
    <style>
        * {
            box-sizing: border-box;
        }
        @page {
            size: A4 landscape;
            margin: 10mm 12mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #ffffff;
            color: #000000;
            margin: 0;
            padding: 0;
            font-size: 9px;
        }
        .report-content {
            padding: 10mm 12mm;
            box-sizing: border-box;
        }
        .header-container {
            margin-bottom: 10px;
        }
        .company-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .report-title {
            font-size: 11px;
            font-weight: bold;
            color: #333333;
        }
        .table-matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            page-break-inside: auto;
        }
        .table-matrix tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        .table-matrix th, .table-matrix td {
            border: 1px solid #333333;
            padding: 4px 5px;
            white-space: nowrap;
        }
        .table-matrix th {
            background-color: #e9ecef;
            color: #000000;
            font-weight: bold;
            text-align: center;
        }
        .text-start { text-align: left; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        @media print {
            .no-print { display: none !important; }
            body {
                margin: 0 !important;
                padding: 0 !important;
                background-color: #ffffff !important;
            }
            .report-content {
                padding: 10mm 12mm !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; padding: 10px; background: #333; color: #fff; margin-bottom: 10px;">
        <button onclick="window.print()" style="padding: 6px 16px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold;">
            Print / Save PDF
        </button>
        <button onclick="window.close()" style="padding: 6px 12px; background: #6c757d; color: white; border: none; cursor: pointer; border-radius: 4px; margin-left: 10px;">
            Close
        </button>
    </div>

    <div class="report-content">
        <div class="header-container">
            <div class="company-title">{{ $reportData['company']?->name ?? 'COMPANY PAYROLL' }}</div>
            <div class="report-title">13TH MONTH PAYROLL HISTORY REPORT - YEAR {{ $year }}</div>
        </div>

        <table class="table-matrix">
            <thead>
                <tr>
                    <th class="text-start" style="min-width: 180px;">NAME</th>
                    @foreach($reportData['cutoffs'] as $cData)
                        <th class="text-center">{{ $cData['short_label'] }}</th>
                    @endforeach
                    <th class="text-end">TOTAL</th>
                    <th class="text-end">13TH MONTH PAY</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['rows'] as $row)
                    <tr>
                        <td class="text-start fw-bold">{{ $row['employee_name'] }}</td>
                        @foreach($reportData['cutoffs'] as $pId => $cData)
                            @php $val = (float)($row['cutoff_values'][$pId] ?? 0); @endphp
                            <td class="text-end">{{ $val > 0 ? number_format($val, 2) : '0.00' }}</td>
                        @endforeach
                        <td class="text-end fw-bold">{{ number_format($row['total_basic'], 2) }}</td>
                        <td class="text-end fw-bold">{{ number_format($row['thirteenth_month_pay'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($reportData['cutoffs']) + 3 }}" class="text-center" style="padding: 15px;">
                            No approved payroll records found for {{ $year }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            if (window.location.search.includes('autoprint=1')) {
                window.print();
            }
        });
    </script>
</body>
</html>

