<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\SubDepartment;
use App\Models\Company;
use App\Models\User;
use App\Models\PayrollPeriod;
use App\Models\EmpIncome;
use App\Models\EmpDeduction;
use App\Models\IncomeType;
use App\Models\DeductionType;
use Illuminate\Http\Request;

trait PayrollEmployeeCrudActions
{
    protected function validateEmployeeForm(Request $request, array $rules): array
    {
        $messages = [
            'first_name.required' => 'First Name is required.',
            'last_name.required' => 'Last Name is required.',
            'company_id.required' => 'Company is required.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            $stepFields = [
                1 => [
                    'company_id', 'employee_code', 'first_name', 'middle_name', 'last_name',
                    'user_id', 'email', 'phone', 'date_of_birth', 'date_hired',
                    'position', 'dept_id', 'sub_dept_id', 'acct_num', 'atm_num', 'biometric_id',
                ],
                2 => [
                    'incomes', 'deductions',
                ],
                3 => [
                    'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number',
                    'tax_code', 'remarks',
                ],
            ];

            $failedStep = 1;
            foreach ($stepFields as $step => $fields) {
                if ($validator->errors()->hasAny($fields)) {
                    $failedStep = $step;
                    break;
                }
            }

            $response = back()
                ->withErrors($validator)
                ->withInput()
                ->with('employee_form_step', $failedStep)
                ->with('statusDanger', 'Please complete the highlighted required fields before saving the employee.');

            throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
        }

        return $validator->validated();
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();
        $userCompanyId = $currentUser->company_id ?? $this->getCompanyId();

        if ($currentUser->role !== 'admin' && $userCompanyId) {
            $request->merge(['company_id' => $userCompanyId]);
        }

        $validatedData = $this->validateEmployeeForm($request, $this->employeeValidationRules());

        authorizeCompanyAccess((int) $validatedData['company_id']);

        // Pre-validate target User BEFORE creating EmployeeMaster to avoid orphan records on failure
        $targetUser = null;
        if ($request->filled('user_id')) {
            $targetUser = User::find($request->input('user_id'));
            if (!$targetUser) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_id' => ['The selected user ID is invalid.'],
                ]);
            }
            if (!empty($targetUser->emp_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_id' => ['User is already linked to a Payroll Employee record.'],
                ]);
            }
            if ($targetUser->company_id && (int) $targetUser->company_id !== (int) $validatedData['company_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_id' => ['User and Payroll Employee belong to different companies.'],
                ]);
            }
        }

        $incomes = $validatedData['incomes'] ?? $request->input('incomes', []);
        $deductions = $validatedData['deductions'] ?? $request->input('deductions', []);
        unset($validatedData['incomes'], $validatedData['deductions']);

        $companyId = (int) $validatedData['company_id'];

        // 1. Enforce Company Scope & Active Status on submitted Income Types
        $resolvedIncomes = [];
        if (!empty($incomes) && is_array($incomes)) {
            foreach ($incomes as $incData) {
                if (empty($incData['income_type_id'])) continue;
                $incTypeId = (int) $incData['income_type_id'];

                $incType = IncomeType::withoutGlobalScopes()
                    ->where('id', $incTypeId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($companyId) {
                        $q->whereNull('company_id')
                          ->orWhere('company_id', $companyId);
                    })
                    ->first();

                if (!$incType) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'incomes' => ['The selected income type does not belong to this company or is inactive.'],
                    ]);
                }

                $resolvedIncomes[] = [
                    'income_type' => $incType,
                    'amount' => (float) ($incData['amount'] ?? 0),
                ];
            }
        }

        // 2. Enforce Company Scope & Active Status on submitted Deduction Types
        $resolvedDeductions = [];
        if (!empty($deductions) && is_array($deductions)) {
            foreach ($deductions as $decData) {
                if (empty($decData['deduction_type_id'])) continue;
                $decTypeId = (int) $decData['deduction_type_id'];

                $decType = DeductionType::withoutGlobalScopes()
                    ->where('id', $decTypeId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($companyId) {
                        $q->whereNull('company_id')
                          ->orWhere('company_id', $companyId);
                    })
                    ->first();

                if (!$decType) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'deductions' => ['The selected deduction type does not belong to this company or is inactive.'],
                    ]);
                }

                $resolvedDeductions[] = [
                    'deduction_type' => $decType,
                    'amount' => (float) ($decData['amount'] ?? 0),
                ];
            }
        }

        $validatedData = $this->normalizeEmployeeData($validatedData);

        if (empty($validatedData['employee_code'])) {
            $validatedData['employee_code'] = EmployeeMaster::generateNextEmployeeCode((int) $validatedData['company_id']);
        }

        if ($currentUser->role === 'hr' || $currentUser->role_id === \App\Models\Role::HR) {
            $validatedData['status'] = 'pending_approval';
            $msg = 'Payroll Employee created and submitted for Company Admin approval.';
        } else {
            $validatedData['status'] = $validatedData['status'] ?? 'active';
            $msg = 'Payroll Employee Created Successfully.';
        }

        // Create EmployeeMaster, link User, and persist initial recurring incomes/deductions inside an atomic transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($validatedData, $targetUser, $resolvedIncomes, $resolvedDeductions) {
            $employeeMaster = EmployeeMaster::create($validatedData);
            if ($targetUser) {
                $targetUser->update(['emp_id' => $employeeMaster->id]);
            }

            foreach ($resolvedIncomes as $item) {
                $incType = $item['income_type'];
                $amt = $item['amount'];
                $frequency = ($incType->system_key === 'overtime' || strtoupper((string)$incType->code) === 'OVERTIME')
                    ? 'per_occurrence'
                    : 'monthly';

                EmpIncome::updateOrCreate(
                    ['employee_id' => $employeeMaster->id, 'income_type_id' => $incType->id],
                    ['rate' => $amt, 'amount' => $amt, 'is_active' => true, 'frequency' => $frequency]
                );
            }

            foreach ($resolvedDeductions as $item) {
                $decType = $item['deduction_type'];
                $amt = $item['amount'];

                EmpDeduction::updateOrCreate(
                    ['employee_id' => $employeeMaster->id, 'deduction_type_id' => $decType->id],
                    ['rate' => $amt, 'amount' => $amt, 'is_active' => true, 'frequency' => 'per_payroll']
                );
            }
        });

        return redirect()->to(panelRoute('payroll-employees.index'))
            ->with('status', $msg);
    }

    public function approve($id)
    {
        $currentUser = auth()->user();
        if (!in_array($currentUser->role, ['admin', 'company']) && $currentUser->role_id !== \App\Models\Role::SUPER_ADMIN && $currentUser->role_id !== \App\Models\Role::COMPANY_ADMIN) {
            abort(403, 'Only Company Admin or Super Admin can approve payroll employees.');
        }

        $employee = EmployeeMaster::findOrFail($id);
        $this->authorizeEmployeeAccess($employee);

        $employee->update(['status' => 'active']);

        return redirect()->to(panelRoute('payroll-employees.index'))
            ->with('status', 'Payroll Employee approved successfully.');
    }

    public function edit($id)
    {
        $employee = EmployeeMaster::findOrFail($id);
        $this->authorizeEmployeeAccess($employee);

        $companyId = $this->getCompanyId();

        $companiesQuery = company::where('status', 1);
        if ($companyId) {
            $companiesQuery->where('id', $companyId);
        }
        $companies = $companiesQuery->get();

        $selectedCompanyId = old('company_id', $employee->company_id);
        $departments = $this->departmentsForCompany($selectedCompanyId);
        $subDepartments = $this->subDepartmentsForDepartment(old('dept_id', $employee->dept_id));
        $taxCodes = config('tax_codes', []);

        $incomeTypes = IncomeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($selectedCompanyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $selectedCompanyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'system_key']);

        $deductionTypes = DeductionType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($selectedCompanyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $selectedCompanyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'system_key']);

        return view('admin.payroll_employee.edit', compact(
            'employee', 'companies', 'departments', 'subDepartments', 'taxCodes', 'selectedCompanyId', 'incomeTypes', 'deductionTypes'
        ));
    }

    public function update(Request $request, $id)
    {
        $employee = EmployeeMaster::findOrFail($id);
        $this->authorizeEmployeeAccess($employee);

        $validatedData = $this->validateEmployeeForm($request, array_merge(
            $this->employeeValidationRules($id),
            ['status' => 'nullable|in:active,inactive,resigned']
        ));

        if (empty($validatedData['status'])) {
            $validatedData['status'] = $employee->status ?? 'active';
        }

        $companyId = $this->getCompanyId();
        if ($companyId) {
            $validatedData['company_id'] = $companyId;
        }

        authorizeCompanyAccess((int) $validatedData['company_id']);

        $validatedData = $this->normalizeEmployeeData($validatedData);

        // Lock rates: rates cannot be updated after employee creation
        unset($validatedData['monthly_rate'], $validatedData['daily_rate'], $validatedData['hourly_rate']);

        $employee->update($validatedData);

        return redirect()->to(panelRoute('payroll-employees.index'))
            ->with('status', 'Payroll Employee Updated Successfully.');
    }

    public function destroy($id)
    {
        $employee = EmployeeMaster::findOrFail($id);
        $this->authorizeEmployeeAccess($employee);
        $employee->delete();

        return redirect()->to(panelRoute('payroll-employees.index'))
            ->with('statusDanger', 'Payroll Employee Deleted Successfully.');
    }

    public function show($id)
    {
        $employee = EmployeeMaster::with([
            'company', 'dept', 'subDepartment',
            'empIncomes.incomeType', 'empDeductions.deductionType',
            'empLoans.loanType',
        ])->findOrFail($id);

        $this->authorizeEmployeeAccess($employee);

        $taxCodes = config('tax_codes', []);

        return view('admin.payroll_employee.show', compact('employee', 'taxCodes'));
    }

    public function getDepartmentsByCompany(Request $request)
    {
        $companyId = $request->input('company_id');

        if (!$companyId) {
            return response()->json(['departments' => []]);
        }

        authorizeCompanyAccess((int) $companyId);

        $departments = Department::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json(['departments' => $departments]);
    }

    public function getSubDepartmentsByDepartment(Request $request)
    {
        $departmentId = $request->input('department_id');
        $companyId = $request->input('company_id');

        if (!$departmentId || !$companyId) {
            return response()->json(['sub_departments' => []]);
        }

        authorizeCompanyAccess((int) $companyId);

        $subDepartments = SubDepartment::where('department_id', $departmentId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json(['sub_departments' => $subDepartments]);
    }

}
