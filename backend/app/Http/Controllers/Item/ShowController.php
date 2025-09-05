<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Resources\Item\ItemResource;
use App\Services\PriceCalculatorService;

class ShowController extends Controller
{
    public function __invoke($productId)
    {
        // Используем eager loading для загрузки связанных данных
        $product = Product::with([
            'sub_subcategories.subcategory.category', // Цепочка категорий
            'brand', // Бренд товара
            'inventories.warehouse', // Склады и количество
            'promotions', // Акции на товар
            'discounts' // Скидки на товар
        ])->find($productId);

        // Если товар не найден, возвращаем ошибку 404
        if (!$product) {
            return response()->json(['error' => 'Товар не найден'], 404);
        }

        // Возвращаем данные товара через ресурс
        return new ItemResource($product);
    }
}
