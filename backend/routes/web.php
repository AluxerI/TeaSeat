<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'App\Http\Controllers\Item'], function() {
    Route::get('/item/{productId}', 'ShowController')->name('item.show');
    Route::get('/catalog', 'IndexController')->name('item.index');
    Route::get('/cities', 'LocationController@getCities');
    Route::get('/cities/{city}/items', 'LocationController@getProductsInCity');
    Route::get('/cities/{city}/items/{productId}/availability', 'LocationController@getProductAvailabilityDetails');
});