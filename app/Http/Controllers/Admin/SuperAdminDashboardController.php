<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        // Company Statistics
        $totalCompanies = Company::where('status', '1')->count();
        $pendingCompanies = Company::where('status', '0')->count();
        $deactivatedCompanies = Company::where('status', '2')->count();
        $totalEmployees = EmployeeMaster::count();
        $totalUsers = User::count();
        
        // Recent Companies
        $recentCompanies = Company::with('user')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Recent Users
        $recentUsers = User::with('company')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Company Registration Chart Data (Monthly)
        $companiesThisYear = Company::whereYear('created_at', now()->year)
            ->get(['created_at', 'status']);

        $formattedChartData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthRecords = $companiesThisYear->filter(
                fn ($c) => (int) $c->created_at->format('n') === $i
            );
            $formattedChartData[] = [
                'month'       => date('M', mktime(0, 0, 0, $i, 10)),
                'Active'      => $monthRecords->where('status', '1')->count(),
                'Pending'     => $monthRecords->where('status', '0')->count(),
                'Deactivated' => $monthRecords->where('status', '2')->count(),
            ];
        }

        // Tenant Single-Company Activity Log Filter
        $allCompanies = Company::where('status', '1')->orderBy('name')->get();
        $firstCompanyId = $allCompanies->first()?->id;
        $selectedCompanyId = $request->input('company_id', $firstCompanyId);

        $activityLogsQuery = \App\Models\ActivityLog::with(['user', 'company'])
            ->orderBy('created_at', 'desc');

        if ($selectedCompanyId) {
            $activityLogsQuery->where('company_id', $selectedCompanyId);
        }

        $activityLogs = $activityLogsQuery->take(5)->get();

        if ($request->ajax()) {
            $html = view('dashboards.super_admin', compact(
                'totalCompanies', 'pendingCompanies', 'deactivatedCompanies',
                'totalEmployees', 'totalUsers', 'recentCompanies', 'recentUsers',
                'formattedChartData', 'allCompanies', 'selectedCompanyId', 'activityLogs'
            ))->render();

            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);
            $cardsHtml = '';
            $tableHtml = '';

            if ($cardsNode = $xpath->query('//*[@id="cards-container"]')->item(0)) {
                $cardsHtml = $dom->saveHTML($cardsNode);
            }
            if ($tableNode = $xpath->query('//*[@id="dashboardtable"]')->item(0)) {
                $tableHtml = $dom->saveHTML($tableNode);
            }

            return response()->json([
                'cards'     => $cardsHtml,
                'table'     => $tableHtml,
                'chartData' => $formattedChartData,
            ]);
        }

        return view('dashboards.super_admin', compact(
            'totalCompanies', 'pendingCompanies', 'deactivatedCompanies',
            'totalEmployees', 'totalUsers', 'recentCompanies', 'recentUsers',
            'formattedChartData', 'allCompanies', 'selectedCompanyId', 'activityLogs'
        ));
    }
}

