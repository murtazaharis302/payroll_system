<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use App\Models\Company;
use App\Models\Menu;

class PermissionMatrixController extends Controller
{
    /**
     * Display Company Internal Role Delegation Matrix (Tier 2 UI)
     */
    public function showMatrix(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = ($user->role_id === Role::SUPER_ADMIN || $user->role === 'admin');

        if ($isSuperAdmin) {
            $companies = Company::where('status', '1')->orderBy('name')->get();
            $companyId = (int) ($request->query('company_id') ?: ($user->company_id ?: $companies->first()?->id ?: 1));
        } else {
            $companies = collect();
            $companyId = (int) ($user->company_id ?? getActiveCompanyId($user) ?? session('active_company_id') ?? 1);
        }

        $selectedCompany = Company::find($companyId);
        $menus = Menu::orderBy('order')->get();

        return view('admin.permissions.matrix', compact('menus', 'companyId', 'companies', 'selectedCompany', 'isSuperAdmin'));
    }

    /**
     * Display Super Admin Tenant Permission Matrix (Tier 1 UI)
     */
    public function showCompanyPermissions(Company $company)
    {
        $menus = Menu::orderBy('order')->get();
        return view('super_admin.companies.permissions', compact('menus', 'company'));
    }

    /**
     * Super Admin Endpoint: Toggle company entitlement (Tier 1)
     */
    public function toggleCompanyEntitlement(Request $request, Company $company)
    {
        $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'is_enabled' => 'required|boolean',
        ]);

        $menuId = (int) $request->input('menu_id');
        $isEnabled = (bool) $request->input('is_enabled');

        MenuRoleAccess::updateOrCreate(
            [
                'company_id' => $company->id,
                'role_id' => Role::COMPANY_ADMIN,
                'menu_id' => $menuId,
            ],
            [
                'is_enabled' => $isEnabled,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $isEnabled ? 'Module enabled successfully for company.' : 'Module disabled successfully for company.',
        ]);
    }

    /**
     * Endpoint: Toggle individual role permission (Auto-Save on Switch)
     */
    public function toggleRoleDelegation(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = ($user->role_id === Role::SUPER_ADMIN || $user->role === 'admin');

        $allowedRoles = $isSuperAdmin
            ? [Role::COMPANY_ADMIN, Role::HR, Role::EMPLOYEE]
            : [Role::HR, Role::EMPLOYEE];

        $request->validate([
            'menu_id'    => 'required|exists:menus,id',
            'role_id'    => 'required|in:' . implode(',', $allowedRoles),
            'is_enabled' => 'required|boolean',
        ]);

        $companyId = (int) ($isSuperAdmin && $request->filled('company_id')
            ? $request->input('company_id')
            : ($user->company_id ?? getActiveCompanyId($user) ?? session('active_company_id') ?? 1));

        $menuId = (int) $request->input('menu_id');
        $roleId = (int) $request->input('role_id');
        $isEnabled = (bool) $request->input('is_enabled');

        // Verify Tier 1 entitlement for Company Admin if delegating HR or Employee role
        if (!$isSuperAdmin && $roleId !== Role::COMPANY_ADMIN) {
            $hasCompanyEntitlement = MenuRoleAccess::checkAccess($companyId, Role::COMPANY_ADMIN, $menuId);
            if (!$hasCompanyEntitlement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delegate a module that is disabled by the Host (Super Admin).',
                ], 403);
            }
        }

        MenuRoleAccess::updateOrCreate(
            [
                'company_id' => $companyId,
                'role_id'    => $roleId,
                'menu_id'    => $menuId,
            ],
            [
                'is_enabled' => $isEnabled,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Role permission updated successfully.',
        ]);
    }

    /**
     * Endpoint: Bulk Save Matrix Decisions
     */
    public function saveMatrix(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = ($user->role_id === Role::SUPER_ADMIN || $user->role === 'admin');

        $companyId = (int) ($isSuperAdmin && $request->filled('company_id')
            ? $request->input('company_id')
            : ($user->company_id ?? getActiveCompanyId($user) ?? session('active_company_id') ?? 1));

        $permissions = $request->input('permissions', []); // menu_id => [role_id => is_enabled]

        $menus = Menu::all();
        $roles = [Role::HR, Role::EMPLOYEE];
        if ($isSuperAdmin) {
            $roles[] = Role::COMPANY_ADMIN;
        }

        foreach ($menus as $menu) {
            $hasCompanyEntitlement = $isSuperAdmin || MenuRoleAccess::checkAccess($companyId, Role::COMPANY_ADMIN, $menu->id);

            foreach ($roles as $roleId) {
                if (!$hasCompanyEntitlement && $roleId !== Role::COMPANY_ADMIN) {
                    continue;
                }

                $isEnabled = isset($permissions[$menu->id][$roleId]) && (bool) $permissions[$menu->id][$roleId];

                MenuRoleAccess::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'role_id'    => $roleId,
                        'menu_id'    => $menu->id,
                    ],
                    [
                        'is_enabled' => $isEnabled,
                    ]
                );
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Permissions matrix saved successfully.',
            ]);
        }

        return redirect()->back()->with('statusSuccess', 'Permissions matrix saved successfully.');
    }
}
