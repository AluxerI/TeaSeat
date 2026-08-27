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
use App\Http\Controllers\Cart\CheckoutController;
use App\Http\Controllers\Cart\QuoteController;
use App\Http\Controllers\Order\Admin\AdminOrderActionController;
use App\Http\Controllers\Order\Admin\AdminOrderController;
use App\Http\Controllers\Seller\BootstrapController as SellerBootstrapController;
use App\Http\Controllers\Seller\DeviceController as SellerDeviceController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\SyncController as SellerSyncController;
use App\Http\Controllers\Manager\FulfillmentIssueController as ManagerFulfillmentIssueController;
use App\Http\Controllers\Manager\DeliveryAssignmentController as ManagerDeliveryAssignmentController;
use App\Http\Controllers\Manager\PackedOrderController as ManagerPackedOrderController;
use App\Http\Controllers\Manager\OrderController as ManagerOrderController;
use App\Http\Controllers\Manager\OrderCommandController as ManagerOrderCommandController;
use App\Http\Controllers\Manager\OrderItemController as ManagerOrderItemController;
use App\Http\Controllers\Picker\OrderController as PickerOrderController;
use App\Http\Controllers\Picker\AssembledGiftController as PickerAssembledGiftController;
use App\Http\Controllers\Courier\DeliveryController as CourierDeliveryController;
use App\Http\Controllers\Gift\GiftConstructorController;
use App\Http\Controllers\Cart\GiftController as CartGiftController;
use App\Http\Controllers\Review\ProductReviewController;
use App\Http\Controllers\Review\CustomerReviewController;
use App\Http\Controllers\Review\OrderFeedbackController;
use App\Http\Controllers\Manager\ReviewModerationController;
use App\Http\Controllers\Manager\OrderFeedbackModerationController;
use App\Http\Controllers\Order\OrderRequestController as CustomerOrderRequestController;
use App\Http\Controllers\Manager\OrderRequestController as ManagerOrderRequestController;

Route::get('/products/{product}/reviews', [ProductReviewController::class, 'index'])
    ->whereNumber('product')
    ->name('product.reviews.index');

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

    Route::get('/reviews/mine', [CustomerReviewController::class, 'mine'])
        ->name('reviews.mine');
    Route::post('/products/{product}/reviews', [CustomerReviewController::class, 'store'])
        ->whereNumber('product')
        ->name('product.reviews.store');
    Route::put('/reviews/{review}', [CustomerReviewController::class, 'update'])
        ->whereNumber('review')
        ->name('reviews.update');
    Route::get('/orders/{order}/feedback', [OrderFeedbackController::class, 'show'])
        ->whereNumber('order')
        ->name('orders.feedback.show');
    Route::post('/orders/{order}/feedback', [OrderFeedbackController::class, 'store'])
        ->whereNumber('order')
        ->name('orders.feedback.store');
    Route::put('/order-feedback/{feedback}', [OrderFeedbackController::class, 'update'])
        ->whereNumber('feedback')
        ->name('order-feedback.update');
    Route::get('/orders/{order}/requests', [CustomerOrderRequestController::class, 'index'])
        ->whereNumber('order')
        ->name('orders.requests.index');
    Route::post('/orders/{order}/requests', [CustomerOrderRequestController::class, 'store'])
        ->whereNumber('order')
        ->name('orders.requests.store');
    Route::post('/order-requests/{orderRequest}/withdraw', [CustomerOrderRequestController::class, 'withdraw'])
        ->whereNumber('orderRequest')
        ->name('order-requests.withdraw');

    Route::prefix('gift-constructor')->group(function () {
        Route::get('/boxes/{box}/products', [GiftConstructorController::class, 'boxProducts'])
            ->whereNumber('box')
            ->name('gift-constructor.boxes.products');
        Route::get('/advanced/options', [GiftConstructorController::class, 'advancedOptions'])
            ->name('gift-constructor.advanced.options');
        Route::post('/advanced/validate-layout', [GiftConstructorController::class, 'validateLayout'])
            ->name('gift-constructor.advanced.validate');
        Route::post('/advanced/quote', [GiftConstructorController::class, 'advancedQuote'])
            ->name('gift-constructor.advanced.quote');
        Route::post('/advanced/gifts', [GiftConstructorController::class, 'advancedStore'])
            ->name('gift-constructor.advanced.store');
        Route::get('/simple/options', [GiftConstructorController::class, 'simpleOptions'])
            ->name('gift-constructor.simple.options');
        Route::post('/simple/quote', [GiftConstructorController::class, 'simpleQuote'])
            ->name('gift-constructor.simple.quote');
        Route::post('/simple/gifts', [GiftConstructorController::class, 'simpleStore'])
            ->name('gift-constructor.simple.store');
    });
    Route::get('/gifts', [GiftConstructorController::class, 'index'])->name('gifts.index');
    Route::get('/gifts/{gift}', [GiftConstructorController::class, 'show'])
        ->whereNumber('gift')->name('gifts.show');
    Route::put('/gifts/{gift}', [GiftConstructorController::class, 'update'])
        ->whereNumber('gift')->name('gifts.update');
    Route::delete('/gifts/{gift}', [GiftConstructorController::class, 'destroy'])
        ->whereNumber('gift')->name('gifts.destroy');

    //Адреса
    Route::group(['prefix' => 'addresses',  'namespace' => '\App\Http\Controllers\User\Address'], function () {
    Route::get('/', action: 'IndexController')->name('address.index');
    Route::post('/store', 'StoreController')->name('address.store');
    });
    
    // Корзина
    Route::group(['prefix' => 'cart',  'namespace' => 'App\Http\Controllers\Cart'], function () {
        Route::get('/', 'IndexController') -> name('cart.index');
        Route::post('/add', 'AddController') -> name('cart.add');
        Route::post('/gifts', [CartGiftController::class, 'store'])->name('cart.gifts.store');
        Route::put('/gifts/{orderGift}', [CartGiftController::class, 'update'])
            ->whereNumber('orderGift')->name('cart.gifts.update');
        Route::delete('/gifts/{orderGift}', [CartGiftController::class, 'destroy'])
            ->whereNumber('orderGift')->name('cart.gifts.destroy');
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
        Route::get(
            '/delivery-slots/{addressId}/{deliveryMethodId}',
            [CheckoutController::class, 'getDeliverySlots']
        )
            ->whereNumber('addressId')
            ->whereNumber('deliveryMethodId')
            ->name('checkout.delivery-slots');
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
        ->middleware('permission:create seller orders')
        ->name('seller.devices.register');

    Route::get('/bootstrap', SellerBootstrapController::class)
        ->middleware(['permission:create seller orders', 'throttle:30,1'])
        ->name('seller.bootstrap');

    Route::get('/orders', [SellerOrderController::class, 'index'])
        ->middleware('permission:view own seller orders')
        ->name('seller.orders.index');
    Route::get('/orders/{order}', [SellerOrderController::class, 'show'])
        ->whereNumber('order')
        ->middleware('permission:view own seller orders')
        ->name('seller.orders.show');
    Route::post('/orders', [SellerOrderController::class, 'store'])
        ->middleware('permission:create seller orders')
        ->name('seller.orders.store');
    Route::put('/orders/{order}', [SellerOrderController::class, 'update'])
        ->whereNumber('order')
        ->middleware('permission:create seller orders')
        ->name('seller.orders.update');
    Route::post('/orders/{order}/cancel', [SellerOrderController::class, 'cancel'])
        ->whereNumber('order')
        ->middleware('permission:create seller orders')
        ->name('seller.orders.cancel');
    Route::post('/orders/complete', [SellerOrderController::class, 'complete'])
        ->middleware('permission:complete own seller orders')
        ->name('seller.orders.complete');
    Route::post('/orders/{order}/escalate', [SellerOrderController::class, 'escalate'])
        ->whereNumber('order')
        ->middleware('permission:complete own seller orders')
        ->name('seller.orders.escalate');

    Route::post('/sync', SellerSyncController::class)
        ->middleware([
            'permission:create seller orders',
            'permission:complete own seller orders',
            'throttle:30,1',
        ])
        ->name('seller.sync');
});

// Сборщик работает только со складскими исполнениями назначенных точек.
Route::prefix('picker')->middleware('auth:sanctum')->group(function () {
    Route::get('/assembled-gifts', [PickerAssembledGiftController::class, 'index'])
        ->middleware('permission:view picking orders')
        ->name('picker.assembled-gifts.index');
    Route::post('/assembled-gifts/{product}/replenish', [PickerAssembledGiftController::class, 'replenish'])
        ->whereNumber('product')
        ->middleware('permission:manage own picking orders')
        ->name('picker.assembled-gifts.replenish');
    Route::get('/incoming-transfers', [PickerOrderController::class, 'incomingTransfers'])
        ->middleware('permission:view picking orders')
        ->name('picker.transfers.index');
    Route::post('/incoming-transfers/{order}/receive', [PickerOrderController::class, 'receiveTransfer'])
        ->whereNumber('order')
        ->middleware('permission:manage own picking orders')
        ->name('picker.transfers.receive');
    Route::get('/orders', [PickerOrderController::class, 'index'])
        ->middleware('permission:view picking orders')
        ->name('picker.orders.index');
    Route::get('/orders/{order}', [PickerOrderController::class, 'show'])
        ->whereNumber('order')
        ->middleware('permission:view picking orders')
        ->name('picker.orders.show');
    Route::post('/orders/{order}/take', [PickerOrderController::class, 'take'])
        ->whereNumber('order')
        ->middleware('permission:manage own picking orders')
        ->name('picker.orders.take');
    Route::post('/orders/{order}/release', [PickerOrderController::class, 'release'])
        ->whereNumber('order')
        ->middleware('permission:manage own picking orders')
        ->name('picker.orders.release');
    Route::post('/orders/{order}/complete', [PickerOrderController::class, 'complete'])
        ->whereNumber('order')
        ->middleware('permission:manage own picking orders')
        ->name('picker.orders.complete');
    Route::post('/orders/{order}/escalate', [PickerOrderController::class, 'escalate'])
        ->whereNumber('order')
        ->middleware('permission:manage own picking orders')
        ->name('picker.orders.escalate');
    Route::post('/orders/{order}/shortage', [PickerOrderController::class, 'reportShortage'])
        ->whereNumber('order')
        ->middleware('permission:report picking shortage')
        ->name('picker.orders.shortage');
});

// Курьер видит свободные и собственные доставки только своих активных точек.
Route::prefix('courier')->middleware('auth:sanctum')->group(function () {
    Route::get('/deliveries', [CourierDeliveryController::class, 'index'])
        ->middleware('permission:view assigned deliveries')
        ->name('courier.deliveries.index');
    Route::get('/deliveries/{order}', [CourierDeliveryController::class, 'show'])
        ->whereNumber('order')
        ->middleware('permission:view assigned deliveries')
        ->name('courier.deliveries.show');
    Route::post('/deliveries/{order}/claim', [CourierDeliveryController::class, 'claim'])
        ->whereNumber('order')
        ->middleware('permission:update assigned deliveries')
        ->name('courier.deliveries.claim');
    Route::post('/deliveries/{order}/release', [CourierDeliveryController::class, 'release'])
        ->whereNumber('order')
        ->middleware('permission:update assigned deliveries')
        ->name('courier.deliveries.release');
    Route::post('/deliveries/{order}/start', [CourierDeliveryController::class, 'start'])
        ->whereNumber('order')
        ->middleware('permission:update assigned deliveries')
        ->name('courier.deliveries.start');
    Route::post('/deliveries/{order}/deliver', [CourierDeliveryController::class, 'deliver'])
        ->whereNumber('order')
        ->middleware('permission:update assigned deliveries')
        ->name('courier.deliveries.deliver');
});

// Отдельный API менеджера. В отличие от Filament, для менеджера здесь
// обязательно применяется ограничение по назначенным активным складам.
Route::prefix('manager')->middleware('auth:sanctum')->group(function () {
    Route::prefix('order-requests')->group(function () {
        Route::get('/', [ManagerOrderRequestController::class, 'index'])
            ->middleware('permission:view manager orders')
            ->name('manager.order-requests.index');
        Route::get('/{orderRequest}', [ManagerOrderRequestController::class, 'show'])
            ->whereNumber('orderRequest')
            ->middleware('permission:view manager orders')
            ->name('manager.order-requests.show');
        Route::post('/{orderRequest}/take', [ManagerOrderRequestController::class, 'take'])
            ->whereNumber('orderRequest')
            ->middleware('permission:manage manager orders')
            ->name('manager.order-requests.take');
        Route::post('/{orderRequest}/release', [ManagerOrderRequestController::class, 'release'])
            ->whereNumber('orderRequest')
            ->middleware('permission:manage manager orders')
            ->name('manager.order-requests.release');
        Route::post('/{orderRequest}/resolve', [ManagerOrderRequestController::class, 'resolve'])
            ->whereNumber('orderRequest')
            ->middleware('permission:manage manager orders')
            ->name('manager.order-requests.resolve');
        Route::post('/{orderRequest}/reject', [ManagerOrderRequestController::class, 'reject'])
            ->whereNumber('orderRequest')
            ->middleware('permission:manage manager orders')
            ->name('manager.order-requests.reject');
    });

    Route::prefix('reviews')->group(function () {
        Route::get('/', [ReviewModerationController::class, 'index'])
            ->middleware('permission:view reviews')
            ->name('manager.reviews.index');
        Route::get('/{review}', [ReviewModerationController::class, 'show'])
            ->whereNumber('review')
            ->middleware('permission:view reviews')
            ->name('manager.reviews.show');
        Route::post('/{review}/hide', [ReviewModerationController::class, 'hide'])
            ->whereNumber('review')
            ->middleware('permission:moderate reviews')
            ->name('manager.reviews.hide');
        Route::post('/{review}/restore', [ReviewModerationController::class, 'restore'])
            ->whereNumber('review')
            ->middleware('permission:moderate reviews')
            ->name('manager.reviews.restore');
        Route::put('/{review}/reply', [ReviewModerationController::class, 'reply'])
            ->whereNumber('review')
            ->middleware('permission:moderate reviews')
            ->name('manager.reviews.reply');
    });

    Route::prefix('order-feedback')->group(function () {
        Route::get('/', [OrderFeedbackModerationController::class, 'index'])
            ->middleware('permission:view reviews')
            ->name('manager.order-feedback.index');
        Route::get('/{feedback}', [OrderFeedbackModerationController::class, 'show'])
            ->whereNumber('feedback')
            ->middleware('permission:view reviews')
            ->name('manager.order-feedback.show');
        Route::post('/{feedback}/hide', [OrderFeedbackModerationController::class, 'hide'])
            ->whereNumber('feedback')
            ->middleware('permission:moderate reviews')
            ->name('manager.order-feedback.hide');
        Route::post('/{feedback}/restore', [OrderFeedbackModerationController::class, 'restore'])
            ->whereNumber('feedback')
            ->middleware('permission:moderate reviews')
            ->name('manager.order-feedback.restore');
    });

    Route::get('/orders', [ManagerOrderController::class, 'index'])
        ->middleware('permission:view manager orders')
        ->name('manager.orders.index');
    Route::get('/orders/{order}', [ManagerOrderController::class, 'show'])
        ->whereNumber('order')
        ->middleware('permission:view manager orders')
        ->name('manager.orders.show');
    Route::post('/orders/{order}/internal-notes', [ManagerOrderCommandController::class, 'internalNote'])
        ->whereNumber('order')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.internal-notes');
    Route::post('/orders/{order}/confirm', [ManagerOrderCommandController::class, 'confirm'])
        ->whereNumber('order')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.confirm');
    Route::post('/orders/{order}/cancel', [ManagerOrderCommandController::class, 'cancel'])
        ->whereNumber('order')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.cancel');
    Route::post('/orders/{order}/reschedule', [ManagerOrderCommandController::class, 'reschedule'])
        ->whereNumber('order')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.reschedule');
    Route::post('/orders/{order}/items', [ManagerOrderItemController::class, 'store'])
        ->whereNumber('order')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.items.store');
    Route::patch('/orders/{order}/items/{item}', [ManagerOrderItemController::class, 'changeQuantity'])
        ->whereNumber('order')
        ->whereNumber('item')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.items.quantity');
    Route::post('/orders/{order}/items/{item}/replace', [ManagerOrderItemController::class, 'replace'])
        ->whereNumber('order')
        ->whereNumber('item')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.items.replace');
    Route::post('/orders/{order}/items/{item}/remove', [ManagerOrderItemController::class, 'remove'])
        ->whereNumber('order')
        ->whereNumber('item')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.items.remove');
    Route::post('/orders/{order}/gifts/{gift}/remove', [ManagerOrderItemController::class, 'removeGift'])
        ->whereNumber('order')
        ->whereNumber('gift')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.gifts.remove');
    Route::post('/orders/{order}/gifts/{gift}/replace', [ManagerOrderItemController::class, 'replaceGift'])
        ->whereNumber('order')
        ->whereNumber('gift')
        ->middleware('permission:manage manager orders')
        ->name('manager.orders.gifts.replace');
    Route::post('/orders/{order}/return-to-stock', [ManagerPackedOrderController::class, 'returnToStock'])
        ->whereNumber('order')
        ->middleware('permission:manage orders')
        ->name('manager.orders.return-to-stock');
    Route::post('/deliveries/{order}/assign-courier', [ManagerDeliveryAssignmentController::class, 'assign'])
        ->whereNumber('order')
        ->middleware('permission:assign couriers')
        ->name('manager.deliveries.assign-courier');

    Route::prefix('fulfillment-issues')->group(function () {
        Route::get('/', [ManagerFulfillmentIssueController::class, 'index'])
            ->middleware('permission:view fulfillment issues')
            ->name('manager.fulfillment-issues.index');
        Route::get('/{issue}', [ManagerFulfillmentIssueController::class, 'show'])
            ->whereNumber('issue')
            ->middleware('permission:view fulfillment issues')
            ->name('manager.fulfillment-issues.show');
        Route::get('/{issue}/affected-orders', [ManagerFulfillmentIssueController::class, 'affectedOrders'])
            ->whereNumber('issue')
            ->middleware('permission:view fulfillment issues')
            ->name('manager.fulfillment-issues.affected-orders');

        Route::post('/{issue}/take', [ManagerFulfillmentIssueController::class, 'take'])
            ->whereNumber('issue')
            ->middleware('permission:manage fulfillment issues')
            ->name('manager.fulfillment-issues.take');
        Route::post('/{issue}/release', [ManagerFulfillmentIssueController::class, 'release'])
            ->whereNumber('issue')
            ->middleware('permission:manage fulfillment issues')
            ->name('manager.fulfillment-issues.release');
        Route::post('/{issue}/close', [ManagerFulfillmentIssueController::class, 'close'])
            ->whereNumber('issue')
            ->middleware('permission:manage fulfillment issues')
            ->name('manager.fulfillment-issues.close');
    });
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

// Глобальное управление заказами остаётся только у администратора.
// Менеджер работает через /manager/orders с ограничением по активным точкам.
Route::prefix('admin')->middleware([
    'auth:sanctum',
    'role:admin',
    'permission:manage orders',
])->group(function () {
    
    // Управление заказами
    Route::prefix('orders')->group(function () {
        // Основные эндпоинты
        Route::get('/', [AdminOrderController::class, 'index'])
            ->name('management.orders.index');
        Route::get('/stats', [AdminOrderController::class, 'stats'])
            ->name('management.orders.stats');
        Route::get('/{order}', [AdminOrderController::class, 'show'])
            ->name('management.orders.show');
        Route::put('/{order}/status', [AdminOrderController::class, 'updateStatus'])
            ->name('management.orders.update-status');
        Route::put('/{order}/tracking', [AdminOrderController::class, 'updateTracking'])
            ->name('management.orders.update-tracking');
        Route::put('/{order}/internal-notes', [AdminOrderController::class, 'updateInternalNotes'])
            ->name('management.orders.update-internal-notes');
        
        // Действия с заказами
        Route::prefix('{order}')->group(function () {
            Route::put('/cancel', [AdminOrderActionController::class, 'cancel'])
                ->name('management.orders.cancel');
            Route::put('/confirm', [AdminOrderActionController::class, 'confirm'])
                ->name('management.orders.confirm');
            Route::put('/ship', [AdminOrderActionController::class, 'markAsShipped'])
                ->name('management.orders.ship');
            Route::put('/deliver', [AdminOrderActionController::class, 'markAsDelivered'])
                ->name('management.orders.deliver');
            Route::put('/delivery-method', [AdminOrderActionController::class, 'updateDeliveryMethod'])
                ->name('management.orders.update-delivery-method');
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
