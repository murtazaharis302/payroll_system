<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HRAdjustment;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use App\Models\DeductionType;
use App\Http\Requests\UpdateHrAdjustmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HRAdjustmentController extends Controller
{
    /**
     * Get company ID for current user
     */
    protected function getCompanyId()
    {
        return getActiveCompanyId(); // Uses helper function
    }

    /**
     * Display a listing of adjustments.
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        $query = HRAdjustment::with(['employee', 'incomeType', 'deductionType']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($request->wantsJson() || $request->expectsJson() || $request->ajax()) {
            $adjustments = (clone $query)->orderBy('effective_date', 'desc')->paginate(20);
            return response()->json($adjustments);
        }

        $allAdjustments = (clone $query)->orderBy('effective_date', 'desc')->get();
        $pendingCount   = (clone $query)->where('status', 'pending')->count();
        $approvedCount  = (clone $query)->where('status', 'approved')->count();
        $rejectedCount  = (clone $query)->where('status', 'rejected')->count();

        $employeesQuery = EmployeeMaster::where('status', 'active')->orderBy('last_name')->orderBy('first_name');
        if ($companyId) {
            $employeesQuery->where('company_id', $companyId);
        }
        $employees = $employeesQuery->get();

        $incomeTypes = IncomeType::orderBy('name')->get();
        $deductionTypes = DeductionType::orderBy('name')->get();

        $viewName = view()->exists('company.hr-adjustments.index')
            ? 'company.hr-adjustments.index'
            : 'admin.hr-adjustments.index';

        return view($viewName, compact(
            'allAdjustments',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
            'employees',
            'incomeTypes',
            'deductionTypes'
        ));
    }

    /**
     * Store a newly created adjustment.
     */
    public function store(UpdateHrAdjustmentRequest $request)
    {
        $validated = $request->validated();
        $companyId = $this->getCompanyId() ?? EmployeeMaster::findOrFail($validated['employee_id'])->company_id;

        $adjustment = new HRAdjustment();
        $adjustment->employee_id = $validated['employee_id'];
        $adjustment->company_id = $companyId;
        $adjustment->adjustment_type = $validated['type'];
        $adjustment->effective_date = $validated['effective_date'];
        $adjustment->reason = $validated['description'];
        $adjustment->notes = $validated['description'];
        $adjustment->status = $request->input('status', 'approved');
        $adjustment->created_by = Auth::id() ?? 1;

        if ($validated['type'] === 'income') {
            $adjustment->income_type_id = $validated['income_type_id'] ?? null;
            $adjustment->amount = $validated['amount'];
        } elseif ($validated['type'] === 'deduction') {
            $adjustment->deduction_type_id = $validated['deduction_type_id'] ?? null;
            $adjustment->amount = $validated['amount'];
        } elseif ($validated['type'] === 'attendance') {
            $adjustment->hours_worked = $validated['hours_worked'] ?? 0.00;
            $adjustment->overtime_hours = $validated['overtime_hours'] ?? 0.00;
        }

        $adjustment->save();

        if ($request->wantsJson() || $request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'HR Adjustment created successfully.',
                'data' => $adjustment
            ], 201);
        }

        return redirect()->back()->with('status', 'HR Adjustment submitted successfully.');
    }

    /**
     * Update the specified adjustment.
     */
    public function update(UpdateHrAdjustmentRequest $request, $id)
    {
        $adjustment = HRAdjustment::findOrFail($id);
        
        $companyId = $this->getCompanyId();
        if ($companyId && (int) $adjustment->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validated();

        $adjustment->employee_id = $validated['employee_id'];
        $adjustment->adjustment_type = $validated['type'];
        $adjustment->effective_date = $validated['effective_date'];
        $adjustment->reason = $validated['description'];
        $adjustment->notes = $validated['description'];

        if ($validated['type'] === 'income') {
            $adjustment->income_type_id = $validated['income_type_id'] ?? null;
            $adjustment->deduction_type_id = null;
            $adjustment->amount = $validated['amount'];
            $adjustment->hours_worked = null;
            $adjustment->overtime_hours = null;
        } elseif ($validated['type'] === 'deduction') {
            $adjustment->deduction_type_id = $validated['deduction_type_id'] ?? null;
            $adjustment->income_type_id = null;
            $adjustment->amount = $validated['amount'];
            $adjustment->hours_worked = null;
            $adjustment->overtime_hours = null;
        } elseif ($validated['type'] === 'attendance') {
            $adjustment->hours_worked = $validated['hours_worked'] ?? 0.00;
            $adjustment->overtime_hours = $validated['overtime_hours'] ?? 0.00;
            $adjustment->amount = null;
            $adjustment->income_type_id = null;
            $adjustment->deduction_type_id = null;
        }

        $adjustment->save();

        if ($request->wantsJson() || $request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'HR Adjustment updated successfully.',
                'data' => $adjustment
            ]);
        }

        return redirect()->back()->with('status', 'HR Adjustment updated successfully.');
    }

    /**
     * Approve the adjustment.
     */
    public function approve($id)
    {
        $adjustment = HRAdjustment::findOrFail($id);
        
        $companyId = $this->getCompanyId();
        if ($companyId && (int) $adjustment->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        $adjustment->status = 'approved';
        $adjustment->approved_by = Auth::id() ?? 1;
        $adjustment->approved_at = now();
        $adjustment->save();

        if (request()->wantsJson() || request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'message' => 'HR Adjustment approved successfully.',
                'data' => $adjustment
            ]);
        }

        return redirect()->back()->with('status', 'HR Adjustment approved successfully.');
    }

    /**
     * Delete the adjustment.
     */
    public function destroy($id)
    {
        $adjustment = HRAdjustment::findOrFail($id);
        
        $companyId = $this->getCompanyId();
        if ($companyId && (int) $adjustment->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        $adjustment->delete();

        if (request()->wantsJson() || request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'message' => 'HR Adjustment deleted successfully.'
            ]);
        }

        return redirect()->back()->with('status', 'HR Adjustment deleted successfully.');
    }
}
