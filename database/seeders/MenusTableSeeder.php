<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;

class MenusTableSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            [
                'name' => 'Dashboard',
                'route' => 'dashboard',
                'route_name' => 'dashboard',
                'icon' => 'fe-grid',
                'order' => 1,
                'module' => 'dashboard',
                'is_active' => true,
            ],
            [
                'name' => 'Users / Employees',
                'route' => 'employee',
                'route_name' => 'employee',
                'icon' => 'fe-users',
                'order' => 2,
                'module' => 'employees',
                'is_active' => true,
            ],
            [
                'name' => 'Companies',
                'route' => 'companies',
                'route_name' => 'companies',
                'icon' => 'fe-briefcase',
                'order' => 3,
                'module' => 'companies',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Employees',
                'route' => 'company/payroll-employees',
                'route_name' => 'company.payroll-employees.index',
                'icon' => 'fe-user-check',
                'order' => 4,
                'module' => 'payroll_employees',
                'is_active' => true,
            ],
            [
                'name' => 'Departments',
                'route' => 'admin/departments',
                'route_name' => 'admin.departments.index',
                'icon' => 'fe-folder',
                'order' => 5,
                'module' => 'departments',
                'is_active' => true,
            ],
            [
                'name' => 'Income Types',
                'route' => 'income-types',
                'route_name' => 'income-types.index',
                'icon' => 'fe-plus-circle',
                'order' => 6,
                'module' => 'income_types',
                'is_active' => true,
            ],
            [
                'name' => 'Deduction Types',
                'route' => 'deduction-types',
                'route_name' => 'deduction-types.index',
                'icon' => 'fe-minus-circle',
                'order' => 7,
                'module' => 'deduction_types',
                'is_active' => true,
            ],
            [
                'name' => 'Loan Types',
                'route' => 'loan-types',
                'route_name' => 'loan-types.index',
                'icon' => 'fe-repeat',
                'order' => 8,
                'module' => 'loan_types',
                'is_active' => true,
            ],
            [
                'name' => 'Assign Income',
                'route' => 'company/emp-incomes',
                'route_name' => 'emp-incomes.index',
                'icon' => 'fe-dollar-sign',
                'order' => 9,
                'module' => 'emp_incomes',
                'is_active' => true,
            ],
            [
                'name' => 'Assign Deductions',
                'route' => 'company/emp-deductions',
                'route_name' => 'emp-deductions.index',
                'icon' => 'fe-minus-square',
                'order' => 10,
                'module' => 'emp_deductions',
                'is_active' => true,
            ],
            [
                'name' => 'Gov. Contributions',
                'route' => 'company/government-contributions',
                'route_name' => 'government-contributions.index',
                'icon' => 'fe-shield',
                'order' => 11,
                'module' => 'gov_contributions',
                'is_active' => true,
            ],
            [
                'name' => 'Attendance',
                'route' => 'company/attendance',
                'route_name' => 'attendance.index',
                'icon' => 'fe-clock',
                'order' => 12,
                'module' => 'attendance',
                'is_active' => true,
            ],
            [
                'name' => 'HR Adjustments',
                'route' => 'admin/hr-adjustments',
                'route_name' => 'admin.hr-adjustments.index',
                'icon' => 'fe-sliders',
                'order' => 13,
                'module' => 'hr_adjustments',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Periods',
                'route' => 'admin/payroll-periods',
                'route_name' => 'admin.payroll-periods.index',
                'icon' => 'fe-calendar',
                'order' => 14,
                'module' => 'payroll_periods',
                'is_active' => true,
            ],
            [
                'name' => 'Vale / Loans',
                'route' => 'company/loan-monitor',
                'route_name' => 'admin.loan-monitor.index',
                'icon' => 'fe-trending-down',
                'order' => 15,
                'module' => 'vale_loans',
                'is_active' => true,
            ],
            [
                'name' => 'Loan Requests Queue',
                'route' => 'admin/loan-requests',
                'route_name' => 'admin.loan-requests.approval-queue',
                'icon' => 'fe-inbox',
                'order' => 16,
                'module' => 'loan_requests',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Input / Drafts',
                'route' => 'company/payroll-drafts',
                'route_name' => 'payroll-drafts.index',
                'icon' => 'fe-file-text',
                'order' => 17,
                'module' => 'payroll_drafts',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Approvals',
                'route' => 'company/payroll-approvals',
                'route_name' => 'payroll-approvals.index',
                'icon' => 'fe-check-square',
                'order' => 18,
                'module' => 'payroll_approvals',
                'is_active' => true,
            ],
            [
                'name' => 'Reports Hub',
                'route' => 'company/reports',
                'route_name' => 'reports.index',
                'icon' => 'fe-bar-chart-2',
                'order' => 19,
                'module' => 'reports',
                'is_active' => true,
            ],
            [
                'name' => 'Activity Logs',
                'route' => 'admin/activity-logs',
                'route_name' => 'admin.activity-logs.index',
                'icon' => 'fe-activity',
                'order' => 20,
                'module' => 'activity_logs',
                'is_active' => true,
            ],
            [
                'name' => 'Permissions Matrix',
                'route' => 'company/permissions-matrix',
                'route_name' => 'company.permissions.matrix',
                'icon' => 'fe-lock',
                'order' => 21,
                'module' => 'permissions',
                'is_active' => true,
            ],
            [
                'name' => 'Account Settings',
                'route' => 'employee/settings',
                'route_name' => 'settings',
                'icon' => 'fe-settings',
                'order' => 22,
                'module' => 'settings',
                'is_active' => true,
            ],
        ];

        $capabilities = [
            [
                'name' => 'Loan Monitor — Record Payment',
                'route' => null,
                'route_name' => null,
                'icon' => 'fe-dollar-sign',
                'order' => 151,
                'module' => 'loan_record_payment',
                'parent_module' => 'vale_loans',
                'is_active' => true,
            ],
            [
                'name' => 'Loan Monitor — Edit Loan',
                'route' => null,
                'route_name' => null,
                'icon' => 'fe-edit-2',
                'order' => 152,
                'module' => 'loan_edit',
                'parent_module' => 'vale_loans',
                'is_active' => true,
            ],
            [
                'name' => 'Loan Monitor — Edit Manual Payment',
                'route' => null,
                'route_name' => null,
                'icon' => 'fe-edit-3',
                'order' => 153,
                'module' => 'loan_payment_edit',
                'parent_module' => 'vale_loans',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Draft — Add Income',
                'route' => null,
                'route_name' => null,
                'icon' => 'fe-plus-circle',
                'order' => 171,
                'module' => 'payroll_income_inputs',
                'parent_module' => 'payroll_drafts',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Draft — Add Deduction',
                'route' => null,
                'route_name' => null,
                'icon' => 'fe-minus-circle',
                'order' => 172,
                'module' => 'payroll_deduction_inputs',
                'parent_module' => 'payroll_drafts',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Draft — View Income Rates',
                'route' => null,
                'route_name' => null,
                'icon' => 'fe-eye',
                'order' => 173,
                'module' => 'payroll_income_rate_visibility',
                'parent_module' => 'payroll_drafts',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Draft — Correction Override',
                'route' => null,
                'route_name' => null,
                'icon' => 'fe-unlock',
                'order' => 174,
                'module' => 'payroll_locked_edit_override',
                'parent_module' => 'payroll_drafts',
                'is_active' => true,
            ],
            [
                'name' => 'Payroll Draft — View Income Column',
                'route' => null,
                'route_name' => null,
                'icon' => 'fe-dollar-sign',
                'order' => 175,
                'module' => 'payroll_income_column_visibility',
                'parent_module' => 'payroll_drafts',
                'is_active' => true,
            ],
        ];

        $validModules = [];
        $createdMenus = [];

        // 1. Seed top-level modules
        foreach ($menus as $m) {
            $validModules[] = $m['module'];
            $menuModel = Menu::updateOrCreate(
                ['module' => $m['module']],
                $m
            );
            $createdMenus[$m['module']] = $menuModel->id;
        }

        // 2. Seed sub-capabilities linked via parent_id
        foreach ($capabilities as $cap) {
            $validModules[] = $cap['module'];
            $parentModule = $cap['parent_module'];
            unset($cap['parent_module']);
            $cap['parent_id'] = $createdMenus[$parentModule] ?? null;

            Menu::updateOrCreate(
                ['module' => $cap['module']],
                $cap
            );
        }

        // 3. Delete obsolete/transitional capability menus
        Menu::whereNotIn('module', $validModules)->delete();
    }
}
