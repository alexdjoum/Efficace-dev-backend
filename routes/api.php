<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('login', [\App\Http\Controllers\AuthController::class, 'login'])->name('login');
Route::post('register', [\App\Http\Controllers\AuthController::class, 'register'])->name('register');
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('customers', \App\Http\Controllers\CustomerController::class);
    Route::apiResource('roles', \App\Http\Controllers\RoleController::class);
    Route::apiResource('employees', \App\Http\Controllers\EmployeeController::class);
    Route::get('permissions', [\App\Http\Controllers\RoleController::class, 'permissions']);
    Route::controller(\App\Http\Controllers\AuthController::class)->group(function () {
        Route::post('logout', 'logout');
        Route::post('logout-all', 'logoutAll');
        Route::get('current', 'current');
        Route::post('change-password', 'changePassword');
        Route::post('update-profile', 'updateProfile');
    });
});
