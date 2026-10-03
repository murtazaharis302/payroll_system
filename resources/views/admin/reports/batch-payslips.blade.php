<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Batch Payslips - {{ $period->period_name }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            size: A4 landscape;
            margin: 8mm 8mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #111;
        }

        /* ---- Grid: 2 columns x 2 rows per A4 landscape page (194mm printable height) ---- */
        .page-wrap {
            width: 100%;
            height: 194mm;
            max-height: 194mm;
            box-sizing: border-box;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .page-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .row-wrap {
            height: 95mm;
            max-height: 95mm;
            page-break-inside: avoid;
        }

        /* 140.5mm quadrant width, 95mm height. Centered vertical and horizontal alignment. */
        .slip-cell {
            width: 50%;
            height: 95mm;
            vertical-align: middle;
            text-align: center;
            padding: 0;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        .slip-cell:first-child {
            border-right: 1px dashed #aaa;
        }

        /* ---- Page divider between row 1 and row 2 ---- */
        .divider-row {
            height: 2mm;
            line-height: 2mm;
        }

        .divider-cell {
            padding: 0;
            height: 2mm;
            border-top: 1px dashed #aaa;
            line-height: 0;
            font-size: 0;
        }

        /* ---- Outer Payslip Footprint: ~116mm wide, height: auto (natural content-driven growth) ---- */
        .payslip {
            width: 116mm;
            max-width: 116mm;
            height: auto;
            margin: 0 auto;
            text-align: left;
            border: 1px solid #333;
            border-radius: 2px;
            padding: 4mm 4.5mm;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        /* Shift top row upward to increase clearance from horizontal cut line */
        .row-top .payslip {
            position: relative;
            top: -3mm;
        }

        /* Header */
        .ps-header {
            border-bottom: 1.5px solid #333;
            padding-bottom: 4px;
            margin-bottom: 5px;
            text-align: center;
        }
        .ps-company {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .ps-company-sub {
            font-size: 7px;
            color: #555;
        }
        .ps-title {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-top: 2px;
        }

        /* Employee info grid */
        .ps-emp {
            display: table;
            width: 100%;
            margin-bottom: 5px;
            font-size: 8px;
        }
        .ps-emp-row {
            display: table-row;
        }
        .ps-emp-l {
            display: table-cell;
            width: 50%;
            padding: 2px 0;
        }
        .ps-emp-r {
            display: table-cell;
            width: 50%;
            padding: 2px 0;
        }
        .emp-lbl { color: #666; }
        .emp-val { font-weight: bold; }

        /* Two-column income/deduction layout */
        .ps-body {
            display: table;
            width: 100%;
            border-top: 1px solid #ccc;
            padding-top: 5px;
            margin-bottom: 4px;
        }
        .ps-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .ps-col:first-child {
            padding-right: 4px;
            border-right: 1px solid #ddd;
        }
        .ps-col:last-child {
            padding-left: 4px;
        }

        .col-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            background: #e8e8e8;
            padding: 2.5px 4px;
            margin-bottom: 3px;
            border-radius: 1px;
        }

        .ps-line {
            display: table;
            width: 100%;
            padding: 2px 0;
            border-bottom: 1px dotted #e0e0e0;
        }
        .ps-line:last-child { border-bottom: none; }
        .ps-line-label {
            display: table-cell;
            font-size: 8px;
            color: #333;
        }
        .ps-line-value {
            display: table-cell;
            text-align: right;
            font-size: 8px;
            font-weight: bold;
            white-space: nowrap;
        }

        .subtotal-line {
            display: table;
            width: 100%;
            border-top: 1px solid #aaa;
            margin-top: 4px;
            padding-top: 2.5px;
        }
        .subtotal-lbl {
            display: table-cell;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #444;
        }
        .subtotal-val {
            display: table-cell;
            text-align: right;
            font-size: 8px;
            font-weight: bold;
        }

        /* Net Pay */
        .ps-netpay {
            margin-top: 5px;
            border-top: 1.5px solid #333;
            padding-top: 4px;
            display: table;
            width: 100%;
        }
        .netpay-lbl {
            display: table-cell;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .netpay-val {
            display: table-cell;
            text-align: right;
            font-size: 11px;
            font-weight: bold;
            color: #1a5c1a;
        }

        /* Footer */
        .ps-footer {
            margin-top: 5px;
            border-top: 1px solid #eee;
            padding-top: 3px;
            font-size: 6.5px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>

<?php
    $perPage = 4; // 2 columns x 2 rows
    $total   = count($payrolls);
    $pages   = ceil($total / $perPage);
?>

@for ($p = 0; $p < $pages; $p++)
    <?php $pagePayrolls = $payrolls->slice($p * $perPage, $perPage)->values(); ?>

    <div class="page-wrap" @if($p > 0) style="page-break-before: always;" @endif>
        <table class="page-table">
            {{-- Row 1 (slots 0 and 1) --}}
            <tr class="row-wrap row-top">
                <td class="slip-cell">
                    @if($pagePayrolls->get(0))
                        @include('admin.reports.payslip-card', ['payroll' => $pagePayrolls->get(0), 'company' => $company, 'period' => $period])
                    @endif
                </td>
                <td class="slip-cell">
                    @if($pagePayrolls->get(1))
                        @include('admin.reports.payslip-card', ['payroll' => $pagePayrolls->get(1), 'company' => $company, 'period' => $period])
                    @endif
                </td>
            </tr>

            <tr class="divider-row">
                <td colspan="2" class="divider-cell"></td>
            </tr>

            {{-- Row 2 (slots 2 and 3) --}}
            <tr class="row-wrap row-bottom">
                <td class="slip-cell">
                    @if($pagePayrolls->get(2))
                        @include('admin.reports.payslip-card', ['payroll' => $pagePayrolls->get(2), 'company' => $company, 'period' => $period])
                    @endif
                </td>
                <td class="slip-cell">
                    @if($pagePayrolls->get(3))
                        @include('admin.reports.payslip-card', ['payroll' => $pagePayrolls->get(3), 'company' => $company, 'period' => $period])
                    @endif
                </td>
            </tr>
        </table>
    </div>
@endfor

</body>
</html>
