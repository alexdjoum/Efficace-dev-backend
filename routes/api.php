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

// Route::prefix('auth')
//     ->controller(\App\Http\Controllers\AuthController::class)
//     ->name('auth.')
//     ->group(function () {
//     Route::post('login', 'login');
//     Route::post('register', 'register');
//     Route::post('resend-code', 'resendCode');
//     Route::post('get-reset-code', 'getResetCode');
//     Route::post('verify-reset-code', 'verifyResetCode');
//     Route::post('reset-password', 'resetPassword');
//     //Route::apiResource('products', \App\Http\Controllers\ProductController::class);
//     //Route::apiResource('lands', \App\Http\Controllers\LandController::class);
// });

Route::controller(\App\Http\Controllers\AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('resend-code', 'resendCode')->name('resend-code');
    Route::post('get-reset-code', 'getResetCode')->name('get-reset-code');
    Route::post('verify-reset-code', 'verifyResetCode')->name('verify-reset-code');
    Route::post('reset-password', 'resetPassword')->name('reset-password');
});

// Route::middleware(['auth:sanctum'])->get('/test-auth', function (Request $request) {
//     return response()->json([
//         'authenticated' => true,
//         'user' => $request->user()->email,
//         'roles' => $request->user()->roles->pluck('name'),
//         'is_admin' => $request->user()->isAdmin(),
//     ]);
// });

// Route::middleware(['auth:sanctum', 'role:admin'])->get('/test-admin', function (Request $request) {
//     return response()->json([
//         'message' => 'Vous êtes admin !',
//         'user' => $request->user()->email,
//     ]);
// });

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::post('/lands', [\App\Http\Controllers\LandController::class, 'store']);
    Route::delete('/lands/{id}', [\App\Http\Controllers\LandController::class, 'destroy']);
    Route::post('/products', [\App\Http\Controllers\ProductController::class, 'store']);
    Route::delete('/products/{id}', [\App\Http\Controllers\ProductController::class, 'store']);
    Route::post('/properties', [\App\Http\Controllers\PropertyController::class, 'store']);
    
});
Route::get('/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'show']);
Route::get('/properties', [\App\Http\Controllers\PropertyController::class, 'index']);
Route::match(['PUT', 'PATCH'], '/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'update']);
Route::get('lands', [\App\Http\Controllers\LandController::class, 'index']);
Route::middleware(['auth:api', 'role:admin'])->post('admin/users/create', [\App\Http\Controllers\UserController::class, 'createUser']);
Route::get('products', [\App\Http\Controllers\ProductController::class, 'index']);
Route::patch('products/{product}', [\App\Http\Controllers\ProductController::class, 'update']);
Route::get('/appointments', [\App\Http\Controllers\AppointmentController::class, 'index']);
Route::post('/appointments', [\App\Http\Controllers\AppointmentController::class, 'store']);
Route::patch('/appointments/{id}/status', [\App\Http\Controllers\AppointmentController::class, 'update']); 
Route::post('/properties/{property}/operating-ratios', [\App\Http\Controllers\OperatingRatioExcludingTaxController::class, 'store']);
Route::post('/properties/{property}/building-investments', [\App\Http\Controllers\BuildingInvestmentController::class, 'store']);
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
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
    //Route::apiResource('properties', \App\Http\Controllers\PropertyController::class);
    // Route::apiResource('lands', \App\Http\Controllers\LandController::class);
    Route::apiResource('retail_spaces', \App\Http\Controllers\RetailSpaceController::class);
    Route::apiResource('virtuals', \App\Http\Controllers\VirtualController::class);
    // Route::apiResource('products', \App\Http\Controllers\ProductController::class);
    Route::apiResource('orders', \App\Http\Controllers\OrderController::class);
    Route::apiResource('propositions', \App\Http\Controllers\PropositionController::class);
    Route::apiResource('contracts', \App\Http\Controllers\ContractController::class)->except('index', 'show');
});





// Routes accessibles uniquement par Admin
// Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
//     Route::post('/users', [UserController::class, 'store']);
//     Route::delete('/users/{id}', [UserController::class, 'destroy']);
//     Route::put('/users/{id}/assign-role', [UserController::class, 'assignRole']);
// });

// // Routes accessibles par Admin et Validator
// Route::middleware(['auth', 'verified', 'role:admin,validator'])->group(function () {
//     Route::post('/corrections/{id}/validate', [CorrectionController::class, 'validate']);
//     Route::post('/corrections/{id}/reject', [CorrectionController::class, 'reject']);
//     Route::get('/reports', [ReportController::class, 'index']);
// });

// // Routes accessibles par Admin, Validator et Corrector
// Route::middleware(['auth', 'verified', 'role:admin,validator,corrector'])->group(function () {
//     Route::get('/corrections', [CorrectionController::class, 'index']);
//     Route::post('/corrections', [CorrectionController::class, 'store']);
//     Route::put('/corrections/{id}', [CorrectionController::class, 'update']);
//     Route::get('/corrections/{id}', [CorrectionController::class, 'show']);
// });

// // Routes accessibles par Admin et Manager
// Route::middleware(['auth', 'verified', 'role:admin,manager'])->group(function () {
//     Route::apiResource('resources', ResourceController::class);
//     Route::get('/statistics', [StatisticsController::class, 'index']);
// });

// // Utilisation avec permissions spécifiques
// Route::middleware(['auth', 'verified', 'permission:validate-correction'])->group(function () {
//     Route::post('/corrections/{id}/validate', [CorrectionController::class, 'validate']);
// });

// // Utilisation avec niveau hiérarchique minimum
// // Niveau 3 = Validator et au-dessus (Admin)
// Route::middleware(['auth', 'verified', 'hierarchy:3'])->group(function () {
//     Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
//     Route::get('/admin/reports', [ReportController::class, 'adminReports']);
// });

// // Niveau 2 = Corrector et au-dessus (Validator, Admin)
// Route::middleware(['auth', 'verified', 'hierarchy:2'])->group(function () {
//     Route::get('/corrections/my-corrections', [CorrectionController::class, 'myCorrections']);
// });