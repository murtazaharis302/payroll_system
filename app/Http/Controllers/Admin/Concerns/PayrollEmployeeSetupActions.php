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

trait PayrollEmployeeSetupActions
{
    protected function getCompanyId()
    {
        return getActiveCompanyId();
    }

    protected function authorizeEmployeeAccess(EmployeeMaster $employee): void
    {
        authorizeCompanyAccess((int) $employee->company_id);
    }

    protected function employeeValidationRules(?int $employeeId = null): array
    {
        $emailRule = 'nullable|email|unique:employees_master,email';
        $codeRule = 'nullable|string|max:50|unique:employees_master,employee_code';

        if ($employeeId) {
            $emailRule .= ',' . $employeeId;
            $codeRule .= ',' . $employeeId;
        }

        return [
            'user_id' => 'nullable|exists:users,id',
            'company_id' => 'required|exists:companies,id',
            'employee_code' => $codeRule,
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => $emailRule,
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'date_hired' => 'nullable|date',
            'position' => 'nullable|string|max:255',
            'dept_id' => 'required|exists:departments,id',
            'sub_dept_id' => 'nullable|exists:sub_departments,id',
            'acct_num' => 'nullable|string|max:50',
            'atm_num' => 'nullable|string|max:50',
            'sss_number' => 'nullable|string|max:50',
            'philhealth_number' => 'nullable|string|max:50',
            'pagibig_number' => 'nullable|string|max:50',
            'tin_number' => 'nullable|string|max:50',
            'tax_code' => 'nullable|string|max:20',
            'remarks' => 'nullable|string|max:2000',
            'biometric_id' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive,resigned,pending_approval',
            'incomes' => 'nullable|array',
            'incomes.*.income_type_id' => 'required_with:incomes|integer|exists:income_types,id',
            'incomes.*.amount' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|array',
            'deductions.*.deduction_type_id' => 'required_with:deductions|integer|exists:deduction_types,id',
            'deductions.*.amount' => 'nullable|numeric|min:0',
        ];
    }

    protected function normalizeEmployeeData(array $data): array
    {
        $companyId = (int) ($data['company_id'] ?? 0);

        if (empty($data['dept_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dept_id' => ['Department is required before this employee can be included in payroll.'],
            ]);
        }

        $dept = Department::where('id', $data['dept_id'])
            ->where('company_id', $companyId)
            ->first();

        if (!$dept) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dept_id' => ['Department is required and must belong to the employee\'s company.'],
            ]);
        }

        $data['department'] = $dept->name;

        if (!empty($data['sub_dept_id'])) {
            $subDept = SubDepartment::where('id', $data['sub_dept_id'])
                ->where('company_id', $companyId)
                ->where('department_id', $data['dept_id'])
                ->first();

            if (!$subDept) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sub_dept_id' => ['The selected sub-department is invalid or does not belong to the selected department.'],
                ]);
            }
        }

        return $data;
    }

    protected function departmentsForCompany(?int $companyId)
    {
        if (!$companyId) {
            return collect();
        }

        return Department::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    protected function subDepartmentsForDepartment(?int $deptId)
    {
        if (!$deptId) {
            return collect();
        }

        return SubDepartment::where('department_id', $deptId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }



    public function create()
    {
        $currentUser = auth()->user();
        $companyId = $currentUser->company_id ?? $this->getCompanyId();

        if ($currentUser->role === 'admin') {
            $companies = Company::where('status', 1)->get();
            $selectedCompanyId = old('company_id', $companyId ?? $companies->first()?->id);
        } else {
            $companies = Company::where('id', $companyId)->get();
            $selectedCompanyId = $companyId;
        }

        // Fetch unlinked company employees (Users who do NOT have a Payroll Employee entry yet)
        $unlinkedEmployees = collect();
        if ($selectedCompanyId) {
            $existingMasterEmails = EmployeeMaster::where('company_id', $selectedCompanyId)
                ->whereNotNull('email')
                ->pluck('email')
                ->toArray();
            $existingEmpIds = EmployeeMaster::where('company_id', $selectedCompanyId)->pluck('id')->toArray();

            $unlinkedEmployees = User::where('company_id', $selectedCompanyId)
                ->whereIn('role_id', [3, 4]) // HR or Employee
                ->where(function ($q) use ($existingEmpIds) {
                    $q->whereNull('emp_id')
                      ->orWhereNotIn('emp_id', $existingEmpIds);
                })
                ->whereNotIn('email', $existingMasterEmails)
                ->with('employees')
                ->orderBy('name')
                ->get();
        }

        $departments = $this->departmentsForCompany($selectedCompanyId);
        $subDepartments = $this->subDepartmentsForDepartment(old('dept_id'));
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

        return view('admin.payroll_employee.create', compact(
            'companies', 'departments', 'subDepartments', 'taxCodes', 'selectedCompanyId', 'unlinkedEmployees', 'incomeTypes', 'deductionTypes'
        ));
    }

    protected function restrictHrAccess(): void
    {
        $user = auth()->user();
        if ($user && !\App\Models\MenuRoleAccess::hasModuleAccess($user, 'payroll_employees')) {
            abort(403, 'Unauthorized access to payroll employee management.');
        }
    }

    public function index(Request $request)
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
        $subDepartments = collect();
        if ($selectedCompanyId) {
            $employees = EmployeeMaster::where('company_id', $selectedCompanyId)
                ->orderBy('last_name')
                ->get();

            $departments = $this->departmentsForCompany($selectedCompanyId);
        }

        $taxCodes = config('tax_codes', []);
        $incomeTypes = IncomeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($selectedCompanyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $selectedCompanyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $deductionTypes = DeductionType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($selectedCompanyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $selectedCompanyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('admin.payroll_employee.employee_maintenance', compact(
            'companies', 'selectedCompanyId', 'employees', 'departments', 'subDepartments', 'taxCodes', 'incomeTypes', 'deductionTypes'
        ));
    }

}
