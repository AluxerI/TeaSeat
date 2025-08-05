<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Resources\Item\ItemResource;
use App\http\Resources\Item\ProductsResource;

class ShowController extends Controller
{
    public function __invoke($productId)
    {
        $product = Product::with([
            'sub_subcategories.subcategory.category',
            'brand',
            'inventories.warehouse'
        ])->find($productId);
        if (!$product) {
            return response()->json(['error' => 'Товар не найден'], 404);
        }
        return new ProductsResource($product);
    }
}
