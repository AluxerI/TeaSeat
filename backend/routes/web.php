<?php

use App\Http\Controllers\MainPageController;
use App\Http\Controllers\ContactPageController;
use App\Http\Controllers\AboutInfoPageController;
use App\Http\Controllers\DeliveryPageController;
use App\Http\Controllers\CatalogPageController;
use App\Http\Controllers\ProductController;

use Illuminate\Support\Facades\Route;


Route::group(['namespace' => 'App\Http\Controllers\Item'], function() {
    Route::get('/item/{productId}', 'ShowController')->name('item.show');
    Route::get('/items', 'IndexController')->name('item.index');
});

Route::get('/', [MainPageController::class, 'index']);
Route::get('/contact', [ContactPageController::class, 'index']);
Route::get('/AboutInfo', [AboutInfoPageController::class, 'index']);
Route::get('/Delivery', [DeliveryPageController::class, 'index']);
Route::get('/Catalog', [CatalogPageController::class, 'index']);
Route::get('/test', [ProductController::class, 'index']);
