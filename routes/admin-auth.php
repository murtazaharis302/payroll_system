<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisterUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function(){
    Route::get('register', [RegisterUserController::class, 'create'])->name('register');
    Route::post('register', [RegisterUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});
Route::middleware('auth:admin')->group(function(){
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});