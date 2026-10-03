<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayHeader;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\PayrollProcessingService;
use App\Services\PayrollWorkflowService;

class PayrollApprovalController extends Controller
{
    /**
     * Get company ID for current user
     */
    protected function getCompanyId()
    {
        return getActiveCompanyId(); // Uses helper function
    }
    
    /**
     * Display a listing of payroll drafts pending approval.
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = PayHeader::with(['employee', 'payrollPeriod', 'approvedBy']);
        
        if ($companyId) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        if ($request->has('payroll_period_id') && $request->payroll_period_id) {
            $query->where('payroll_period_id', $request->payroll_period_id);
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('status') && $request->status) {
            if ($request->status === 'pending_approval') {
                $query->where('status', 'pending_approval');
            } elseif ($request->status === 'approved') {
                $query->whereIn('status', ['approved', 'posted', 'paid']);
            } elseif ($request->status === 'rejected') {
                $query->where('status', 'rejected');
            } elseif ($request->status === 'draft') {
                $query->where('status', 'draft');
            }
        } else {
            $query->where('status', 'pending_approval'); // default: pending approval
        }

        $perPage = (int) $request->input('per_page', 50);
        $drafts = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        $pendingQuery = PayHeader::where('status', 'pending_approval');
        $approvedQuery = PayHeader::whereIn('status', ['approved', 'posted', 'paid']);
        $rejectedQuery = PayHeader::where('status', 'rejected');

        if ($companyId) {
            $pendingQuery->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
            $approvedQuery->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
            $rejectedQuery->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $pendingCount = $pendingQuery->count();
        $approvedCount = $approvedQuery->count();
        $rejectedCount = $rejectedQuery->count();

        $payrollPeriodsQuery = PayrollPeriod::query();
        if ($companyId) {
            $payrollPeriodsQuery->where('company_id', $companyId);
        }
        $payrollPeriods = $payrollPeriodsQuery->orderBy('period_start', 'desc')->get();

        $employeesQuery = EmployeeMaster::where('status', 'active');
        if ($companyId) {
            $employeesQuery->where('company_id', $companyId);
        }
        $employees = $employeesQuery->orderBy('last_name')->get();

        return view('admin.payroll-approvals.index', compact('drafts', 'payrollPeriods', 'pendingCount', 'approvedCount', 'rejectedCount', 'employees'));
    }

    /**
     * Show the review details page for a draft.
     */
    public function review($id)
    {
        $draft = PayHeader::with(['employee', 'payrollPeriod'])->findOrFail($id);
        
        $companyId = $this->getCompanyId();
        if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        $currentUser = Auth::user();
        $roleId = (int) ($currentUser->role_id ?? 0);

        $isCompanyAdmin = $roleId === \App\Models\Role::COMPANY_ADMIN;
        $isSuperAdmin   = $roleId === \App\Models\Role::SUPER_ADMIN
            || strtolower((string) ($currentUser->role ?? '')) === 'admin';
        $hasOverridePermission = \App\Models\MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_locked_edit_override')
            || \App\Models\MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_approvals');

        // Company Admin and Super Admin can always reopen approved payrolls
        $canViewApproved = ($draft->status === 'approved') && ($isCompanyAdmin || $isSuperAdmin || $hasOverridePermission);

        if ($draft->status !== 'pending_approval' && !$canViewApproved) {
            return back()->with('statusDanger', 'Only pending drafts (or approved drafts for authorized correction review) can be reviewed.');
        }

        $employee = $draft->employee;
        $period = $draft->payrollPeriod;
        $canReopen = ($draft->status === 'approved') && ($isCompanyAdmin || $isSuperAdmin || $hasOverridePermission);

        return view('admin.payroll-approvals.review', compact('draft', 'employee', 'period', 'canReopen', 'hasOverridePermission'));
    }

    /**
     * Reopen an approved payroll draft for correction.
     */
    public function reopen(Request $request, $id)
    {
        $currentUser = Auth::user();
        $roleId = (int) ($currentUser->role_id ?? 0);

        // Company Admin (role_id = 2) always has reopen permission
        // Also allow Super Admin and anyone with payroll_locked_edit_override access
        $isCompanyAdmin = $roleId === \App\Models\Role::COMPANY_ADMIN;
        $isSuperAdmin   = $roleId === \App\Models\Role::SUPER_ADMIN
            || strtolower((string) ($currentUser->role ?? '')) === 'admin';
        $hasOverride    = \App\Models\MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_locked_edit_override');
        $hasApproval    = \App\Models\MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_approvals');

        if (!$isCompanyAdmin && !$isSuperAdmin && !$hasOverride && !$hasApproval) {
            abort(403, 'Unauthorized access to reopen payroll drafts.');
        }

        $validated = $request->validate([
            'reopen_reason' => 'required|string|max:500',
        ]);

        $draft = PayHeader::withoutGlobalScopes()->with('employee')->findOrFail($id);

        $companyId = $this->getCompanyId();
        if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        if ($draft->status !== 'approved') {
            return back()->with('statusDanger', "Cannot reopen payroll. Only approved records can be reopened for correction (current status: '{$draft->status}').");
        }

        try {
            $workflow = app(PayrollWorkflowService::class);
            $workflow->reopenForCorrection($draft, $validated['reopen_reason'], $currentUser);

            $reviewRoute = \Illuminate\Support\Facades\Route::has('payroll-approvals.review')
                ? route('payroll-approvals.review', $draft->id)
                : route('admin.payroll-approvals.review', $draft->id);

            return redirect($reviewRoute)
                ->with('status', 'Payroll reopened for correction successfully. Status reset to Pending Approval.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (\App\Exceptions\InvalidStatusTransitionException | \InvalidArgumentException $e) {
            return back()->with('statusDanger', $e->getMessage());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Unexpected error while reopening payroll #{$draft->id} for correction: {$e->getMessage()}", [
                'exception' => $e,
                'draft_id' => $draft->id,
                'user_id' => $currentUser->id,
            ]);
            return back()->with('statusDanger', 'An unexpected error occurred while reopening the payroll draft. Please try again.');
        }
    }

    /**
     * Approve a payroll draft.
     */
    public function approve(Request $request, $id)
    {
        if (!\App\Models\MenuRoleAccess::hasModuleAccess(Auth::user(), 'payroll_approvals')) {
            abort(403, 'Unauthorized access to payroll approvals.');
        }

        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:500',
        ]);

        $draft = PayHeader::findOrFail($id);
        
        $companyId = $this->getCompanyId();
        if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        if ($draft->status !== 'pending_approval') {
            return back()->with('statusDanger', 'Only pending drafts can be approved.');
        }

        try {
            $service = app(PayrollProcessingService::class);
            $service->approvePayroll($draft, Auth::user());

            if (!empty($validated['approval_notes'])) {
                $draft->update(['notes' => $validated['approval_notes']]);
            }

            ActivityLog::log(
                'approve',
                'payroll_draft',
                "Approved payroll draft for employee",
                PayHeader::class,
                $draft->id
            );

            return redirect()->route('payroll-approvals.index')
                ->with('status', 'Payroll draft approved and locked successfully.');

        } catch (\Exception $e) {
            return back()->with('statusDanger', 'Failed to approve payroll: ' . $e->getMessage());
        }
    }

    /**
     * Reject a payroll draft.
     */
    public function reject(Request $request, $id)
    {
        if (!\App\Models\MenuRoleAccess::hasModuleAccess(Auth::user(), 'payroll_approvals')) {
            abort(403, 'Unauthorized access to payroll approvals.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $draft = PayHeader::withoutGlobalScopes()->with('employee')->findOrFail($id);
        
        $companyId = $this->getCompanyId();
        if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        if ($draft->status !== 'pending_approval') {
            return back()->with('statusDanger', 'Only pending drafts can be rejected.');
        }

        $workflow = app(PayrollWorkflowService::class);
        $workflow->transition($draft, PayrollWorkflowService::STATE_REJECTED, [
            'rejection_reason' => $validated['rejection_reason'],
            'user' => Auth::user(),
        ]);

        ActivityLog::log(
            'reject',
            'payroll_draft',
            "Rejected payroll draft for employee. Reason: " . $validated['rejection_reason'],
            PayHeader::class,
            $draft->id
        );

        return redirect()->route('payroll-approvals.index')
            ->with('status', 'Payroll draft rejected and sent back to HR.');
    }

    /**
     * Batch approve multiple drafts.
     */
    public function batchApprove(Request $request)
    {
        if (!\App\Models\MenuRoleAccess::hasModuleAccess(Auth::user(), 'payroll_approvals')) {
            abort(403, 'Unauthorized access to payroll approvals.');
        }

        $validated = $request->validate([
            'draft_ids' => 'required|array',
            'draft_ids.*' => 'exists:pay_headers,id',
        ]);

        $companyId = $this->getCompanyId();
        $service = app(PayrollProcessingService::class);
        $admin = Auth::user();

        $approvedCount = 0;
        DB::beginTransaction();
        try {
            foreach ($validated['draft_ids'] as $id) {
                $draft = PayHeader::findOrFail($id);
                
                if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
                    continue;
                }

                if ($draft->status === 'pending_approval') {
                    $service->approvePayroll($draft, $admin);
                    $approvedCount++;
                }
            }
            DB::commit();

            ActivityLog::log(
                'batch_approve',
                'payroll_draft',
                "Batch approved {$approvedCount} payroll drafts"
            );

            return back()->with('status', "Approved {$approvedCount} payroll drafts successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('statusDanger', 'Failed to batch approve: ' . $e->getMessage());
        }
    }

    /**
     * Batch reject multiple drafts.
     */
    public function batchReject(Request $request)
    {
        if (!\App\Models\MenuRoleAccess::hasModuleAccess(Auth::user(), 'payroll_approvals')) {
            abort(403, 'Unauthorized access to payroll approvals.');
        }

        $validated = $request->validate([
            'draft_ids' => 'required|array',
            'draft_ids.*' => 'exists:pay_headers,id',
            'rejection_reason' => 'required|string|max:500',
        ]);

        $companyId = $this->getCompanyId();
        $workflow = app(PayrollWorkflowService::class);

        $rejectedCount = 0;
        DB::beginTransaction();
        try {
            foreach ($validated['draft_ids'] as $id) {
                $draft = PayHeader::withoutGlobalScopes()->with('employee')->find($id);

                if (!$draft) {
                    continue;
                }
                
                if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
                    continue;
                }

                if ($draft->status === 'pending_approval') {
                    $workflow->transition($draft, PayrollWorkflowService::STATE_REJECTED, [
                        'rejection_reason' => $validated['rejection_reason'],
                        'user' => Auth::user(),
                    ]);
                    $rejectedCount++;
                }
            }
            DB::commit();

            ActivityLog::log(
                'batch_reject',
                'payroll_draft',
                "Batch rejected {$rejectedCount} payroll drafts"
            );

            return back()->with('status', "Rejected {$rejectedCount} payroll drafts successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('statusDanger', 'Failed to batch reject: ' . $e->getMessage());
        }
    }
}
