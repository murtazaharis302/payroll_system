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

// Password Reset / Account Setup Routes
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
// Logout Route
Route::post('logout', [userController::class, 'logout'])->name('logout')->middleware('auth');

// Self-service account security routes are intentionally independent of the
// payroll permission matrix and active-company selection.
Route::middleware('auth')->group(function () {
    foreach (['admin', 'company', 'hr', 'employee'] as $portal) {
        Route::prefix($portal)->name($portal.'.')->group(function () {
            Route::get('settings', [SettingsController::class, 'index'])->name('settings');
            Route::post('settings/update-password', [SettingsController::class, 'updatePassword'])
                ->name('settings.update-password');
        });
    }
});

// ============================================
// LOGIN & PUBLIC ROUTES
// ============================================

// ADMIN LOGIN ROUTES
Route::get('admin', [DashboardController::class, 'index']);
Route::get('admin/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('IsAdmin:adminForm');
Route::post('admin/login', [LoginController::class, 'login'])->middleware('IsAdmin:adminForm');

// COMPANY LOGIN ROUTES
Route::get('company', [companyController::class, 'redirectToCompanyLogin']);
Route::get('company/login', [companyController::class, 'showLoginForm'])->name('companyLoginForm')->middleware('IsAdmin:companyForm');
Route::post('company/login', [LoginController::class, 'login'])->name('companyLogin')->middleware('IsAdmin:companyForm');

// EMPLOYEE LOGIN ROUTES
Route::get('employee', [EmployeeController::class, 'redirectToEmployeeLogin']);
Route::get('employee/login', [EmployeeController::class, 'showLoginForm'])->name('employeeLoginForm')->middleware('IsAdmin:employeeForm');
Route::post('employee/login', [LoginController::class, 'login'])->name('employeeLogin')->middleware('IsAdmin:employeeForm');

// HR LOGIN ROUTES
Route::get('hr', function () {
    return redirect('hr/login');
});
Route::get('hr/login', [EmployeeController::class, 'showLoginForm'])->name('hrLoginForm')->middleware('IsAdmin:hrForm');
Route::post('hr/login', [LoginController::class, 'login'])->name('hrLogin')->middleware('IsAdmin:hrForm');

// // ACCOUNTING LOGIN ROUTES
// Route::get('accounting', function(){ return redirect('accounting/login'); });
// Route::get('accounting/login', [EmployeeController::class, 'showLoginForm'])->name('accountingLoginForm')->middleware('IsAdmin:accountingForm');
// Route::post('accounting/login', [LoginController::class, 'login'])->name('accountingLogin')->middleware('IsAdmin:accounting');

// USER (WEBSITE) LOGIN & PUBLIC ROUTES
Route::post('user/signin', [SigninController::class, 'signin'])->name('user.signin')->middleware('IsUser');
Route::post('user/signup', [SignupController::class, 'signup'])->name('user.signup');
Route::get('/confirm-email/{token}', [SignupController::class, 'confirm'])->name('confirm.email');
Route::get('/', [WebsiteHomeController::class, 'index'])->name('home');

// ============================================
// WEBSITE/USER ROUTES (IsUser Hard Boundary)
// ============================================
Route::middleware(['auth', 'IsUser'])->group(function () {
    Route::get('user/dashboard', [WebsiteDashboardController::class, 'index'])->name('user.dashboard');
    Route::get('user/edit-profile', [EditProfileController::class, 'edit'])->name('user.edit_profile');
    Route::post('user/edit-profile', [EditProfileController::class, 'update'])->name('user.update_profile');
});

// ============================================
// SUPER ADMIN ROUTES (SuperAdmin Hard Boundary)
// ============================================
Route::prefix('admin')->middleware(['auth', 'SuperAdmin'])->name('admin.')->group(function () {
    // Dashboard
    Route::get('dashboard', [\App\Http\Controllers\Admin\SuperAdminDashboardController::class, 'dashboard'])->name('dashboard');

    // Profile
    Route::get('/edit-profile/{id}', [ProfileController::class, 'editProfile'])->name('edit_profile');
    Route::post('/edit-profile/{id}', [ProfileController::class, 'updateProfile'])->name('update_profile');

    // Users Management
    Route::get('users', [userController::class, 'index'])->name('users');
    Route::get('createuser', [userController::class, 'create'])->name('createUser');
    Route::post('storeuser', [userController::class, 'store'])->name('storeUser');
    Route::get('edituser/{id}', [userController::class, 'edit'])->name('editUser');
    Route::put('updateuser/{id}', [userController::class, 'update'])->name('updateUser');
    Route::delete('deleteuser/{id}', [userController::class, 'destroy'])->name('deleteUser');

    // Companies Management (Tenant Management)
    Route::get('companies', [companyController::class, 'index'])->name('companies');
    Route::get('createCompany', [companyController::class, 'create'])->name('createCompany');
    Route::post('storeCompany', [companyController::class, 'store'])->name('storeCompany');
    Route::get('editCompany/{id}', [companyController::class, 'edit'])->name('editCompany');
    Route::put('updateCompany/{id}', [companyController::class, 'update'])->name('updateCompany');
    Route::get('deactivatedCompanies', [companyController::class, 'deactivatedCompanies'])->name('deactivatedCompanies');
    Route::post('deactivate/{id}', [companyController::class, 'deactivate'])->name('deactivateCompany');
    Route::post('activateCompany/{id}', [companyController::class, 'activateCompany'])->name('activateCompany');
    Route::delete('destroyPending/{id}', [companyController::class, 'destroyPending'])->name('destroyPendingCompany');
    Route::get('pending', [companyController::class, 'pending'])->name('pending');
    Route::post('aprovePendingCompany/{id}', [companyController::class, 'aprovePending'])->name('aprovePendingCompany');

    // Activity Logs
    Route::get('activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('activity-logs/export', [\App\Http\Controllers\Admin\ActivityLogController::class, 'export'])->name('activity-logs.export');
});

// ============================================
// SUPER ADMIN HR PERMISSIONS ROUTES
// ============================================
Route::prefix('admin')
    ->middleware(['auth', 'SuperAdmin'])
    ->name('admin.')
    ->group(function () {
        Route::get('hr-permissions', [\App\Http\Controllers\Admin\HrPermissionController::class, 'index'])
            ->name('hr-permissions.index');

        Route::post('hr-permissions', [\App\Http\Controllers\Admin\HrPermissionController::class, 'update'])
            ->name('hr-permissions.update');
    });

Route::prefix('super-admin')->middleware(['auth', 'SuperAdmin'])->group(function () {
    Route::get('companies/{company}/permissions', [\App\Http\Controllers\Admin\PermissionMatrixController::class, 'showCompanyPermissions'])->name('super_admin.companies.permissions');
    Route::post('companies/{company}/permissions/toggle', [\App\Http\Controllers\Admin\PermissionMatrixController::class, 'toggleCompanyEntitlement'])->name('super_admin.companies.permissions.toggle');
});

// ============================================
// TENANT ROUTES (CompanyAuth Hard Boundary)
// ============================================
