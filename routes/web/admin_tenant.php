<?php


use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceDeviceController;
use App\Http\Controllers\Admin\companyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeductionTypeController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmpDeductionController;
use App\Http\Controllers\Admin\EmpIncomeController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\GovernmentContributionController;
use App\Http\Controllers\Admin\IncomeTypeController;
use App\Http\Controllers\Admin\LoanMonitorController;
use App\Http\Controllers\Admin\LoanTypeController;
use App\Http\Controllers\Admin\PayrollApprovalController;
use App\Http\Controllers\Admin\PayrollDraftController;
use App\Http\Controllers\Admin\PayrollEmployeeController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\userController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\website\EditProfileController;
use App\Http\Controllers\website\SigninController;
use App\Http\Controllers\website\SignupController;
use App\Http\Controllers\website\WebsiteDashboardController;
use App\Http\Controllers\website\WebsiteHomeController;
use Illuminate\Support\Facades\Route;

    // ── ADMIN AS TENANT PAYROLL/HR ROUTES (IsAdmin:admin) ─────────
    Route::prefix('admin')->middleware('IsAdmin:admin')->group(function () {
        // Employees Management
        Route::get('employee', [EmployeeController::class, 'index'])->name('admin.employee');
        Route::get('employees/search', [EmployeeController::class, 'ajaxSearch'])->name('admin.employees.ajax-search');
        Route::get('create', [EmployeeController::class, 'create'])->name('admin.createEmployee');
        Route::post('storeEmployee', [EmployeeController::class, 'store'])->name('admin.storeEmployee');
        Route::get('edit/{id}', [EmployeeController::class, 'edit'])->name('admin.editEmployee');
        Route::put('update/{id}', [EmployeeController::class, 'update'])->name('admin.updateEmployee');
        Route::delete('delete/{id}', [EmployeeController::class, 'destroy'])->name('admin.deleteEmployee');

        // Permissions
        Route::get('permissions-matrix', [\App\Http\Controllers\Admin\PermissionMatrixController::class, 'showMatrix'])->name('admin.permissions.matrix');
        Route::post('permissions/toggle', [\App\Http\Controllers\Admin\PermissionMatrixController::class, 'toggleRoleDelegation'])->name('admin.permissions.toggle');
        Route::post('permissions-matrix/save', [\App\Http\Controllers\Admin\PermissionMatrixController::class, 'saveMatrix'])->name('admin.permissions.save');
        Route::get('permissions/{role}', [PermissionController::class, 'index'])->name('admin.permissions');
        Route::get('getUsersList', [PermissionController::class, 'selectedUsersList'])->name('admin.getUsersList');
        Route::put('storePermissions', [PermissionController::class, 'store'])->name('admin.storePermissions');
        Route::get('getUserPermissions', [PermissionController::class, 'getUserPermissions'])->name('admin.getUserPermissions');

        // Payroll Employees
        Route::get('payroll-employees/company-master', [PayrollEmployeeController::class, 'companyMaster'])->name('admin.payroll-employees.company-master');
        Route::get('payroll-employees/company-master/{id}/details', [PayrollEmployeeController::class, 'getEmployeeDetailsAjax'])->name('admin.payroll-employees.company-master.details');
        Route::post('payroll-employees/company-master/{id}/update-quick', [PayrollEmployeeController::class, 'updateQuickMasterAjax'])->name('admin.payroll-employees.company-master.update-quick');
        Route::get('payroll-employees/maintenance/{id}/full-details', [PayrollEmployeeController::class, 'getEmployeeFullDetailsAjax'])->name('admin.payroll-employees.full-details');
        Route::post('payroll-employees/maintenance/{id}/update-record', [PayrollEmployeeController::class, 'updateQuickRecordAjax'])->name('admin.payroll-employees.update-record');
        Route::get('payroll-employees', [PayrollEmployeeController::class, 'index'])->name('admin.payroll-employees.index');
        Route::get('payroll-employees/create', [PayrollEmployeeController::class, 'create'])->name('admin.payroll-employees.create');
        Route::post('payroll-employees', [PayrollEmployeeController::class, 'store'])->name('admin.payroll-employees.store');
        Route::post('payroll-employees/link-user', [PayrollEmployeeController::class, 'linkUserToEmployee'])->name('admin.payroll-employees.link-user');
        Route::post('payroll-employees/{id}/approve', [PayrollEmployeeController::class, 'approve'])->name('admin.payroll-employees.approve');
        Route::get('payroll-employees/{id}', [PayrollEmployeeController::class, 'show'])->name('admin.payroll-employees.show');
        Route::get('payroll-employees/{id}/edit', [PayrollEmployeeController::class, 'edit'])->name('admin.payroll-employees.edit');
        Route::put('payroll-employees/{id}', [PayrollEmployeeController::class, 'update'])->name('admin.payroll-employees.update');
        Route::delete('payroll-employees/{id}', [PayrollEmployeeController::class, 'destroy'])->name('admin.payroll-employees.destroy');
        Route::post('payroll-employees/get-departments-by-company', [PayrollEmployeeController::class, 'getDepartmentsByCompany'])->name('admin.payroll-employees.get-departments');
        Route::post('payroll-employees/get-sub-departments', [PayrollEmployeeController::class, 'getSubDepartmentsByDepartment'])->name('admin.payroll-employees.get-sub-departments');

        Route::get('departments', [DepartmentController::class, 'index'])->name('admin.departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('admin.departments.store');
        Route::post('sub-departments', [DepartmentController::class, 'storeSubDepartment'])->name('admin.sub-departments.store');

        // Income Types (Admin Only)
        Route::get('income-types', [IncomeTypeController::class, 'index'])->name('income-types.index');
        Route::get('income-types/create', [IncomeTypeController::class, 'create'])->name('income-types.create');
        Route::post('income-types', [IncomeTypeController::class, 'store'])->name('income-types.store');
        Route::get('income-types/{id}/edit', [IncomeTypeController::class, 'edit'])->name('income-types.edit');
        Route::put('income-types/{id}', [IncomeTypeController::class, 'update'])->name('income-types.update');
        Route::post('income-types/{id}/toggle-status', [IncomeTypeController::class, 'toggleStatus'])->name('income-types.toggle-status');
        Route::delete('income-types/{id}', [IncomeTypeController::class, 'destroy'])->name('income-types.destroy');

        // Deduction Types (Admin Only)
        Route::get('deduction-types', [DeductionTypeController::class, 'index'])->name('deduction-types.index');
        Route::get('deduction-types/create', [DeductionTypeController::class, 'create'])->name('deduction-types.create');
        Route::post('deduction-types', [DeductionTypeController::class, 'store'])->name('deduction-types.store');
        Route::get('deduction-types/{id}/edit', [DeductionTypeController::class, 'edit'])->name('deduction-types.edit');
        Route::put('deduction-types/{id}', [DeductionTypeController::class, 'update'])->name('deduction-types.update');
        Route::post('deduction-types/{id}/toggle-status', [DeductionTypeController::class, 'toggleStatus'])->name('deduction-types.toggle-status');
        Route::delete('deduction-types/{id}', [DeductionTypeController::class, 'destroy'])->name('deduction-types.destroy');

        // Loan Types
        Route::get('payroll-setup/loan-types', [LoanTypeController::class, 'index'])->name('loan-types.index');
        Route::post('payroll-setup/loan-types', [LoanTypeController::class, 'store'])->name('loan-types.store');
        Route::put('payroll-setup/loan-types/{id}', [LoanTypeController::class, 'update'])->name('loan-types.update');
        Route::post('payroll-setup/loan-types/{id}/toggle-status', [LoanTypeController::class, 'toggleStatus'])->name('loan-types.toggle-status');
        Route::delete('payroll-setup/loan-types/{id}', [LoanTypeController::class, 'destroy'])->name('loan-types.destroy');
        Route::get('loan-types', [LoanTypeController::class, 'index']);
        Route::post('loan-types', [LoanTypeController::class, 'store']);

        // Employee Income Assignments
        Route::get('emp-incomes', [EmpIncomeController::class, 'index'])->name('admin.emp-incomes.index');
        Route::get('emp-incomes/create', [EmpIncomeController::class, 'create'])->name('admin.emp-incomes.create');
        Route::post('emp-incomes', [EmpIncomeController::class, 'store'])->name('admin.emp-incomes.store');
        Route::get('emp-incomes/{id}/edit', [EmpIncomeController::class, 'edit'])->name('admin.emp-incomes.edit');
        Route::put('emp-incomes/{id}', [EmpIncomeController::class, 'update'])->name('admin.emp-incomes.update');
        Route::delete('emp-incomes/{id}', [EmpIncomeController::class, 'destroy'])->name('admin.emp-incomes.destroy');

        // Employee Deduction Assignments
        Route::get('emp-deductions', [EmpDeductionController::class, 'index'])->name('admin.emp-deductions.index');
        Route::get('emp-deductions/create', [EmpDeductionController::class, 'create'])->name('admin.emp-deductions.create');
        Route::post('emp-deductions', [EmpDeductionController::class, 'store'])->name('admin.emp-deductions.store');
        Route::get('emp-deductions/{id}/edit', [EmpDeductionController::class, 'edit'])->name('admin.emp-deductions.edit');
        Route::put('emp-deductions/{id}', [EmpDeductionController::class, 'update'])->name('admin.emp-deductions.update');
        Route::delete('emp-deductions/{id}', [EmpDeductionController::class, 'destroy'])->name('admin.emp-deductions.destroy');

        // Attendance
        Route::get('attendance', [AttendanceController::class, 'index'])->name('admin.attendance.index');
        Route::get('attendance/import', [AttendanceController::class, 'import'])->name('admin.attendance.import');
        Route::post('attendance/import', [AttendanceController::class, 'processImport'])->name('admin.attendance.process-import');
        Route::get('attendance/manual-generate', [AttendanceController::class, 'manualGenerateForm'])->name('admin.attendance.manual-generate');
        Route::post('attendance/manual-generate', [AttendanceController::class, 'processManualGenerate'])->name('admin.attendance.process-manual-generate');
        Route::get('attendance/get-company-employees/{companyId}', [AttendanceController::class, 'getCompanyEmployeesAjax'])->name('admin.attendance.get-company-employees');
        Route::delete('attendance/{id}', [AttendanceController::class, 'destroy'])->name('admin.attendance.destroy');
        Route::post('attendance/delete-batch', [AttendanceController::class, 'deleteBatch'])->name('admin.attendance.delete-batch');
        Route::get('attendance/devices', [AttendanceDeviceController::class, 'index'])->name('admin.attendance.devices.index');
        Route::post('attendance/devices', [AttendanceDeviceController::class, 'store'])->name('admin.attendance.devices.store');
        Route::post('attendance/devices/{device}/server-address', [AttendanceDeviceController::class, 'updateServerAddress'])->name('admin.attendance.devices.update-server-address');
        Route::post('attendance/devices/{device}/toggle', [AttendanceDeviceController::class, 'toggle'])->name('admin.attendance.devices.toggle');
        Route::post('attendance/devices/{device}/rotate-token', [AttendanceDeviceController::class, 'rotateToken'])->name('admin.attendance.devices.rotate-token');
        Route::post('attendance/devices/{device}/reset-position', [AttendanceDeviceController::class, 'resetPosition'])->name('admin.attendance.devices.reset-position');
        Route::post('attendance/devices/{device}/test-handshake', [AttendanceDeviceController::class, 'testHandshake'])->name('admin.attendance.devices.test-handshake');
        Route::get('attendance/devices/{device}/logs', [AttendanceDeviceController::class, 'deviceLogs'])->name('admin.attendance.devices.logs');
        Route::get('attendance/devices/{device}/mappings', [AttendanceDeviceController::class, 'mappings'])->name('admin.attendance.devices.mappings');
        Route::post('attendance/devices/{device}/mappings', [AttendanceDeviceController::class, 'storeMapping'])->name('admin.attendance.devices.store-mapping');
        Route::delete('attendance/devices/{device}/mappings/{mapping}', [AttendanceDeviceController::class, 'deleteMapping'])->name('admin.attendance.devices.delete-mapping');

        // Government Contributions
        Route::get('government-contributions', [GovernmentContributionController::class, 'index'])->name('admin.government-contributions.index');
        Route::get('government-contributions/create', [GovernmentContributionController::class, 'create'])->name('admin.government-contributions.create');
        Route::post('government-contributions', [GovernmentContributionController::class, 'store'])->name('admin.government-contributions.store');
        Route::get('government-contributions/{id}', [GovernmentContributionController::class, 'show'])->name('admin.government-contributions.show');
        Route::get('government-contributions/{id}/edit', [GovernmentContributionController::class, 'edit'])->name('admin.government-contributions.edit');
        Route::put('government-contributions/{id}', [GovernmentContributionController::class, 'update'])->name('admin.government-contributions.update');
        Route::delete('government-contributions/{id}', [GovernmentContributionController::class, 'destroy'])->name('admin.government-contributions.destroy');

        // Payroll Drafts (HR Input)
        Route::get('payroll-drafts', [PayrollDraftController::class, 'index'])->name('admin.payroll-drafts.index');
        Route::get('payroll-drafts/create', [PayrollDraftController::class, 'create'])->name('admin.payroll-drafts.create');
        Route::post('payroll-drafts/generate', [PayrollDraftController::class, 'generate'])->name('admin.payroll-drafts.generate');
        Route::get('payroll-drafts/batch-change', [PayrollDraftController::class, 'batchChangeCreate'])->name('admin.payroll-drafts.batch-change.create');
        Route::post('payroll-drafts/batch-change', [PayrollDraftController::class, 'batchChangeStore'])->name('admin.payroll-drafts.batch-change.store');
        Route::get('payroll-drafts/add-employee', [PayrollDraftController::class, 'addEmployeeCreate'])->name('admin.payroll-drafts.add-employee.create');
        Route::post('payroll-drafts/add-employee', [PayrollDraftController::class, 'addEmployeeStore'])->name('admin.payroll-drafts.add-employee.store');
        Route::get('payroll-drafts/{id}/edit', [PayrollDraftController::class, 'edit'])->name('admin.payroll-drafts.edit');
        Route::post('payroll-drafts/{id}/pay-ins', [PayrollDraftController::class, 'addIncome'])->name('admin.payroll-drafts.add-income');
        Route::post('payroll-drafts/{id}/pay-outs', [PayrollDraftController::class, 'addDeduction'])->name('admin.payroll-drafts.add-deduction');
        Route::put('payroll-drafts/{id}', [PayrollDraftController::class, 'update'])->name('admin.payroll-drafts.update');
        Route::post('payroll-drafts/{id}/submit', [PayrollDraftController::class, 'submit'])->name('admin.payroll-drafts.submit');
        Route::post('payroll-drafts/batch-submit', [PayrollDraftController::class, 'batchSubmit'])->name('admin.payroll-drafts.batch-submit');
        Route::delete('payroll-drafts/{id}', [PayrollDraftController::class, 'destroy'])->name('admin.payroll-drafts.destroy');
        Route::post('payroll-drafts/batch-destroy', [PayrollDraftController::class, 'batchDestroy'])->name('admin.payroll-drafts.batch-destroy');
        Route::post('payroll-drafts/save-company-deduction-defaults', [PayrollDraftController::class, 'saveCompanyDeductionDefaults'])->name('admin.payroll-drafts.save-company-deduction-defaults');
        Route::post('payroll-drafts/save-company-income-defaults', [PayrollDraftController::class, 'saveCompanyIncomeDefaults'])->name('admin.payroll-drafts.save-company-income-defaults');

        // Payroll Approvals (Accounting)
        Route::get('payroll-approvals', [PayrollApprovalController::class, 'index'])->name('admin.payroll-approvals.index');
        Route::get('payroll-approvals/{id}/review', [PayrollApprovalController::class, 'review'])->name('admin.payroll-approvals.review');
        Route::post('payroll-approvals/{id}/approve', [PayrollApprovalController::class, 'approve'])->name('admin.payroll-approvals.approve');
        Route::post('payroll-approvals/{id}/reject', [PayrollApprovalController::class, 'reject'])->name('admin.payroll-approvals.reject');
        Route::post('payroll-approvals/{id}/reopen', [PayrollApprovalController::class, 'reopen'])->name('admin.payroll-approvals.reopen');
        Route::post('payroll-approvals/batch-approve', [PayrollApprovalController::class, 'batchApprove'])->name('admin.payroll-approvals.batch-approve');
        Route::post('payroll-approvals/batch-reject', [PayrollApprovalController::class, 'batchReject'])->name('admin.payroll-approvals.batch-reject');

        // Loan Monitor Module
        Route::get('loan-monitor', [LoanMonitorController::class, 'index'])->name('admin.loan-monitor.index');
        Route::get('loan-monitor/employee/{id}', [LoanMonitorController::class, 'getEmployeeLoans'])->name('admin.loan-monitor.employee-loans');
        Route::get('loan-monitor/{type}/{id}/history', [LoanMonitorController::class, 'paymentHistory'])->name('admin.loan-monitor.payment-history');
        Route::post('loan-monitor/store', [LoanMonitorController::class, 'storeLoan'])->name('admin.loan-monitor.store-loan');
        Route::post('loan-monitor/payment', [LoanMonitorController::class, 'recordPayment'])->name('admin.loan-monitor.record-payment');
        Route::post('loan-monitor/loan/{id}/update', [LoanMonitorController::class, 'updateLoan'])->name('admin.loan-monitor.update-loan');
        Route::post('loan-monitor/payment/{id}/update', [LoanMonitorController::class, 'updatePayment'])->name('admin.loan-monitor.update-payment');
        Route::get('loan-monitor/loan/{id}/print', [LoanMonitorController::class, 'printForm'])->name('admin.loan-monitor.print-form');
        Route::get('loan-monitor/loan/{id}/excel', [LoanMonitorController::class, 'exportExcel'])->name('admin.loan-monitor.export-excel');

        // Payroll Periods
        Route::get('payroll-periods', [\App\Http\Controllers\Admin\PayrollPeriodController::class, 'index'])->name('admin.payroll-periods.index');
        Route::get('payroll-periods/create', [\App\Http\Controllers\Admin\PayrollPeriodController::class, 'create'])->name('admin.payroll-periods.create');
        Route::post('payroll-periods', [\App\Http\Controllers\Admin\PayrollPeriodController::class, 'store'])->name('admin.payroll-periods.store');
        Route::post('payroll-periods/quick-create', [\App\Http\Controllers\Admin\PayrollPeriodController::class, 'quickCreate'])->name('admin.payroll-periods.quick-create');
        Route::get('payroll-periods/{id}/edit', [\App\Http\Controllers\Admin\PayrollPeriodController::class, 'edit'])->name('admin.payroll-periods.edit');
        Route::put('payroll-periods/{id}', [\App\Http\Controllers\Admin\PayrollPeriodController::class, 'update'])->name('admin.payroll-periods.update');
        Route::delete('payroll-periods/{id}', [\App\Http\Controllers\Admin\PayrollPeriodController::class, 'destroy'])->name('admin.payroll-periods.destroy');

        // Reports Module
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::post('reports/payslip', [ReportsController::class, 'payslip'])->name('reports.payslip');
        Route::post('reports/payroll-register', [ReportsController::class, 'payrollRegister'])->name('reports.payroll-register');
        Route::post('reports/payroll-summary-1', [ReportsController::class, 'payrollSummary1'])->name('reports.payroll-summary-1');
        Route::post('reports/payroll-summary-2', [ReportsController::class, 'payrollSummary2'])->name('reports.payroll-summary-2');
        Route::post('reports/voucher', [ReportsController::class, 'voucher'])->name('reports.voucher');
        Route::post('reports/sss', [ReportsController::class, 'sssReport'])->name('reports.sss');
        Route::post('reports/philhealth', [ReportsController::class, 'philHealthReport'])->name('reports.philhealth');
        Route::post('reports/pagibig', [ReportsController::class, 'pagibigReport'])->name('reports.pagibig');
        Route::post('reports/withholding-tax', [ReportsController::class, 'withholdingTaxReport'])->name('reports.withholding-tax');
        Route::post('reports/loan-ledger', [ReportsController::class, 'loanLedger'])->name('reports.loan-ledger');
        Route::post('reports/alpha-list', [ReportsController::class, 'alphaList'])->name('reports.alpha-list');
        Route::post('reports/batch-payslips', [ReportsController::class, 'batchPayslips'])->name('reports.batch-payslips');
        Route::post('reports/income-payroll-history', [ReportsController::class, 'incomePayrollHistory'])->name('reports.income-payroll-history');
        Route::post('reports/recurring-income-summary', [ReportsController::class, 'recurringIncomeSummary'])->name('reports.recurring-income-summary');
        Route::post('reports/deduction-payroll-history', [ReportsController::class, 'deductionPayrollHistory'])->name('reports.deduction-payroll-history');
        Route::post('reports/13th-month-computation', [ReportsController::class, 'thirteenthMonthComputation'])->name('reports.13th-month-computation');
        Route::post('reports/bank-report', [ReportsController::class, 'bankReport'])->name('reports.bank-report');
        Route::post('reports/save-bank-settings', [ReportsController::class, 'saveBankSettingsAjax'])->name('reports.save-bank-settings');
        Route::post('reports/deduction-history', [ReportsController::class, 'deductionHistory'])->name('reports.deduction-history');
        Route::post('reports/income-history', [ReportsController::class, 'incomeHistory'])->name('reports.income-history');
        Route::post('reports/deduction-ledger', [ReportsController::class, 'deductionLedger'])->name('reports.deduction-ledger');
        Route::post('reports/single-deduction-matrix', [ReportsController::class, 'singleDeductionMatrix'])->name('reports.single-deduction-matrix');
        Route::post('reports/export-payroll-bank', [ReportsController::class, 'exportPayrollForBank'])->name('reports.export-payroll-bank');
        // Report #14: Pending Bank Format Export (Placeholder - awaiting client template specification)
        // Route::post('reports/export-pending-bank-format', [ReportsController::class, 'exportPendingBankFormat'])->name('reports.export-pending-bank-format');

        // Phase 4 Export Routes
        Route::get('reports/export-bank-advice/{payrollPeriodId}', [ReportsController::class, 'exportBankAdvice'])->name('reports.export-bank-advice');
        Route::get('reports/export-master-sheet/{payrollPeriodId}', [ReportsController::class, 'exportMasterSheet'])->name('reports.export-master-sheet');
        Route::get('reports/export-statutory/{type}/{year}/{month}', [ReportsController::class, 'exportStatutory'])->name('reports.export-statutory');
        Route::get('reports/export-13th-month/{year}', [ReportsController::class, 'exportThirteenthMonth'])->name('reports.export-13th-month');
        Route::match(['get', 'post'], 'reports/thirteenth-month-history', [ReportsController::class, 'thirteenthMonthHistory'])->name('reports.thirteenth-month-history');
        Route::get('reports/thirteenth-month-history/pdf', [ReportsController::class, 'exportThirteenthMonthHistoryPdf'])->name('reports.thirteenth-month-history.pdf');
        Route::get('reports/thirteenth-month-history/excel', [ReportsController::class, 'exportThirteenthMonthHistoryExcel'])->name('reports.thirteenth-month-history.excel');

        // HR Adjustments Routes
        Route::resource('hr-adjustments', \App\Http\Controllers\Admin\HRAdjustmentController::class, ['as' => 'admin']);
        Route::post('hr-adjustments/{id}/approve', [\App\Http\Controllers\Admin\HRAdjustmentController::class, 'approve'])->name('admin.hr-adjustments.approve');

        // Loan Request Approval Queue
        Route::get('loan-requests', [\App\Http\Controllers\Admin\LoanRequestController::class, 'approvalQueue'])->name('admin.loan-requests.approval-queue');
        Route::post('loan-requests/{id}/status', [\App\Http\Controllers\Admin\LoanRequestController::class, 'updateStatus'])->name('admin.loan-requests.update-status');

        // Activity Logs
        Route::get('activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
        Route::get('activity-logs/export', [\App\Http\Controllers\Admin\ActivityLogController::class, 'export'])->name('admin.activity-logs.export');
    });
