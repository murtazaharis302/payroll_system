<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use App\Services\EmployeeProvisioningService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Get company ID for current user
     */
    protected function getCompanyId()
    {
        return getActiveCompanyId();
    }

    public function redirectToEmployeeLogin()
    {
        return redirect('employee/login');
    }

    public function showLoginForm()
    {
        $loginRoute = 'employeeLogin';
        $portalType = 'employee';
        $portalTitle = 'Employee Portal';
        $portalDescription = 'Access your employee dashboard to view your attendance, payroll, and manage your personal information.';

        if (request()->is('hr/login') || request()->is('hr')) {
            $loginRoute = 'hrLogin';
            $portalType = 'hr';
            $portalTitle = 'HR Portal';
            $portalDescription = 'Manage payroll employees, attendance, vale/loans, and goods purchases for your company.';
        }

        return view('employee.login', compact('loginRoute', 'portalType', 'portalTitle', 'portalDescription'));
    }

    protected function restrictHrUserManagement(): void
    {
        $user = auth()->user();
        if ($user && !\App\Models\MenuRoleAccess::hasModuleAccess($user, 'employees')) {
            abort(403, 'Access denied. User management is restricted.');
        }
    }

    public function index(Request $request)
    {
        $this->restrictHrUserManagement();
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->role === 'admin' || $currentUser->role_id === \App\Models\Role::SUPER_ADMIN;

        $query = User::with(['company', 'employees', 'payrollMaster']);

        if (! $isSuperAdmin) {
            $companyId = getActiveCompanyId() ?? $currentUser->company_id;
            if (! $companyId) {
                $comp = Company::where('user_id', $currentUser->id)->first();
                $companyId = $comp?->id;
            }
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
        }

        // Only fetch Employees and HR (exclude Admin / Super Admin and Company Admin / App Owners)
        $query->where(function ($q) {
            $q->whereIn('role_id', [\App\Models\Role::HR, \App\Models\Role::EMPLOYEE]);
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'role')) {
                $q->orWhereIn('role', ['hr', 'employee']);
            }
        });

        // Filter 1: Company filter
        if ($request->filled('company_id')) {
            $companyIdFilter = (int) $request->input('company_id');
            $query->where('company_id', $companyIdFilter);
        }

        // Filter 2: Role filter (HR, Employee)
        if ($request->filled('role')) {
            $roleFilter = strtolower($request->input('role'));
            $roleMap = [
                'hr' => 3,
                'employee' => 4,
            ];
            $roleIdFilter = $roleMap[$roleFilter] ?? null;

            if ($roleIdFilter) {
                $query->where(function ($q) use ($roleFilter, $roleIdFilter) {
                    $q->where('role_id', $roleIdFilter);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'role')) {
                        $q->orWhere('role', $roleFilter);
                    }
                });
            }
        }

        $perPage = (int) $request->input('per_page', 50);
        $employees = $query->orderBy('name')->paginate($perPage)->withQueryString();

        $companies = collect();
        if ($isSuperAdmin) {
            $companies = Company::where('status', '1')->orderBy('name')->get();
        }

        return view('admin.employee.index', compact('employees', 'companies'));
    }

    public function create()
    {
        $this->restrictHrUserManagement();
        $companies = collect();
        if (auth()->user()->role === 'admin') {
            $companies = Company::where('status', '1')->orderBy('name')->get();
        }

        return view('admin.employee.create', compact('companies'));
    }

    public function store(Request $request, EmployeeProvisioningService $provisioningService)
    {
        $this->restrictHrUserManagement();
        $isAdmin = auth()->user()->role === 'admin';

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required',
            'role' => 'required|in:hr,employee',
            'address' => 'required',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];

        if ($isAdmin) {
            $rules['company_id'] = 'required|exists:companies,id';
        } else {
            $rules['company_id'] = 'prohibited';
        }

        $validatedData = $request->validate($rules);
        $validatedData['age'] = $request->input('age');

        if ($isAdmin) {
            $companyId = (int) $validatedData['company_id'];
            $company = Company::find($companyId);
            $ownerUserId = $company?->user_id ?? auth()->id();
        } else {
            $companyId = getActiveCompanyId() ?? auth()->user()->company_id;
            $ownerUserId = auth()->id();
        }

        $provisioningService->provision($validatedData, $ownerUserId, $companyId);

        return redirect()->route('employee')->with('status', 'Employee Created Successfully.');
    }

    public function edit($id)
    {
        $this->restrictHrUserManagement();

        $user = User::findOrFail($id);
        authorizeCompanyAccess($user->company_id);

        $employee = Employee::with('employee')->where('e_user_id', $user->id)->first();
        if (!$employee) {
            $employee = new Employee([
                'e_user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'owner_user_id' => $user->company?->user_id ?? auth()->id(),
            ]);
            $employee->setRelation('employee', $user);
        }

        $companies = collect();
        if (auth()->user()->role === 'admin' || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN) {
            $companies = Company::where('status', '1')->orderBy('name')->get();
        }

        return view('admin.employee.edit', compact('employee', 'companies'));
    }

    public function update($id, Request $request)
    {
        $this->restrictHrUserManagement();
        $isAdmin = auth()->user()->role === 'admin' || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$id,
            'phone' => 'required',
            'address' => 'required',
            'role' => 'required|in:hr,employee',
        ];

        if ($isAdmin) {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $validatedData = $request->validate($rules);

        $user = User::findOrFail($id);
        authorizeCompanyAccess($user->company_id);

        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];
        $user->phone = $validatedData['phone'];
        $user->role = $validatedData['role'];
        if ($isAdmin && isset($validatedData['company_id'])) {
            $user->company_id = $validatedData['company_id'];
        }
        $user->update();

        $employee = Employee::where('e_user_id', $id)->first();
        if ($employee) {
            if ($isAdmin && isset($validatedData['company_id'])) {
                $company = Company::find($validatedData['company_id']);
                if ($company && $company->user_id) {
                    $employee->owner_user_id = $company->user_id;
                }
            }
            $employee->age = $request->input('age');
            $employee->address = $validatedData['address'];
            $employee->designation = ucfirst($validatedData['role']);
            $employee->update();
        }

        return redirect()->route('employee')->with('status', 'Employee Record Updated Successfully.');
    }

    public function destroy($id)
    {
        $this->restrictHrUserManagement();

        $userCompanyId = (int) (session('company_id') ?? auth()->user()->company_id ?? 0);
        $isSuperAdmin = (int) auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN;

        // Scoped employee/user lookup
        $employee = Employee::find($id);
        $user = null;

        if ($employee) {
            $user = User::find($employee->e_user_id);
        } else {
            $user = User::find($id);
            if ($user) {
                $employee = Employee::where('e_user_id', $user->id)->first();
            }
        }

        if (! $user && ! $employee) {
            abort(404, 'Employee record not found.');
        }

        // Authorize with policy checks
        if ($employee) {
            $this->authorize('delete', $employee);
        }
        if ($user && $user->payrollMaster) {
            $this->authorize('delete', $user->payrollMaster);
        }

        if ($employee) {
            $employee->delete();
        }

        if ($user) {
            Permission::where('user_id', $user->id)->delete();
            \App\Models\UserCompanyAccess::where('user_id', $user->id)->delete();
            if ($user->emp_id) {
                $master = \App\Models\EmployeeMaster::find($user->emp_id);
                if ($master) {
                    $master->delete();
                }
            }
            $user->delete();
        }

        return redirect()->route('employee')->with('statusDanger', 'Employee Deleted Successfully.');
    }

    /**
     * AJAX search endpoint for Select2 employee search.
     * Features:
     * - Real-time search by Employee ID (employee_code), First Name, Last Name, or Department.
     * - Formats text as: [EMP-ID] Full Name — Department.
     * - AJAX lazy-loading (limit: 25 per page).
     */
    public function ajaxSearch(Request $request)
    {
        $q = trim($request->input('q', ''));
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 25);
        $activeCompanyId = (int) $this->getCompanyId();

        if ($request->filled('company_id')) {
            $requestedCompanyId = (int) $request->input('company_id');
            if ($activeCompanyId > 0 && $requestedCompanyId !== $activeCompanyId && (int) auth()->user()->role_id !== \App\Models\Role::SUPER_ADMIN) {
                abort(403, 'Unauthorized access to company records.');
            }
            $companyId = $requestedCompanyId;
        } else {
            $companyId = $activeCompanyId;
        }

        $query = \App\Models\EmployeeMaster::with('dept');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if (! empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('employee_code', 'LIKE', "%{$q}%")
                    ->orWhere('first_name', 'LIKE', "%{$q}%")
                    ->orWhere('last_name', 'LIKE', "%{$q}%")
                    ->orWhere('middle_name', 'LIKE', "%{$q}%")
                    ->orWhere('department', 'LIKE', "%{$q}%")
                    ->orWhereHas('dept', function ($dq) use ($q) {
                        $dq->where('name', 'LIKE', "%{$q}%");
                    });
            });
        }

        $query->orderBy('last_name')->orderBy('first_name');

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        $results = collect($paginator->items())->map(function ($emp) {
            $empCode = $emp->employee_code ?? ('EMP-'.$emp->id);
            $fullName = $emp->last_name.', '.$emp->first_name.($emp->middle_name ? ' '.substr($emp->middle_name, 0, 1).'.' : '');
            $deptName = $emp->dept->name ?? $emp->department ?? 'No Department';

            return [
                'id' => $emp->id,
                'text' => "[{$empCode}] {$fullName} — {$deptName}",
                'employee_code' => $empCode,
                'full_name' => $fullName,
                'department' => $deptName,
            ];
        });

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
