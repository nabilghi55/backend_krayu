<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\FrontController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentCallbackController;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('login', [AuthUserController::class, 'login']);
Route::post('register', [AuthUserController::class, 'register']);

Route::post('password/forgot', [AuthUserController::class, 'forgotPassword']);
Route::post('password/reset/{token}', [AuthUserController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthUserController::class, 'profile']);
    
    Route::post('/profile/update', [AuthUserController::class, 'updateProfile']);
    
    Route::post('/address/update', [AuthUserController::class, 'updateAddress']);
    
    Route::post('/password/change', [AuthUserController::class, 'changePassword']);
    
    Route::post('/logout', [AuthUserController::class, 'logout']);
    Route::post('/checkout', [CartController::class, 'checkoutApi']);

    
});
Route::post('/payments/midtrans-notification', [PaymentCallbackController::class, 'receive']);
Route::get('/dummy-payment-callback', [PaymentCallbackController::class, 'dummyCallback']);
Route::get('/products', [FrontController::class, 'index']);
Route::get('/product/{slug}', [ShopController::class, 'product']);