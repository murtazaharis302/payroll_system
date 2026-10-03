<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpDeduction;
use App\Models\EmployeeMaster;
use App\Models\DeductionType;
use Illuminate\Http\Request;

class EmpDeductionController extends Controller
{
    protected function scopedEmployeesQuery()
    {
        $companyId = getActiveCompanyId();
        $query = EmployeeMaster::where('status', 'active');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->orderBy('last_name')->orderBy('first_name');
    }

    protected function scopedDeductionsQuery()
    {
        $companyId = getActiveCompanyId();
        $query = EmpDeduction::with(['employee', 'deductionType'])->orderBy('created_at', 'desc');

        if ($companyId) {
            $query->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $empDeductions = $this->scopedDeductionsQuery()->paginate($perPage)->withQueryString();

        return view('admin.emp_deduction.index', compact('empDeductions'));
    }

    public function create()
    {
        $employees = $this->scopedEmployeesQuery()->get();
        $deductionTypes = DeductionType::forCompany(getActiveCompanyId())->where('is_active', true)->orderBy('name')->get();

        return view('admin.emp_deduction.create', compact('employees', 'deductionTypes'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees_master,id',
            'deduction_type_id' => 'required|exists:deduction_types,id',
            'amount' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:monthly,semi-monthly,weekly,one-time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'effective_date' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $value = $validatedData['rate'] ?? $validatedData['amount'] ?? 0;
        $validatedData['rate'] = $value;
        $validatedData['amount'] = $value;

        $employee = EmployeeMaster::findOrFail($validatedData['employee_id']);
        authorizeCompanyAccess($employee->company_id);

        $typeValid = DeductionType::forCompany($employee->company_id)->where('id', $validatedData['deduction_type_id'])->exists();
        if (!$typeValid) {
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'Invalid or unauthorized deduction type selected for this company.');
        }

        $exists = EmpDeduction::where('employee_id', $validatedData['employee_id'])
            ->where('deduction_type_id', $validatedData['deduction_type_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'This deduction type is already assigned to the employee.');
        }

        EmpDeduction::create($validatedData);

        return redirect()->route('emp-deductions.index')->with('status', 'Employee Deduction Assigned Successfully.');
    }

    public function edit($id)
    {
        $empDeduction = EmpDeduction::with(['employee', 'deductionType'])->findOrFail($id);
        authorizeCompanyAccess($empDeduction->employee->company_id);

        $employees = $this->scopedEmployeesQuery()->get();
        $deductionTypes = DeductionType::forCompany(getActiveCompanyId())->where('is_active', true)->orderBy('name')->get();

        return view('admin.emp_deduction.edit', compact('empDeduction', 'employees', 'deductionTypes'));
    }

    public function update(Request $request, $id)
    {
        $empDeduction = EmpDeduction::with('employee')->findOrFail($id);
        authorizeCompanyAccess($empDeduction->employee->company_id);

        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees_master,id',
            'deduction_type_id' => 'required|exists:deduction_types,id',
            'amount' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:monthly,semi-monthly,weekly,one-time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'effective_date' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $value = $validatedData['rate'] ?? $validatedData['amount'] ?? $empDeduction->rate;
        $validatedData['rate'] = $value;
        $validatedData['amount'] = $value;

        $employee = EmployeeMaster::findOrFail($validatedData['employee_id']);
        authorizeCompanyAccess($employee->company_id);

        $typeValid = DeductionType::forCompany($employee->company_id)->where('id', $validatedData['deduction_type_id'])->exists();
        if (!$typeValid) {
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'Invalid or unauthorized deduction type selected for this company.');
        }

        $exists = EmpDeduction::where('employee_id', $validatedData['employee_id'])
            ->where('deduction_type_id', $validatedData['deduction_type_id'])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'This deduction type is already assigned to the employee.');
        }

        $empDeduction->update($validatedData);

        return redirect()->route('emp-deductions.index')->with('status', 'Employee Deduction Updated Successfully.');
    }

    public function destroy($id)
    {
        $empDeduction = EmpDeduction::with('employee')->findOrFail($id);
        authorizeCompanyAccess($empDeduction->employee->company_id);
        $empDeduction->delete();

        return redirect()->route('emp-deductions.index')->with('statusDanger', 'Employee Deduction Assignment Deleted Successfully.');
    }
}
