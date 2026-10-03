<?php


use App\Http\Controllers\Admin\AttendanceController;
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

    // ── EMPLOYEE SUB-GROUP (IsUser Hard Boundary) ────────────────
    Route::prefix('employee')->middleware('IsUser')->name('employee.')->group(function () {
        // Profile
        Route::get('/edit-profile/{id}', [ProfileController::class, 'editProfile'])->name('edit_profile');
        Route::post('/edit-profile/{id}', [ProfileController::class, 'updateProfile'])->name('update_profile');

        // Dashboard
        Route::get('dashboard', [\App\Http\Controllers\Admin\EmployeeDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('my-payslip/{id}/download', [\App\Http\Controllers\Admin\EmployeeDashboardController::class, 'downloadMyPayslip'])->name('my-payslip.download');

        // Users Management (View based on permissions)
        Route::get('users', [userController::class, 'index'])->name('users');
        Route::get('createuser', [userController::class, 'create'])->name('createUser');
        Route::post('storeuser', [userController::class, 'store'])->name('storeUser');
        Route::get('edituser/{id}', [userController::class, 'edit'])->name('editUser');
        Route::put('updateuser/{id}', [userController::class, 'update'])->name('updateUser');
        Route::delete('deleteuser/{id}', [userController::class, 'destroy'])->name('deleteUser');

        // Employees Management
        Route::get('employee', [EmployeeController::class, 'index'])->name('employee');
        Route::get('create', [EmployeeController::class, 'create'])->name('createEmployee');
        Route::post('store', [EmployeeController::class, 'store'])->name('storeEmployee');
        Route::get('edit/{id}', [EmployeeController::class, 'edit'])->name('editEmployee');
        Route::put('update/{id}', [EmployeeController::class, 'update'])->name('updateEmployee');
        Route::delete('delete/{id}', [EmployeeController::class, 'destroy'])->name('deleteEmployee');

        // Companies Management (View Only)
        Route::get('companies', [companyController::class, 'index'])->name('companies');
        Route::get('editCompany/{id}', [companyController::class, 'edit'])->name('editCompany');
        Route::put('updateCompany/{id}', [companyController::class, 'update'])->name('updateCompany');

        // Loan Requests Sub-System
        Route::get('loan-requests', [\App\Http\Controllers\Admin\LoanRequestController::class, 'employeeIndex'])->name('loan-requests.index');
        Route::post('loan-requests', [\App\Http\Controllers\Admin\LoanRequestController::class, 'store'])->name('loan-requests.store');
        Route::post('loan-requests/{id}/status', [\App\Http\Controllers\Admin\LoanRequestController::class, 'updateStatus'])->name('loan-requests.update-status');

        // Activity Logs
        Route::get('activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/export', [\App\Http\Controllers\Admin\ActivityLogController::class, 'export'])->name('activity-logs.export');

        // Payroll Periods Management
        Route::get('payroll-periods', [App\Http\Controllers\Admin\PayrollPeriodController::class, 'index'])->name('payroll-periods.index');
        Route::get('payroll-periods/create', [App\Http\Controllers\Admin\PayrollPeriodController::class, 'create'])->name('payroll-periods.create');
        Route::post('payroll-periods', [App\Http\Controllers\Admin\PayrollPeriodController::class, 'store'])->name('payroll-periods.store');
        Route::get('payroll-periods/{id}/edit', [App\Http\Controllers\Admin\PayrollPeriodController::class, 'edit'])->name('payroll-periods.edit');
        Route::put('payroll-periods/{id}', [App\Http\Controllers\Admin\PayrollPeriodController::class, 'update'])->name('payroll-periods.update');
        Route::delete('payroll-periods/{id}', [App\Http\Controllers\Admin\PayrollPeriodController::class, 'destroy'])->name('payroll-periods.destroy');
        Route::post('payroll-periods/quick-create', [App\Http\Controllers\Admin\PayrollPeriodController::class, 'quickCreate'])->name('payroll-periods.quick-create');

        // Reports Module
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::post('reports/payslip', [ReportsController::class, 'payslip'])->name('reports.payslip');
        Route::post('reports/payroll-register', [ReportsController::class, 'payrollRegister'])->name('reports.payroll-register');
        Route::post('reports/sss', [ReportsController::class, 'sssReport'])->name('reports.sss');
        Route::post('reports/philhealth', [ReportsController::class, 'philhealthReport'])->name('reports.philhealth');
        Route::post('reports/pagibig', [ReportsController::class, 'pagibigReport'])->name('reports.pagibig');
        Route::post('reports/withholding-tax', [ReportsController::class, 'withholdingTaxReport'])->name('reports.withholding-tax');
        Route::post('reports/loan-ledger', [ReportsController::class, 'loanLedger'])->name('reports.loan-ledger');
        Route::post('reports/alpha-list', [ReportsController::class, 'alphaList'])->name('reports.alpha-list');
        Route::post('reports/batch-payslips', [ReportsController::class, 'batchPayslips'])->name('reports.batch-payslips');
    });
