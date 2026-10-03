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
// HR Dashboard Route
Route::middleware(['IsAdmin:hr'])->group(function () {
    Route::get('hr/dashboard', [\App\Http\Controllers\Admin\HRDashboardController::class, 'dashboard'])->name('hr.dashboard');
});
