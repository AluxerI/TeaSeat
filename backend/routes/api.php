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
use App\Http\Controllers\cart\CheckoutController;
use App\Http\Controllers\Cart\QuoteController;
use App\Http\Controllers\Order\Admin\AdminOrderActionController;
use App\Http\Controllers\Order\Admin\AdminOrderController;
use App\Http\Controllers\Seller\BootstrapController as SellerBootstrapController;
use App\Http\Controllers\Seller\DeviceController as SellerDeviceController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\SyncController as SellerSyncController;


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

    //Адреса
    Route::group(['prefix' => 'addresses',  'namespace' => '\App\Http\Controllers\User\Address'], function () {
    Route::get('/', action: 'IndexController')->name('address.index');
    Route::post('/store', 'StoreController')->name('address.store');
    });
    
    // Корзина
    Route::group(['prefix' => 'cart',  'namespace' => 'App\Http\Controllers\Cart'], function () {
        Route::get('/', 'IndexController') -> name('cart.index');
        Route::post('/add', 'AddController') -> name('cart.add');
        Route::put('/update/{itemId}', 'UpdateItemController') -> name('cart.update');
        Route::delete('/remove/{itemId}', 'RemoveItemController') -> name('cart.remove');
        Route::delete('/clear', 'ClearCartController');
        Route::post('/quote', QuoteController::class)
            ->middleware('throttle:60,1')
            ->name('cart.quote');
    });
        // Оформление заказа
    Route::prefix('checkout')->group(function () {
        Route::post('/', [CheckoutController::class, '__invoke']);
        Route::get('/delivery-methods/{addressId}', [CheckoutController::class, 'getDeliveryMethods']);
    });
    
    // Управление заказами
    Route::group(['prefix' => 'orders',  'namespace' => 'App\Http\Controllers\Order'], function () {
        Route::get('/', 'IndexController') -> name('orders.index');
        Route::get('/{order}', 'ShowController') -> name('orders.show');
        Route::put('/{order}/cancel',  'CancelController')->name('cancel');
    });
});

// PWA продавца. Пользователь определяется только по Sanctum-токену, а
// устройство — по заголовку X-Device-UUID после регистрации.
Route::prefix('seller')->middleware('auth:sanctum')->group(function () {
    Route::post('/devices/register', [SellerDeviceController::class, 'store'])
        ->middleware('permission:create seller orders');

    Route::get('/bootstrap', SellerBootstrapController::class)
        ->middleware(['permission:create seller orders', 'throttle:30,1']);

    Route::get('/orders', [SellerOrderController::class, 'index'])
        ->middleware('permission:view own seller orders');
    Route::get('/orders/{order}', [SellerOrderController::class, 'show'])
        ->whereNumber('order')
        ->middleware('permission:view own seller orders');
    Route::post('/orders', [SellerOrderController::class, 'store'])
        ->middleware('permission:create seller orders');
    Route::put('/orders/{order}', [SellerOrderController::class, 'update'])
        ->whereNumber('order')
        ->middleware('permission:create seller orders');
    Route::post('/orders/{order}/cancel', [SellerOrderController::class, 'cancel'])
        ->whereNumber('order')
        ->middleware('permission:create seller orders');
    Route::post('/orders/complete', [SellerOrderController::class, 'complete'])
        ->middleware('permission:complete own seller orders');
    Route::post('/orders/{order}/escalate', [SellerOrderController::class, 'escalate'])
        ->whereNumber('order')
        ->middleware('permission:complete own seller orders');

    Route::post('/sync', SellerSyncController::class)
        ->middleware([
            'permission:create seller orders',
            'permission:complete own seller orders',
            'throttle:30,1',
        ]);
});

// Управление пользователями (только для админов)
Route::group(['namespace' => 'App\Http\Controllers\User'], function() {
    Route::get('/users', 'IndexController') -> name('user.index')
        ->middleware(['auth:sanctum', 'permission:view users']);

    Route::get('/users/{user}', 'ShowController') -> name('user.show.admin')
        ->middleware(['auth:sanctum', 'permission:view users']);

    Route::put('/users/{user}', 'UpdateController') -> name('user.update')
        ->middleware(['auth:sanctum', 'permission:manage users']);

    Route::put('/users/{user}/roles', 'UpdateRolesController') -> name('user.updateroles')
        ->middleware(['auth:sanctum', 'permission:manage users']);

    Route::delete('/users-delete/{user}', 'DeleteUserController') -> name('user.delete')
        ->middleware(['auth:sanctum', 'can:delete users']);
});

//Управление заказов для менеджеров или админов
Route::prefix('admin')->middleware(['auth:sanctum', 'permission:manage orders'])->group(function () {
    
    // Управление заказами
    Route::prefix('orders')->group(function () {
        // Основные эндпоинты
        Route::get('/', [AdminOrderController::class, 'index']);
        Route::get('/stats', [AdminOrderController::class, 'stats']);
        Route::get('/{order}', [AdminOrderController::class, 'show']);
        Route::put('/{order}/status', [AdminOrderController::class, 'updateStatus']);
        Route::put('/{order}/tracking', [AdminOrderController::class, 'updateTracking']);
        Route::put('/{order}/internal-notes', [AdminOrderController::class, 'updateInternalNotes']);
        
        // Действия с заказами
        Route::prefix('{order}')->group(function () {
            Route::put('/cancel', [AdminOrderActionController::class, 'cancel']);
            Route::put('/confirm', [AdminOrderActionController::class, 'confirm']);
            Route::put('/ship', [AdminOrderActionController::class, 'markAsShipped']);
            Route::put('/deliver', [AdminOrderActionController::class, 'markAsDelivered']);
            Route::put('/delivery-method', [AdminOrderActionController::class, 'updateDeliveryMethod']);
        });
    });
});

// каталоги
Route::group(['namespace' => 'App\Http\Controllers\Item'], function() {
    Route::get('/item/{productId}', 'ShowController')->name('item.show');
    Route::get('/catalog', 'IndexController')->name('item.index');
    Route::get('/categories', 'Category\IndexController')->name('category.index');
    Route::get('/cities', 'LocationController@getCities');
    Route::get('/cities/{city}/items', 'LocationController@getProductsInCity');
    Route::get('/cities/{city}/items/{productId}/availability', 'LocationController@getProductAvailabilityDetails');
});

// для менеджеров
Route::group([
    'namespace' => 'App\Http\Controllers\Item',
    'middleware' => 'auth:sanctum'
], function() {
    Route::get('/items/create', 'CreateController')->name('item.create.man')->middleware('can:create products');
    Route::post('/items', 'StoreController')->name('item.store.man')->middleware('can:create products');
    Route::get('/items/{product}/edit', 'EditController')->name('item.edit.man')->middleware('can:edit products');
    Route::put('/items/{product}', 'UpdateController')->name('item.update.man')->middleware('can:edit products');
    Route::post('/items/upload-images', action: 'Image\StoreController')->name('image.store.man')->middleware('can:edit products');
    Route::delete('/items/images/{image}', 'Image\DeleteController')->name('image.delete.man')->middleware('can:edit products');
    Route::post('/items/images/{image}/set-main', 'Image\SetmainController')->name('image.setMain')->middleware('can:edit products');
});
//избранное
Route::group([
    'namespace' => 'App\Http\Controllers\Wishlist',
    'middleware' => 'auth:sanctum',
    'prefix' => 'wishlist'
], function() {
    Route::get('/', 'IndexController')-> name('wishlist.index');
    Route::post('/', 'StoreController')-> name('wishlist.store');
    Route::delete('/{productId}', 'DestroyController')-> name('wishlist.destroy');
    Route::get('/check/{productId}', 'CheckController')-> name('wishlist.check');
});
