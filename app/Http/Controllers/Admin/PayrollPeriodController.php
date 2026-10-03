<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PayrollPeriod;
use App\Models\Company;

class PayrollPeriodController extends Controller
{
    /**
     * Display listing of payroll periods
     */
    public function index(Request $request)
    {
        $companyId = getCompanyId();
        $perPage = (int) $request->input('per_page', 50);
        
        $periods = PayrollPeriod::with('company')
            ->when($companyId, function($query) use ($companyId) {
                return $query->where('company_id', $companyId);
            })
            ->orderBy('period_start', 'desc')
            ->paginate($perPage)
            ->withQueryString();
        
        return view('admin.payroll-periods.index', compact('periods'));
    }

    /**
     * Show form to create new period
     */
    /**
     * Show form to create new period
     */
    public function create()
    {
        $user = auth()->user();
        $isSuperAdmin = ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN);
        $companyId = getActiveCompanyId() ?? $user->company_id;
        
        if ($isSuperAdmin) {
            $companies = Company::where('status', 1)->orderBy('name')->get();
        } else {
            $companies = Company::where('id', $companyId)->where('status', 1)->get();
        }

        $companyLastPeriods = [];
        foreach ($companies as $comp) {
            $lastPeriod = PayrollPeriod::where('company_id', $comp->id)
                ->orderBy('period_end', 'desc')
                ->first();
            if ($lastPeriod && $lastPeriod->period_end) {
                $start = \Carbon\Carbon::parse($lastPeriod->period_end)->addDay();
                $end = $start->copy()->addDays(15);
                $companyLastPeriods[$comp->id] = [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                    'name' => $start->format('m/d/Y') . ' to ' . $end->format('m/d/Y'),
                    'pay_date' => $end->format('Y-m-d'),
                ];
            }
        }

        $selectedCompanyId = old('company_id', $companyId ?? $companies->first()?->id);
        $initialDefaults = $companyLastPeriods[$selectedCompanyId] ?? null;
        
        return view('admin.payroll-periods.create', compact('companies', 'isSuperAdmin', 'companyId', 'companyLastPeriods', 'initialDefaults'));
    }

    /**
     * Store new payroll period
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN);
        $companyId = getActiveCompanyId() ?? $user->company_id;

        if (!$isSuperAdmin && $companyId) {
            $request->merge(['company_id' => $companyId]);
        }

        if (!$request->filled('period_name') && $request->filled('period_start') && $request->filled('period_end')) {
            $start = \Carbon\Carbon::parse($request->input('period_start'))->format('m/d/Y');
            $end   = \Carbon\Carbon::parse($request->input('period_end'))->format('m/d/Y');
            $request->merge(['period_name' => "{$start} to {$end}"]);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'period_name' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'pay_date' => 'required|date',
            'status' => 'required|in:open,closed,completed',
        ]);
        
        if (!$isSuperAdmin && $companyId && (int)$validated['company_id'] !== (int)$companyId) {
            return back()->with('error', 'Unauthorized access to this company.');
        }
        
        PayrollPeriod::create($validated);
        
        logActivity('create', 'payroll_periods', 'Created payroll period: ' . $validated['period_name']);
        
        return redirect(panelRoute('payroll-periods.index'))
            ->with('success', 'Payroll period created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $period = PayrollPeriod::findOrFail($id);
        $user = auth()->user();
        $isSuperAdmin = ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN);
        
        // Check company access
        $companyId = getActiveCompanyId() ?? $user->company_id;
        if (!$isSuperAdmin && $companyId && (int)$period->company_id !== (int)$companyId) {
            abort(403, 'Unauthorized access.');
        }
        
        // Get companies for dropdown
        if ($isSuperAdmin) {
            $companies = Company::where('status', 1)->orderBy('name')->get();
        } else {
            $companies = Company::where('id', $companyId)->where('status', 1)->get();
        }
        
        return view('admin.payroll-periods.edit', compact('period', 'companies', 'isSuperAdmin'));
    }

    /**
     * Update payroll period
     */
    public function update(Request $request, $id)
    {
        $period = PayrollPeriod::findOrFail($id);
        $user = auth()->user();
        $isSuperAdmin = ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN);
        
        $companyId = getActiveCompanyId() ?? $user->company_id;
        if (!$isSuperAdmin && $companyId) {
            if ((int)$period->company_id !== (int)$companyId) {
                abort(403, 'Unauthorized access.');
            }
            $request->merge(['company_id' => $companyId]);
        }

        if (!$request->filled('period_name') && $request->filled('period_start') && $request->filled('period_end')) {
            $start = \Carbon\Carbon::parse($request->input('period_start'))->format('m/d/Y');
            $end   = \Carbon\Carbon::parse($request->input('period_end'))->format('m/d/Y');
            $request->merge(['period_name' => "{$start} to {$end}"]);
        }
        
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'period_name' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'pay_date' => 'required|date',
            'status' => 'required|in:open,closed,completed',
        ]);
        
        $period->update($validated);
        
        logActivity('update', 'payroll_periods', 'Updated payroll period: ' . $validated['period_name']);
        
        return redirect(panelRoute('payroll-periods.index'))
            ->with('success', 'Payroll period updated successfully.');
    }

    /**
     * Delete payroll period
     */
    public function destroy($id)
    {
        $period = PayrollPeriod::findOrFail($id);
        $user = auth()->user();
        $isSuperAdmin = ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN);

        // Check company access
        $companyId = getActiveCompanyId($user) ?? $user->company_id;
        if (!$isSuperAdmin && $companyId && (int)$period->company_id !== (int)$companyId) {
            abort(403, 'Unauthorized access.');
        }
        
        // Check if period has transactions / pay headers
        if ($period->payHeaders()->count() > 0) {
            return back()->with('error', 'Cannot delete period with existing payroll transactions.');
        }
        
        $periodName = $period->period_name;
        $period->delete();
        
        logActivity('delete', 'payroll_periods', 'Deleted payroll period: ' . $periodName);
        
        return redirect(panelRoute('payroll-periods.index'))
            ->with('success', 'Payroll period deleted successfully.');
    }

    /**
     * Quick create multiple periods (helper for setup)
     */
    public function quickCreate(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = ($user->role === 'admin' || $user->role_id === \App\Models\Role::SUPER_ADMIN);
        $companyId = getActiveCompanyId() ?? $user->company_id;

        if (!$isSuperAdmin && $companyId) {
            $request->merge(['company_id' => $companyId]);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'start_date' => 'required|date',
            'number_of_periods' => 'required|integer|min:1|max:24',
            'frequency' => 'required|in:bi-monthly,monthly,weekly',
        ]);
        
        if (!$isSuperAdmin && $companyId && (int)$validated['company_id'] !== (int)$companyId) {
            return back()->with('error', 'Unauthorized access to this company.');
        }
        
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $count = $validated['number_of_periods'];
        $frequency = $validated['frequency'];
        
        $created = 0;
        
        for ($i = 0; $i < $count; $i++) {
            if ($frequency === 'bi-monthly') {
                // First half of month
                if ($i % 2 === 0) {
                    PayrollPeriod::create([
                        'company_id' => $validated['company_id'],
                        'period_name' => 'Period ' . ($i + 1) . ' - ' . $startDate->format('F Y'),
                        'period_start' => $startDate->copy()->startOfMonth(),
                        'period_end' => $startDate->copy()->day(15),
                        'pay_date' => $startDate->copy()->day(16),
                        'status' => 'open',
                    ]);
                } else {
                    // Second half of month
                    PayrollPeriod::create([
                        'company_id' => $validated['company_id'],
                        'period_name' => 'Period ' . ($i + 1) . ' - ' . $startDate->format('F Y'),
                        'period_start' => $startDate->copy()->day(16),
                        'period_end' => $startDate->copy()->endOfMonth(),
                        'pay_date' => $startDate->copy()->addMonth()->startOfMonth(),
                        'status' => 'open',
                    ]);
                    $startDate->addMonth();
                }
            } elseif ($frequency === 'monthly') {
                PayrollPeriod::create([
                    'company_id' => $validated['company_id'],
                    'period_name' => 'Period ' . ($i + 1) . ' - ' . $startDate->format('F Y'),
                    'period_start' => $startDate->copy()->startOfMonth(),
                    'period_end' => $startDate->copy()->endOfMonth(),
                    'pay_date' => $startDate->copy()->addMonth()->startOfMonth(),
                    'status' => 'open',
                ]);
                $startDate->addMonth();
            }
            
            $created++;
        }
        
        logActivity('create', 'payroll_periods', "Quick created {$created} payroll periods");
        
        return redirect(panelRoute('payroll-periods.index'))
            ->with('success', "{$created} payroll periods created successfully.");
    }
}
