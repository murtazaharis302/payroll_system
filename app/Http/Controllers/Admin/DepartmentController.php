<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    protected function getCompanyId()
    {
        return getActiveCompanyId();
    }

    public function index()
    {
        $currentUser = auth()->user();
        if (!\App\Models\MenuRoleAccess::hasModuleAccess($currentUser, 'departments')) {
            abort(403, 'Unauthorized access to department management.');
        }

        $userCompanyId = $currentUser->company_id ?? $this->getCompanyId();
        $companyId = $userCompanyId;

        if ($currentUser->role === 'admin') {
            $companies = Company::where('status', 1)->get();
            $selectedCompanyId = request('company_id', $userCompanyId ?? $companies->first()?->id);
        } else {
            $companies = Company::where('user_id', $currentUser->id)->orWhere('id', $userCompanyId)->where('status', 1)->get();
            $selectedCompanyId = request('company_id', $userCompanyId ?? $companies->first()?->id);
        }

        $departments = collect();
        if ($selectedCompanyId) {
            $departments = Department::with('subDepartments')
                ->where('company_id', $selectedCompanyId)
                ->orderBy('name')
                ->get();
        }

        return view('admin.department.index', compact('companies', 'departments', 'selectedCompanyId', 'userCompanyId', 'companyId'));
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();
        if (!\App\Models\MenuRoleAccess::hasModuleAccess($currentUser, 'departments')) {
            abort(403, 'Unauthorized access to department management.');
        }

        $userCompanyId = $currentUser->company_id ?? $this->getCompanyId();
        $targetCompanyId = ($currentUser->role !== 'admin' && $userCompanyId)
            ? $userCompanyId
            : $request->input('company_id');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->where(function ($query) use ($targetCompanyId) {
                    return $query->where('company_id', $targetCompanyId);
                }),
            ],
            'name' => 'required|string|max:255',
        ], [
            'code.unique' => 'A department with this code already exists for this company. Please use a different code.',
        ]);

        $validated['company_id'] = $targetCompanyId;

        authorizeCompanyAccess((int) $validated['company_id']);

        Department::create(array_merge($validated, ['is_active' => true]));

        return back()->with('status', 'Department added successfully.');
    }

    public function storeSubDepartment(Request $request)
    {
        $currentUser = auth()->user();
        if (!\App\Models\MenuRoleAccess::hasModuleAccess($currentUser, 'departments')) {
            abort(403, 'Unauthorized access to department management.');
        }
        $userCompanyId = $currentUser->company_id ?? $this->getCompanyId();
        $targetCompanyId = ($currentUser->role !== 'admin' && $userCompanyId)
            ? $userCompanyId
            : $request->input('company_id');
        $targetDepartmentId = $request->input('department_id');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'required|exists:departments,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sub_departments', 'code')->where(function ($query) use ($targetDepartmentId) {
                    return $query->where('department_id', $targetDepartmentId);
                }),
            ],
            'name' => 'required|string|max:255',
        ], [
            'code.unique' => 'A sub-department with this code already exists for this department. Please use a different code.',
        ]);

        $validated['company_id'] = $targetCompanyId;

        authorizeCompanyAccess((int) $validated['company_id']);

        $department = Department::where('id', $validated['department_id'])
            ->where('company_id', $validated['company_id'])
            ->firstOrFail();

        SubDepartment::create([
            'company_id' => $department->company_id,
            'department_id' => $department->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Sub-department added successfully.');
    }
}
