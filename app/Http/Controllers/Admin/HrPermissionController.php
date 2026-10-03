<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Menu;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use Illuminate\Http\Request;

class HrPermissionController extends Controller
{
    /**
     * Capability records live in menus because menu_role_access keys off menus.
     * The deduction capability is registry-only and deliberately has no route.
     */
    protected function getCapabilityMenus(): array
    {
        $definitions = [
            'payroll_income_inputs' => [
                'name' => 'Payroll Income Input',
                'route' => 'company/payroll-drafts',
                'route_name' => 'payroll-drafts.edit',
                'icon' => 'fe-edit-3',
                'order' => 15,
            ],
            'payroll_income_rate_visibility' => [
                'name' => 'Payroll Income Rate Visibility',
                'icon' => 'fe-eye',
                'is_active' => false,
                'order' => 0,
            ],
            'payroll_deduction_inputs' => [
                'name' => 'Payroll Deduction Input',
                'icon' => 'fe-edit-3',
                'is_active' => false,
                'order' => 0,
            ],
        ];

        $menus = [];
        foreach ($definitions as $module => $attributes) {
            $menus[$module] = Menu::firstOrCreate(
                ['module' => $module],
                $attributes
            );
        }

        return $menus;
    }

    /**
     * Display HR Permissions delegation management page.
     */
    public function index(Request $request)
    {
        return redirect()->route('company.permissions.matrix');
    }

    public function update(Request $request)
    {
        return redirect()->route('company.permissions.matrix');
    }
}
