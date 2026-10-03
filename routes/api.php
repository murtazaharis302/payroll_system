<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Api\DeliAttendanceUploadController;

// Public machine-to-machine DELI automatic attendance upload endpoint
Route::post('/deli/attendance/{publicId}/{token}', [DeliAttendanceUploadController::class, 'handle'])
    ->name('api.deli.attendance');

Route::middleware(['web'])->group(function () {
    Route::get('/employees/search', [EmployeeController::class, 'ajaxSearch'])->name('api.employees.search');
});
