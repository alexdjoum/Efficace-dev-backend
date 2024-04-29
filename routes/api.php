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

Route::controller(\App\Http\Controllers\AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('resend-code', 'resendCode');
    Route::post('get-reset-code', 'getResetCode');
    Route::post('verify-reset-code', 'verifyResetCode');
    Route::post('reset-password', 'resetPassword');
});
Route::middleware(['auth', 'verified'])->group(function () {
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
        Route::get('logs', 'logs');
        Route::post('activate-account', 'activateAccount');
    });
    Route::get('backups', [\App\Http\Controllers\BackupController::class, 'index']);
    Route::get('backups/{backup}/download', [\App\Http\Controllers\BackupController::class, 'download']);
    Route::delete('backups/{backup}', [\App\Http\Controllers\BackupController::class, 'destroy']);
    Route::apiResource('accommodations', \App\Http\Controllers\AccommodationController::class);
    Route::apiResource('properties', \App\Http\Controllers\PropertyController::class);
    Route::apiResource('lands', \App\Http\Controllers\LandController::class);
    Route::apiResource('retail_spaces', \App\Http\Controllers\RetailSpaceController::class);
    Route::apiResource('virtuals', \App\Http\Controllers\VirtualController::class);
    Route::apiResource('products', \App\Http\Controllers\ProductController::class);
    Route::apiResource('orders', \App\Http\Controllers\OrderController::class);
    Route::apiResource('propositions', \App\Http\Controllers\PropositionController::class);
    Route::apiResource('contracts', \App\Http\Controllers\ContractController::class)->except('index', 'show');
});
