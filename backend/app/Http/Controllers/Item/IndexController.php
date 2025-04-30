<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Models\Products;
use App\Http\Resources\ItemResource;

class indexController extends Controller
{
    public function __invoke($productId)
    {
        $product = Products::with(['nameProduct','VolumeWarehouse'])->find($productId);
        // dd($product);
        if (!$product) {
            return response()->json(['error' => 'Товар не найден'], 404);
        }
        return new ItemResource($product);
    }
}
