<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainPageController;
use App\Http\Controllers\ContactPageController;
use App\Http\Controllers\AboutInfoPageController;
use App\Http\Controllers\DeliveryPageController;
use App\Http\Controllers\CatalogPageController;

Route::group(['namespace' => 'App\Http\Controllers\Item'], function() {
    Route::get('/item/{productId}', 'ShowController')->name('item.show');
    Route::get('/catalog', 'CatalogController')->name('item.catalog');
});


Route::get('/', [MainPageController::class, 'index']);

require __DIR__.'/auth.php';

