<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index($role)
    {
        if ($role == 'admin') {
            $usersList = Employee::where('owner_user_id', auth()->id())->get();
        } elseif ($role == 'company') {
            $usersList = company::all();
        } elseif ($role == 'selfCompany') {
            $usersList = Employee::where('owner_user_id', auth()->id())->get();
        }

        return view('admin.permissions.index', compact('role', 'usersList'));
    }

    public function store(Request $request)
    {
        $userId = $request->name;
        $submittedPermissions = $request->input('permissions', []);

        $userRole = User::find($userId)?->role;
        $ownerUserId = Employee::where('e_user_id', $userId)->value('owner_user_id');
        $ownerRole = User::find($ownerUserId)?->role;

        $modules = array_keys(getPermissionModules($userRole, $ownerRole));
        $actions = permissionActions();

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $value = $submittedPermissions[$module][$action] ?? 0;

                $permission = Permission::where('user_id', $userId)
                    ->where('module', $module)
                    ->where('key', $action)
                    ->first();

                if ($permission) {
                    if ($permission->value != $value) {
                        $permission->value = $value;
                        $permission->save();
                    }
                } elseif ($value) {
                    Permission::create([
                        'user_id' => $userId,
                        'module' => $module,
                        'key' => $action,
                        'value' => 1,
                    ]);
                }
            }
        }

        return redirect()->back()->with('status', 'Permissions saved successfully.');
    }

    public function selectedUsersList(Request $request)
    {
        $usersList = Employee::where('owner_user_id', $request->selectedCompany)->get();
        $html = view('admin.permissions.includes.usersList', ['usersList' => $usersList])->render();

        return response()->json([
            'status' => 'Success',
            'usersList' => $usersList,
            'html' => $html,
        ]);
    }

    public function getUserPermissions(Request $request)
    {
        $userPermissions = Permission::where('user_id', $request->selectedUserId)->get();
        $userRole = User::find($request->selectedUserId)?->role;
        $ownerUserId = Employee::where('e_user_id', $request->selectedUserId)->value('owner_user_id');
        $ownerRole = User::find($ownerUserId)?->role;

        $html = view('admin.permissions.includes.permissionsTable', [
            'userPermissions' => $userPermissions,
            'user_id' => $request->selectedUserId,
            'user_role' => $userRole,
            'ownerRole' => $ownerRole,
        ])->render();

        return response()->json([
            'status' => 'Success',
            'html' => $html,
        ]);
    }
}
