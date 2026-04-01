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
Route::post('/registerWorker', [\App\Http\Controllers\AuthController::class, 'registerWorker']);

Route::controller(\App\Http\Controllers\AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('loginWorker', 'loginWorker');
    Route::post('resend-code', 'resendCode')->name('resend-code');
    Route::post('get-reset-code', 'getResetCode')->name('get-reset-code');
    Route::post('verify-reset-code', 'verifyResetCode')->name('verify-reset-code');
    Route::post('reset-password', 'resetPassword')->name('reset-password');
});

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
Route::post('products/payment-plan', [\App\Http\Controllers\ProductController::class, 'calculatePaymentPlan']);
Route::patch('products/{product}', [\App\Http\Controllers\ProductController::class, 'update']);
Route::get('/appointments', [\App\Http\Controllers\AppointmentController::class, 'index']);
Route::post('/appointments', [\App\Http\Controllers\AppointmentController::class, 'store']);
Route::patch('/appointments/{id}/status', [\App\Http\Controllers\AppointmentController::class, 'update']); 
Route::post('/properties/{property}/operating-ratios', [\App\Http\Controllers\OperatingRatioExcludingTaxController::class, 'store']);
Route::post('/properties/{property}/building-investments', [\App\Http\Controllers\BuildingInvestmentController::class, 'store']);
Route::post('/properties/{property}/building-finance', [\App\Http\Controllers\BuildingFinanceController::class, 'store']);
Route::post('/properties/{property}/parts', [\App\Http\Controllers\PropertyController::class, 'add_parts']);
Route::patch('/properties/{property}/parts/{part}', [\App\Http\Controllers\PropertyController::class, 'update_one_part']);
Route::delete('/properties/{property}/parts/{part}', [\App\Http\Controllers\PropertyController::class, 'delete_part']);
Route::delete('/projects/{project}/images/{image}', [\App\Http\Controllers\ProjectController::class, 'deleteImage']);
Route::delete('/projects/{project}/files/{file}', [\App\Http\Controllers\ProjectController::class, 'deleteFile']);
Route::post('/projects', [\App\Http\Controllers\ProjectController::class, 'store']);
Route::get('/projects', [\App\Http\Controllers\ProjectController::class, 'index']);
Route::patch('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'update']);
Route::delete('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'destroy']);
Route::post('/cache/clear', [\App\Http\Controllers\ProductController::class, 'clearCache']);
Route::get('/cache/stats', [\App\Http\Controllers\ProductController::class, 'cacheStats']);
Route::post('/projects/{project}/solds', [\App\Http\Controllers\ProjectController::class, 'createProjectSold']);
Route::get('/projects/{project}/solds', [\App\Http\Controllers\ProjectController::class, 'getProjectSolds']);
Route::delete('/projects/{project}/solds/{sold}', [\App\Http\Controllers\ProjectController::class, 'deleteProjectSold']);
Route::get('/lots', [\App\Http\Controllers\LotController::class, 'index']);
Route::get('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'show']);
Route::get('/worker/accepted-projects', [\App\Http\Controllers\ProjectController::class, 'workerAcceptedProjects']);

Route::post('products/{productId}/land-investment-analysis', [\App\Http\Controllers\ProductController::class, 'landInvestmentAnalysis']);

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/admin/create-user', [\App\Http\Controllers\AuthController::class, 'createAdminUser']);
    Route::get('/admin/users', [\App\Http\Controllers\AuthController::class, 'listAdminUsers']);
    Route::get('/admin/commercials', [\App\Http\Controllers\AuthController::class, 'listCommercials']);
    Route::patch('/admin/users/{id}', [\App\Http\Controllers\AuthController::class, 'updateAdminUser']);

    Route::post('/admin/contracts', [\App\Http\Controllers\ContractController::class, 'store']);
    Route::get('/admin/contracts/{id}', [\App\Http\Controllers\ContractController::class, 'showForAdmin']);
    Route::get('/admin/contracts', [\App\Http\Controllers\ContractController::class, 'listAll']);
    Route::delete('/admin/contracts/{id}', [\App\Http\Controllers\ContractController::class, 'destroy']);
    Route::post('/products/{productId}/propose-land', [\App\Http\Controllers\ProductController::class, 'proposeLand']);
    Route::post('/products/{productId}/propose-property', [\App\Http\Controllers\ProductController::class, 'proposeProperty']);
});

Route::middleware(['auth', 'verified', 'role:engin'])->group(function () {
    Route::get('/engin/my-notifications', [\App\Http\Controllers\ProjectEnginController::class, 'myNotifications']);
    Route::patch('/engin/notifications/{id}/respond', [\App\Http\Controllers\ProjectEnginController::class, 'respondToNotification']);
});

Route::middleware(['auth', 'verified', 'role:admin,validator'])->group(function () {
    Route::patch('/projects/{project}/set-amounts', [\App\Http\Controllers\ProjectController::class, 'setProjectAmounts']);
    Route::patch('/projects/{project}/accept', [\App\Http\Controllers\ProjectController::class, 'acceptProject']);
    Route::post('/projects/{project}/observations', [\App\Http\Controllers\ObservationController::class, 'store']);
    Route::get('/projects/{project}/observations', [\App\Http\Controllers\ObservationController::class, 'index']);
    Route::patch('/projects/{project}/observations/{observation}', [\App\Http\Controllers\ObservationController::class, 'update']);
    Route::delete('/projects/{project}/observations/{observation}', [\App\Http\Controllers\ObservationController::class, 'destroy']);

    Route::post('/projects/{id}/set-launch-info', [\App\Http\Controllers\ProjectController::class, 'setLaunchInfo']);
    Route::patch('/projects/{id}/update-launch-info', [\App\Http\Controllers\ProjectController::class, 'updateLaunchInfo']);
    Route::delete('/projects/{id}/delete-launch-info', [\App\Http\Controllers\ProjectController::class, 'deleteLaunchInfo']);
    Route::patch('/projects/{id}/set-end-date', [\App\Http\Controllers\ProjectController::class, 'setEndDate']);


    Route::post('/projects/{projectId}/assign-user', [\App\Http\Controllers\ProjectController::class, 'assignUser']);
    Route::get('/projects/{projectId}/assigned-users', [\App\Http\Controllers\ProjectController::class, 'listAssignedUsers']);
    Route::patch('/project-users/{id}', [\App\Http\Controllers\ProjectController::class, 'updateAssignment']);
    Route::delete('/project-users/{id}', [\App\Http\Controllers\ProjectController::class, 'removeUser']);

    Route::get('/projects/{projectId}/available-workers', [\App\Http\Controllers\ProjectController::class, 'availableWorkers']);

    Route::post('/admin/accounts', [\App\Http\Controllers\AuthController::class, 'listAccounts']);
    Route::patch('/admin/users/{id}/update-account-status', [\App\Http\Controllers\AuthController::class, 'updateAccountStatus']);

    Route::post('/admin/commissions', [\App\Http\Controllers\CommissionController::class, 'store']);
    Route::post('/admin/commissions/list', [\App\Http\Controllers\CommissionController::class, 'index']);
    Route::delete('/admin/commissions/{id}', [\App\Http\Controllers\CommissionController::class, 'destroy']);


    Route::post('/admin/payment-salespersons', [\App\Http\Controllers\PaymentSalespersonController::class, 'store']);
    Route::get('/admin/commissions/{commissionId}/payments', [\App\Http\Controllers\PaymentSalespersonController::class, 'commissionPayments']);
    Route::delete('/admin/payment-salespersons/{id}', [\App\Http\Controllers\PaymentSalespersonController::class, 'destroy']);

    Route::post('/admin/request-for-sales', [\App\Http\Controllers\RequestForSaleController::class, 'index']);
    Route::patch('/admin/request-for-sales/{id}/status', [\App\Http\Controllers\RequestForSaleController::class, 'updateStatus']);

    Route::post('/projects/{projectId}/assign-engin', [\App\Http\Controllers\ProjectEnginController::class, 'assignEngin']);
    Route::get('/projects/{projectId}/assigned-engins', [\App\Http\Controllers\ProjectEnginController::class, 'getAssignedEngins']);
    Route::patch('/project-engins/{id}', [\App\Http\Controllers\ProjectEnginController::class, 'update']);
    Route::delete('/project-engins/{id}', [\App\Http\Controllers\ProjectEnginController::class, 'destroy']);
    Route::get('/projects/{projectId}/available-engins', [\App\Http\Controllers\ProjectEnginController::class, 'getAvailableEngins']);

    Route::delete('/projects/{projectId}/engins/{userId}', [\App\Http\Controllers\ProjectEnginController::class, 'removeEngin']);

});

Route::middleware(['auth:sanctum', 'role:commercial'])->group(function () {
    Route::get('/commercial/my-commissions', [\App\Http\Controllers\CommissionController::class, 'myCommissions']);
    Route::get('/commercial/my-payments', [\App\Http\Controllers\PaymentSalespersonController::class, 'myPayments']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/contracts', [\App\Http\Controllers\ContractController::class, 'index']); 
    Route::get('/contracts/{id}', [\App\Http\Controllers\ContractController::class, 'show']);
    Route::get('/contracts/{id}/download', [\App\Http\Controllers\ContractController::class, 'download']);
    Route::post('/request-for-sales', [\App\Http\Controllers\RequestForSaleController::class, 'store']);
    Route::get('/request-for-sales/my-requests', [\App\Http\Controllers\RequestForSaleController::class, 'myRequests']);
    Route::delete('/request-for-sales/{id}', [\App\Http\Controllers\RequestForSaleController::class, 'destroy']);
    Route::get('order-customers/my-orders', [\App\Http\Controllers\OrderCustomerController::class, 'myOrders']);
    Route::apiResource('order-customers', \App\Http\Controllers\OrderCustomerController::class);
    
});

Route::get('/auth/google', [\App\Http\Controllers\GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [\App\Http\Controllers\GoogleAuthController::class, 'handleGoogleCallback']);

Route::middleware('auth:sanctum')->get('/auth/verify', [\App\Http\Controllers\GoogleAuthController::class, 'verifyToken']);

Route::middleware(['auth', 'verified', 'role:manager'])->group(function () {
    Route::post('/manager/my-projects', [\App\Http\Controllers\ProjectController::class, 'myProjects']);
    Route::get('/manager/projects/{id}', [\App\Http\Controllers\ProjectController::class, 'showProject']);
    Route::patch('/manager/projects/{id}/update-launch', [\App\Http\Controllers\ProjectController::class, 'updateProjectLaunch']);
    Route::patch('/manager/project-users/{id}/note', [\App\Http\Controllers\ProjectController::class, 'noteWorker']);
    Route::delete('/projects/{projectId}/workers/{userId}', [\App\Http\Controllers\ProjectController::class, 'removeWorker']);
});


Route::get('/worker/stats', [\App\Http\Controllers\ProjectController::class, 'workerStats']);
Route::get('/public/projects', [\App\Http\Controllers\ProjectController::class, 'publicIndex']);
Route::get('/public/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'publicShow']);
Route::get('/file/{path}', [\App\Http\Controllers\FileController::class, 'serve'])
    ->where('path', '.*');

Route::post('/worker/availabilities', [\App\Http\Controllers\WorkerAvailabilityController::class, 'store']);
Route::get('/worker/availabilities', [\App\Http\Controllers\WorkerAvailabilityController::class, 'index']);
Route::post('/worker/availabilities/filter', [\App\Http\Controllers\WorkerAvailabilityController::class, 'index']);
Route::patch('/worker/availabilities/{id}', [\App\Http\Controllers\WorkerAvailabilityController::class, 'update']);
Route::delete('/worker/availabilities/{id}', [\App\Http\Controllers\WorkerAvailabilityController::class, 'destroy']);

// Route::get('/jobs/{jobId}/available-workers', [\App\Http\Controllers\JobController::class, 'availableWorkers']);
// Route::post('/jobs', [\App\Http\Controllers\JobController::class, 'store']);
// Route::get('/jobs', [\App\Http\Controllers\JobController::class, 'index']);
// Route::get('/jobs/{id}', [\App\Http\Controllers\JobController::class, 'show']);
// Route::delete('/jobs/{id}', [\App\Http\Controllers\JobController::class, 'destroy']);

// Route::patch('/jobs/workers/note', [\App\Http\Controllers\JobController::class, 'addNote']);

Route::get('/localisation-workers', [\App\Http\Controllers\LocalisationWorkerController::class, 'index']);

Route::get('/workers', [\App\Http\Controllers\UserController::class, 'listWorkers']);
// Route::post('/jobs/{jobId}/workers', [\App\Http\Controllers\JobController::class, 'addWorker']);
Route::post('/lots', [\App\Http\Controllers\LotController::class, 'index']);
Route::post('/lots/create', [\App\Http\Controllers\LotController::class, 'store']);
Route::get('/lots/main', [\App\Http\Controllers\LotController::class, 'mainLots']);

Route::options('/file/{path}', function() {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->header('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('path', '.*');
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

    Route::get('/worker/my-notifications', [\App\Http\Controllers\ProjectController::class, 'myNotifications']);
    Route::patch('/worker/notifications/{id}/respond', [\App\Http\Controllers\ProjectController::class, 'respondToNotification']);
    Route::get('/worker/my-projects', [\App\Http\Controllers\ProjectController::class, 'myWorkerProjects']); 
});

