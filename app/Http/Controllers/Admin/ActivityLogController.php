<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    private function companyId(): ?int
    {
        $id = getActiveCompanyId();
        return $id ? (int) $id : null;
    }

    /**
     * Build base query with tenant and date range scoping
     */
    private function buildQuery(Request $request)
    {
        $user = Auth::user();
        $userRole = $user ? $user->role : 'admin';
        $companyId = $this->companyId();

        $query = ActivityLog::with(['user', 'company'])->orderBy('created_at', 'desc');

        if ($userRole === 'admin') {
            $selectedCompanyId = $request->input('company_id');
            if ($selectedCompanyId) {
                $query->where('company_id', $selectedCompanyId);
            }
        } else {
            // Company Admin / HR / Employee: strictly scope to company_id
            $effectiveCompanyId = $companyId ?? ($user ? $user->company_id : null);
            if ($effectiveCompanyId) {
                $query->where('company_id', $effectiveCompanyId);
            }
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        return $query;
    }

    /**
     * Display operational activity and audit logs
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $userRole = $user ? $user->role : 'admin';
        $companyId = $this->companyId();

        $allCompanies = collect();
        if ($userRole === 'admin') {
            $allCompanies = Company::where('status', '1')->orderBy('name')->get();
        }

        $selectedCompanyId = $request->input('company_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = $this->buildQuery($request);
        $logs = $query->paginate(25)->withQueryString();

        return view('admin.activity_logs.index', compact(
            'logs',
            'userRole',
            'allCompanies',
            'selectedCompanyId',
            'companyId',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Export activity logs audit report as downloadable CSV
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $userRole = $user ? $user->role : 'admin';

        $query = $this->buildQuery($request);

        // DATA VALIDATION: Check if data exists before downloading
        if ($query->count() === 0) {
            return back()->with('error', 'No activity log records found for the selected date range to download.');
        }

        $logs = $query->get();
        $filename = 'Audit_Log_Report_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($logs, $userRole) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header Row
            $columns = ['Log ID', 'Timestamp', 'User Name', 'User Email'];
            if ($userRole === 'admin') {
                $columns[] = 'Tenant Company';
            }
            $columns = array_merge($columns, ['Module', 'Action', 'Description', 'IP Address']);

            fputcsv($file, $columns);

            foreach ($logs as $log) {
                $row = [
                    $log->id,
                    $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A',
                    $log->user->name ?? 'System',
                    $log->user->email ?? 'N/A',
                ];
                if ($userRole === 'admin') {
                    $row[] = $log->company->name ?? 'Global / N/A';
                }
                $row[] = str_replace('_', ' ', $log->module);
                $row[] = $log->action;
                $row[] = $log->description ?? '';
                $row[] = $log->ip_address ?? '';

                fputcsv($file, $row);
            }

            fclose($file);
        }, 200, $headers);
    }
}
