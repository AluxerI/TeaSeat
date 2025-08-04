<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Resources\Item\ProductsResource;
use App\Models\Product;
use App\Models\Warehouse;

class IndexController extends Controller
{
    public function __invoke()
    {
        $products = Product::with([
            'sub_subcategories.subcategory.category',
            'brand',
            'inventories.warehouse'
        ])->paginate(10);
        return ProductsResource::collection($products);
    }
}
