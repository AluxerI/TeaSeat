<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainPageController;

Route::group(['namespace' => 'App\Http\Controllers\Item'], function() {
    Route::get('/item/{productId}', 'ShowController')->name('item.show');
    Route::get('/catalog', 'IndexController')->name('item.index');
});


Route::get('/', [MainPageController::class, 'index']);

require __DIR__.'/auth.php';

