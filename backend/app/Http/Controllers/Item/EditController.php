<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Resources\Item\ProductResource;

class EditController extends Controller
{
    public function __invoke(Product $product)
    {
        $this->authorize('edit products');

        return response()->json([
            'message' => 'Product data for editing',
            'data' => new ProductResource($product->load(['brand', 'sub_subcategories'])),
            'form_fields' => [
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'weight_grams' => 'required|integer|min:0',
                'ingredients' => 'nullable|string',
                'brand_id' => 'nullable|exists:brands,id',
                'sub_subcategory_ids' => 'array|exists:sub_subcategories,id'
            ]
        ]);
    }
}
