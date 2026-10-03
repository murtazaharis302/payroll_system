<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMaster;
use App\Models\LoanRequest;
use App\Models\LoanType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanRequestController extends Controller
{
    private function companyId(): ?int
    {
        $id = getActiveCompanyId();
        return $id ? (int) $id : null;
    }

    /**
     * Employee Self-Service: View own loan requests & request form
     */
    public function employeeIndex()
    {
        $user = Auth::user();
        
        // Find employee by email or linked user
        $employee = EmployeeMaster::where('email', $user->email)->first();
        if (!$employee && $user->company_id) {
            $employee = EmployeeMaster::where('company_id', $user->company_id)->first();
        }

        $requests = collect();
        if ($employee) {
            $requests = LoanRequest::with('loanType')
                ->where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $loanTypes = LoanType::all();
        $employees = EmployeeMaster::where('status', 'active');
        if ($this->companyId()) {
            $employees->where('company_id', $this->companyId());
        }
        $employeesList = $employees->orderBy('first_name')->get();

        return view('employee.loan_requests.index', compact('employee', 'requests', 'loanTypes', 'employeesList'));
    }

    /**
     * Store new loan request
     */
    public function store(Request $request)
    {
        $request->validate([
            'employee_id'      => 'required|exists:employees_master,id',
            'loan_type_id'     => 'required|exists:loan_types,id',
            'requested_amount' => 'required|numeric|min:1',
        ]);

        LoanRequest::create([
            'employee_id'      => $request->employee_id,
            'loan_type_id'     => $request->loan_type_id,
            'requested_amount' => $request->requested_amount,
            'status'           => 'pending',
        ]);

        return redirect()->back()->with('status', 'Loan request submitted successfully and sent for approval.');
    }

    /**
     * HR / Admin Approval Queue View
     */
    public function approvalQueue()
    {
        $companyId = $this->companyId();
        
        $query = LoanRequest::with(['employee', 'loanType']);
        
        if ($companyId) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return view('admin.loan_requests.index', compact('requests'));
    }

    /**
     * HR / Admin Action: Approve or Reject Loan Request
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status'           => 'required|in:approved,rejected',
            'requested_amount' => 'nullable|numeric|min:1',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:1000',
        ]);

        $loanRequest = LoanRequest::findOrFail($id);

        if ($loanRequest->status !== 'pending') {
            return redirect()->back()->with('statusDanger', 'This loan request has already been processed.');
        }

        if ($request->status === 'approved') {
            if ($request->has('requested_amount') && $request->requested_amount > 0) {
                $loanRequest->requested_amount = $request->requested_amount;
            }
        } elseif ($request->status === 'rejected') {
            $loanRequest->rejection_reason = $request->rejection_reason;
        }

        $loanRequest->status = $request->status;
        $loanRequest->save();

        $msg = $request->status === 'approved' 
            ? 'Loan request approved! Ledger record created automatically.' 
            : 'Loan request rejected.';

        return redirect()->back()->with('status', $msg);
    }
}
