<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\ReportsHubActions;
use App\Http\Controllers\Admin\Concerns\ReportStatutoryActions;
use App\Http\Controllers\Admin\Concerns\ReportSummaryActions;
use App\Http\Controllers\Admin\Concerns\ReportPayrollHistoryActions;
use App\Http\Controllers\Admin\Concerns\ReportDeductionHistoryActions;
use App\Http\Controllers\Admin\Concerns\ReportBankActions;
use App\Http\Controllers\Admin\Concerns\ReportHistoryActions;
use App\Http\Controllers\Admin\Concerns\ReportIncomeActions;
use App\Http\Controllers\Admin\Concerns\ReportLedgerActions;
use App\Http\Controllers\Admin\Concerns\ReportExportActions;
use App\Http\Controllers\Admin\Concerns\ReportThirteenthMonthHistoryActions;

class ReportsController extends Controller
{
    use ReportsHubActions,
        ReportStatutoryActions,
        ReportSummaryActions,
        ReportPayrollHistoryActions,
        ReportDeductionHistoryActions,
        ReportBankActions,
        ReportHistoryActions,
        ReportIncomeActions,
        ReportLedgerActions,
        ReportExportActions,
        ReportThirteenthMonthHistoryActions;
}
