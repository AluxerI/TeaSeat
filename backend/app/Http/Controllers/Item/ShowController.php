<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Resources\Item\ItemResource;

class ShowController extends Controller
{
    public function __invoke($productId)
    {
        // Загружаем необходимые связи для кеша
        $product = Product::with([
            'sub_subcategories.subcategory.category',
            'brand',
            'inventories.warehouse',
            'discounts'
        ])->individualSale()->find($productId);

        if (!$product) {
            return response()->json(['error' => 'Товар не найден'], 404);
        }

        // Используем ItemResource который берет данные из getDetailedData()
        return new ItemResource($product);
    }
}
