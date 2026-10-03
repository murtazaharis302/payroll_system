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

trait PayrollEmployeeMasterActions
{
    public function companyMaster(Request $request)
    {
        $this->restrictHrAccess();

        $currentUser = auth()->user();

        $requestedCompanyId = $request->input('company_id');


        if ($currentUser->role === 'admin' || $currentUser->role_id === \App\Models\Role::SUPER_ADMIN) {
            $companies = Company::where('status', 1)->orderBy('name')->get();
            $selectedCompanyId = $requestedCompanyId ? (int) $requestedCompanyId : ($this->getCompanyId() ?? $companies->first()?->id);
        } else {
            $companyId = $requestedCompanyId ? (int) $requestedCompanyId : ($this->getCompanyId() ?? $currentUser->company_id);
            if (!$companyId) {
                $comp = Company::where('user_id', $currentUser->id)->first();
                $companyId = $comp?->id;
            }
            $companies = Company::where('id', $companyId)->get();
            if ($companies->isEmpty()) {
                $companies = Company::where('status', 1)->orderBy('name')->get();
            }
            $selectedCompanyId = $companyId ?? $companies->first()?->id;
        }

        $employees = collect();
        $departments = collect();
        $payrollPeriods = collect();
        if ($selectedCompanyId) {
            $employees = EmployeeMaster::where('company_id', $selectedCompanyId)
                ->orderBy('last_name')
                ->get();

            $departments = $this->departmentsForCompany($selectedCompanyId);

            $payrollPeriods = PayrollPeriod::where('company_id', $selectedCompanyId)
                ->orderBy('period_start', 'desc')
                ->get();
        }

        $incomeTypes = IncomeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($selectedCompanyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $selectedCompanyId);
            })
            ->orderBy('name')
            ->get();

        $deductionTypes = DeductionType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($selectedCompanyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $selectedCompanyId);
            })
            ->orderBy('name')
            ->get();

        return view('admin.payroll_employee.company_master', compact(
            'companies', 'selectedCompanyId', 'employees', 'departments', 'payrollPeriods', 'incomeTypes', 'deductionTypes'
        ));
    }

    public function linkUserToEmployee(Request $request)
    {
        $currentUser = auth()->user();
        if (!\App\Models\MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_employees')) {
            abort(403, 'Unauthorized access to link user accounts to payroll records.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'employee_id' => 'required|exists:employees_master,id',
        ]);

        $user = User::withoutGlobalScopes()->findOrFail($validated['user_id']);
        $employee = EmployeeMaster::withoutGlobalScopes()->findOrFail($validated['employee_id']);

        authorizeCompanyAccess((int) $employee->company_id);

        if ($user->company_id && (int) $user->company_id !== (int) $employee->company_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_id' => ['User and Payroll Employee belong to different companies.'],
            ]);
        }

        // Prevent duplicate link if user already has an emp_id
        if (!empty($user->emp_id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_id' => ['User is already linked to a Payroll Employee record.'],
            ]);
        }

        // Prevent duplicate link if another user is already linked to this employee
        $existingLinkedUser = User::where('emp_id', $employee->id)->where('id', '!=', $user->id)->first();
        if ($existingLinkedUser) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'employee_id' => ['Payroll Employee record is already linked to another User.'],
            ]);
        }

        $user->update(['emp_id' => $employee->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'User account linked to Payroll Employee successfully.',
                'user' => $user,
                'employee' => $employee,
            ]);
        }

        return redirect()->to(panelRoute('payroll-employees.index'))
            ->with('status', 'User account linked to Payroll Employee successfully.');
    }

    public function getEmployeeDetailsAjax($id)
    {
        $this->restrictHrAccess();

        $employee = EmployeeMaster::with([
            'empIncomes.incomeType',
            'empDeductions.deductionType',
        ])->findOrFail($id);

        $this->authorizeEmployeeAccess($employee);

        $currentUser = auth()->user();
        $isHr = ($currentUser->role === 'hr' || $currentUser->role_id === \App\Models\Role::HR);

        $companyId = $employee->company_id;
        $incomeTypes = $isHr ? collect() : IncomeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $companyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $deductionTypes = DeductionType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $companyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $incomes = $isHr ? collect() : $employee->empIncomes
            ->map(function($inc) {
                return [
                    'id' => $inc->id,
                    'income_type_id' => $inc->income_type_id,
                    'name' => $inc->incomeType->name ?? 'Income',
                    'code' => $inc->incomeType->code ?? 'INC',
                    'amount' => (float) ($inc->amount ?? $inc->rate ?? 0),
                ];
            })->values();

        $deductions = $employee->empDeductions->map(function($dec) {
            return [
                'id' => $dec->id,
                'deduction_type_id' => $dec->deduction_type_id,
                'name' => $dec->deductionType->name ?? 'Deduction',
                'code' => $dec->deductionType->code ?? 'DED',
                'amount' => (float) ($dec->amount ?? $dec->rate ?? 0),
            ];
        })->values();

        $hasPayrollDraft = \App\Models\PayHeader::where('employee_id', $employee->id)->exists();

        $empData = [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'position' => $employee->position ?? 'N/A',
            'department' => $employee->department ?? 'N/A',
        ];

        if (!$isHr) {
            $empData['monthly_rate'] = (float) ($employee->monthly_rate ?? 0);
            $empData['daily_rate'] = (float) ($employee->daily_rate ?? 0);
            $empData['hourly_rate'] = (float) ($employee->hourly_rate ?? 0);
        }

        $responseData = [
            'status' => 'success',
            'has_payroll_draft' => $hasPayrollDraft,
            'employee' => $empData,
            'deductions' => $deductions,
            'deduction_types' => $deductionTypes,
        ];

        if (!$isHr) {
            $responseData['incomes'] = $incomes;
            $responseData['income_types'] = $incomeTypes;
        }

        return response()->json($responseData);
    }

    public function updateQuickMasterAjax(Request $request, $id)
    {
        $employee = EmployeeMaster::findOrFail($id);
        $this->authorizeEmployeeAccess($employee);

        $currentUser = auth()->user();
        $isHr = ($currentUser->role === 'hr' || $currentUser->role_id === \App\Models\Role::HR);

        $validated = $request->validate([
            'incomes' => 'nullable|array',
            'incomes.*.income_type_id' => 'required|exists:income_types,id',
            'incomes.*.amount' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|array',
            'deductions.*.deduction_type_id' => 'required|exists:deduction_types,id',
            'deductions.*.amount' => 'required|numeric|min:0',
        ]);

        $empCompanyId = $employee->company_id;

        // Validate Income Types scope
        if (!$isHr && isset($validated['incomes'])) {
            foreach ($validated['incomes'] as $incData) {
                $typeId = (int) $incData['income_type_id'];
                $isValidType = IncomeType::query()
                    ->where('id', $typeId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($empCompanyId) {
                        $q->whereNull('company_id')
                          ->orWhere('company_id', $empCompanyId);
                    })
                    ->exists();

                if (!$isValidType) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized or invalid income type for this company.'
                    ], 422);
                }
            }
        }

        // Validate Deduction Types scope
        if (isset($validated['deductions'])) {
            foreach ($validated['deductions'] as $decData) {
                $typeId = (int) $decData['deduction_type_id'];
                $isValidType = DeductionType::query()
                    ->where('id', $typeId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($empCompanyId) {
                        $q->whereNull('company_id')
                          ->orWhere('company_id', $empCompanyId);
                    })
                    ->exists();

                if (!$isValidType) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized or invalid deduction type for this company.'
                    ], 422);
                }
            }
        }

        // Base rates and incomes cannot be modified by HR in Quick Master
        if (!$isHr && isset($validated['incomes'])) {
            foreach ($validated['incomes'] as $incData) {
                $typeId = (int) $incData['income_type_id'];
                $amount = (float) ($incData['amount'] ?? 0);

                EmpIncome::updateOrCreate(
                    ['employee_id' => $employee->id, 'income_type_id' => $typeId],
                    ['amount' => $amount, 'rate' => $amount, 'is_active' => true]
                );
            }
        }

        // Sync deductions
        if (isset($validated['deductions'])) {
            foreach ($validated['deductions'] as $decData) {
                $typeId = (int) $decData['deduction_type_id'];
                $amount = (float) $decData['amount'];

                EmpDeduction::updateOrCreate(
                    ['employee_id' => $employee->id, 'deduction_type_id' => $typeId],
                    ['amount' => $amount, 'rate' => $amount, 'is_active' => true]
                );
            }
        }

        $employee->refresh();
        $empResponse = ['id' => $employee->id];
        if (!$isHr) {
            $empResponse['monthly_rate'] = (float) $employee->monthly_rate;
            $empResponse['daily_rate'] = (float) $employee->daily_rate;
            $empResponse['hourly_rate'] = (float) $employee->hourly_rate;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Company Master quick-entry updated successfully.',
            'employee' => $empResponse,
        ]);
    }

    public function getEmployeeFullDetailsAjax($id)
    {
        $this->restrictHrAccess();

        $employee = EmployeeMaster::with([
            'empIncomes.incomeType',
            'empDeductions.deductionType',
        ])->findOrFail($id);

        $this->authorizeEmployeeAccess($employee);

        $companyId = $employee->company_id;
        $incomeTypes = IncomeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $companyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $deductionTypes = DeductionType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $companyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $incomes = $employee->empIncomes
            ->map(function($inc) {
                return [
                    'id' => $inc->id,
                    'income_type_id' => $inc->income_type_id,
                    'name' => $inc->incomeType->name ?? 'Income',
                    'code' => $inc->incomeType->code ?? 'INC',
                    'amount' => (float) ($inc->amount ?? $inc->rate ?? 0),
                ];
            })->values();

        $deductions = $employee->empDeductions->map(function($dec) {
            return [
                'id' => $dec->id,
                'deduction_type_id' => $dec->deduction_type_id,
                'name' => $dec->deductionType->name ?? 'Deduction',
                'code' => $dec->deductionType->code ?? 'DED',
                'amount' => (float) ($dec->amount ?? $dec->rate ?? 0),
            ];
        })->values();

        $hasPayrollDraft = \App\Models\PayHeader::where('employee_id', $employee->id)->exists();

        $empData = [
            'id' => $employee->id,
            'company_id' => $employee->company_id,
            'employee_code' => $employee->employee_code,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'full_name' => $employee->full_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'date_of_birth' => $employee->date_of_birth ? $employee->date_of_birth->format('Y-m-d') : null,
            'date_hired' => $employee->date_hired ? $employee->date_hired->format('Y-m-d') : null,
            'position' => $employee->position ?? '',
            'status' => $employee->status ?? 'active',
            'dept_id' => $employee->dept_id,
            'sub_dept_id' => $employee->sub_dept_id,
            'department' => $employee->department ?? '',
            'acct_num' => $employee->acct_num ?? '',
            'atm_num' => $employee->atm_num ?? '',
            'biometric_id' => $employee->biometric_id ?? '',
            'sss_number' => $employee->sss_number ?? '',
            'philhealth_number' => $employee->philhealth_number ?? '',
            'pagibig_number' => $employee->pagibig_number ?? '',
            'tin_number' => $employee->tin_number ?? '',
            'tax_code' => $employee->tax_code ?? 'SINGLE',
            'remarks' => $employee->remarks ?? '',
            'monthly_rate' => (float) ($employee->monthly_rate ?? 0),
            'daily_rate' => (float) ($employee->daily_rate ?? 0),
            'hourly_rate' => (float) ($employee->hourly_rate ?? 0),
        ];

        return response()->json([
            'status' => 'success',
            'has_payroll_draft' => $hasPayrollDraft,
            'employee' => $empData,
            'incomes' => $incomes,
            'income_types' => $incomeTypes,
            'deductions' => $deductions,
            'deduction_types' => $deductionTypes,
        ]);
    }

}
