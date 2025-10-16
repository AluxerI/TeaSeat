<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\User\ShowCurrentUserController;
use App\Http\Controllers\Auth\DeleteAccountController;
use App\Http\Controllers\Auth\LogoutFromAllDevicesController;
use App\Http\Controllers\Auth\SessionController;


Route::prefix('auth')->group(function () {
    // Основные эндпоинты
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/login/{provider}', [SocialAuthController::class, 'handleProviderCallback']); // для социального входа
    
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

    Route::delete('/account-delete', DeleteAccountController::class)->middleware('auth:sanctum');
});



Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::post('/auth/logout-all', [LogoutFromAllDevicesController::class, '__invoke']);
    Route::get('/auth/sessions', [SessionController::class, '__invoke']);
    Route::get('/user', [ShowCurrentUserController::class, '__invoke'])->name('user.show');
    Route::get('/user/discounts', 'App\Http\Controllers\User\UserDiscountsController')->name('user.discounts');
    
    // Корзина
    Route::group(['prefix' => 'cart', 'middleware' => 'auth:sanctum', 'namespace' => 'App\Http\Controllers\Cart'], function () {
        Route::get('/', 'IndexController') -> name('cart.index');
        Route::post('/add', 'AddController') -> name('cart.add');
        Route::put('/update/{itemId}', 'UpdateItemController') -> name('cart.update');
        Route::delete('/remove/{itemId}', 'RemoveItemController') -> name('cart.remove');
        Route::delete('/clear', 'ClearCartController');
    });
});

// Управление пользователями (только для админов)
Route::group(['namespace' => 'App\Http\Controllers\User'], function() {
    Route::get('/users', 'IndexController') -> name('user.index')
        ->middleware(['auth:sanctum', 'permission:view users']);

    Route::get('/users/{user}', 'ShowController') -> name('user.show')
        ->middleware(['auth:sanctum', 'permission:view users']);

    Route::put('/users/{user}', 'UpdateController') -> name('user.update')
        ->middleware(['auth:sanctum', 'permission:manage users']);

    Route::put('/users/{user}/roles', 'UpdateRolesController') -> name('user.updateroles')
        ->middleware(['auth:sanctum', 'permission:manage users']);

    Route::delete('/users-delete/{user}', 'DeleteUserController') -> name('user.delete')
        ->middleware(['auth:sanctum', 'can:delete users']);
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