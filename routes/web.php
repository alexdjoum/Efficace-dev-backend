<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;


Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/file/{path}', [FileController::class, 'serve'])
    ->where('path', '.*')
    ->name('file.serve');

Route::options('/file/{path}', function() {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('path', '.*');

// require __DIR__.'/auth.php';