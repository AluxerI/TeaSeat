<?php

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\UserController;


Route::prefix('auth')->group(function () {
    // Основные эндпоинты
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    
    // Восстановление пароля
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
    Route::post('/reset-password', [NewPasswordController::class, 'store']);
    
    // Верификация email
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
        ->name('verification.verify');
    
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware(['auth:sanctum', 'throttle:6,1']);
    
    // Верификация телефона
    Route::post('/send-verification-code', [PhoneVerificationController::class, 'sendVerificationCode']);
    Route::post('/verify-phone', [PhoneVerificationController::class, 'verifyPhone']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', [UserController::class, 'show']);
});

Route::group(['namespace' => 'App\Http\Controllers\Item'], function() {
    Route::get('/item/{productId}', 'ShowController')->name('item.show');
    Route::get('/catalog', 'CatalogController')->name('item.catalog');
});
Route::group([
    'namespace' => 'App\Http\Controllers\Item',
    'middleware' => 'auth:sanctum'
], function() {
    Route::get('/items/create', 'CreateController')->name('item.create')->middleware('can:create products');
    Route::post('/items', 'StoreController')->name('item.store')->middleware('can:create products');
    Route::get('/items/{product}/edit', 'EditController')->name('item.edit')->middleware('can:edit products');
    Route::put('/items/{product}', 'UpdateController')->name('item.update')->middleware('can:edit products');
});