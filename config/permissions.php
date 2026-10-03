<?php

return [
    'actions' => ['view', 'edit'],

    // Modules where only "view" is allowed (no edit checkbox)
    'view_only' => ['dashboard'],

    /*
    |--------------------------------------------------------------------------
    | Admin staff (HR / Accounting under system admin)
    |--------------------------------------------------------------------------
    */
    'admin_staff' => [
        'dashboard' => 'Dashboard',
        'users' => 'Users (Admins)',
        'employees' => 'Employees (Staff)',
        'companies' => 'Companies',
        'payroll_employees' => 'Payroll Employees',
        'income_types' => 'Income Types',
        'deduction_types' => 'Deduction Types',
        'emp_incomes' => 'Assign Income',
        'emp_deductions' => 'Assign Deductions',
        'gov_contributions' => 'Gov. Contributions',
        'gov_loans' => 'Gov. Loans',
        'attendance' => 'Attendance',
        'vale_loans' => 'Vale / Loans',
        'goods_purchases' => 'Goods Purchase',
        'payroll_drafts' => 'Payroll Input',
        'payroll_approvals' => 'Payroll Approval',
        'permissions' => 'Permissions',
        'settings' => 'Settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Company owner permissions
    |--------------------------------------------------------------------------
    */
    'company_owner' => [
        'dashboard' => 'Dashboard',
        'employees' => 'Employees (Staff)',
        'companies' => 'My Company',
        'payroll_employees' => 'Payroll Employees',
        'emp_incomes' => 'Assign Income',
        'emp_deductions' => 'Assign Deductions',
        'gov_contributions' => 'Gov. Contributions',
        'gov_loans' => 'Gov. Loans',
        'attendance' => 'Attendance',
        'vale_loans' => 'Vale / Loans',
        'goods_purchases' => 'Goods Purchase',
        'payroll_drafts' => 'Payroll Input',
        'payroll_income_inputs' => 'Payroll Income Input',
        'payroll_income_rate_visibility' => 'Payroll Income Rate Visibility',
        'payroll_deduction_inputs' => 'Payroll Deduction Input',
        'payroll_approvals' => 'Payroll Approval',
        'permissions' => 'Permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Company staff (HR / Accounting under a company)
    |--------------------------------------------------------------------------
    */
    'company_staff' => [
        'dashboard' => 'Dashboard',
        'employees' => 'Employees (Staff)',
        'payroll_employees' => 'Payroll Employees',
        'emp_incomes' => 'Assign Income',
        'emp_deductions' => 'Assign Deductions',
        'gov_contributions' => 'Gov. Contributions',
        'gov_loans' => 'Gov. Loans',
        'attendance' => 'Attendance',
        'vale_loans' => 'Vale / Loans',
        'goods_purchases' => 'Goods Purchase',
        'payroll_drafts' => 'Payroll Input',
        'payroll_income_inputs' => 'Payroll Income Input',
        'payroll_income_rate_visibility' => 'Payroll Income Rate Visibility',
        'payroll_deduction_inputs' => 'Payroll Deduction Input',
        'payroll_approvals' => 'Payroll Approval',
    ],
];
