<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpIncome;
use App\Models\EmployeeMaster;
use App\Models\IncomeType;
use Illuminate\Http\Request;

class EmpIncomeController extends Controller
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

    protected function scopedIncomesQuery()
    {
        $companyId = getActiveCompanyId();
        $query = EmpIncome::with(['employee', 'incomeType'])
            ->orderBy('created_at', 'desc');

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
        $empIncomes = $this->scopedIncomesQuery()->paginate($perPage)->withQueryString();

        return view('admin.emp_income.index', compact('empIncomes'));
    }

    public function create()
    {
        $employees = $this->scopedEmployeesQuery()->get();
        $incomeTypes = IncomeType::forCompany(getActiveCompanyId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.emp_income.create', compact('employees', 'incomeTypes'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees_master,id',
            'income_type_id' => 'required|exists:income_types,id',
            'amount' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:monthly,semi-monthly,daily,hourly',
            'effective_date' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $value = $validatedData['rate'] ?? $validatedData['amount'] ?? 0;
        $validatedData['rate'] = $value;
        $validatedData['amount'] = $value;

        $employee = EmployeeMaster::findOrFail($validatedData['employee_id']);
        authorizeCompanyAccess($employee->company_id);

        $typeValid = IncomeType::forCompany($employee->company_id)
            ->where('id', $validatedData['income_type_id'])
            ->exists();
        if (!$typeValid) {
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'Invalid or unauthorized income type selected for this company.');
        }

        $exists = EmpIncome::where('employee_id', $validatedData['employee_id'])
            ->where('income_type_id', $validatedData['income_type_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'This income type is already assigned to the employee.');
        }

        EmpIncome::create($validatedData);

        return redirect()->route('emp-incomes.index')->with('status', 'Employee Income Assigned Successfully.');
    }

    public function edit($id)
    {
        $empIncome = EmpIncome::with(['employee', 'incomeType'])->findOrFail($id);
        authorizeCompanyAccess($empIncome->employee->company_id);

        $employees = $this->scopedEmployeesQuery()->get();
        $incomeTypes = IncomeType::forCompany(getActiveCompanyId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.emp_income.edit', compact('empIncome', 'employees', 'incomeTypes'));
    }

    public function update(Request $request, $id)
    {
        $empIncome = EmpIncome::with('employee')->findOrFail($id);
        authorizeCompanyAccess($empIncome->employee->company_id);

        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees_master,id',
            'income_type_id' => 'required|exists:income_types,id',
            'amount' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:monthly,semi-monthly,daily,hourly',
            'effective_date' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $value = $validatedData['rate'] ?? $validatedData['amount'] ?? $empIncome->rate;
        $validatedData['rate'] = $value;
        $validatedData['amount'] = $value;

        $employee = EmployeeMaster::findOrFail($validatedData['employee_id']);
        authorizeCompanyAccess($employee->company_id);

        $typeValid = IncomeType::forCompany($employee->company_id)
            ->where('id', $validatedData['income_type_id'])
            ->exists();
        if (!$typeValid) {
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'Invalid or unauthorized income type selected for this company.');
        }

        $exists = EmpIncome::where('employee_id', $validatedData['employee_id'])
            ->where('income_type_id', $validatedData['income_type_id'])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'This income type is already assigned to the employee.');
        }

        $empIncome->update($validatedData);

        return redirect()->route('emp-incomes.index')->with('status', 'Employee Income Updated Successfully.');
    }

    public function destroy($id)
    {
        $empIncome = EmpIncome::with('employee')->findOrFail($id);
        authorizeCompanyAccess($empIncome->employee->company_id);
        $empIncome->delete();

        return redirect()->route('emp-incomes.index')->with('statusDanger', 'Employee Income Assignment Deleted Successfully.');
    }
}
