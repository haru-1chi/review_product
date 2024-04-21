<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\userController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::group([
    'prefix' => 'auth',
    'middleware' => 'api',
], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('resetPassword', [AuthController::class, 'resetPassword']);
    Route::post('password/forgot', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.forgot');
    Route::post('password/reset', [ForgotPasswordController::class, 'reset'])->name('password.reset');
    Route::get('me', [AuthController::class, 'me']);
    
    Route::middleware(['auth'])->group(function () {
        Route::post('update_user', [userController::class, 'update_user']);
        Route::get('getDetail_user', [userController::class, 'getDetail_user']);
    
        Route::get('getList_product', [userController::class, 'getList_product']);
        Route::get('getDetail_product/{id}', [userController::class, 'getDetail_product']);
    
        Route::post('review', [userController::class, 'review']);
        Route::get('getHistory_review', [userController::class, 'getHistory_review']);
        Route::post('update_review/{id}', [userController::class, 'update_review']);
        Route::post('delete_review/{id}', [userController::class, 'delete_review']);
    });
}); 

Route::middleware(['admin'])->group(function () {
    Route::get('getList_user', [AdminController::class, 'getList_user']);
    Route::post('delete_user/{id}', [AdminController::class, 'delete_user']);
    Route::get('getDetail_user/{id}', [AdminController::class, 'getDetail_user']);

    Route::post('insert_product', [AdminController::class, 'insert_product']);
    Route::post('update_product/{id}', [AdminController::class, 'update_product']);
    Route::post('delete_product/{id}', [AdminController::class, 'delete_product']);
    Route::get('getList_product', [AdminController::class, 'getList_product']);
    Route::get('getDetail_product/{id}', [AdminController::class, 'getDetail_product']);
});

