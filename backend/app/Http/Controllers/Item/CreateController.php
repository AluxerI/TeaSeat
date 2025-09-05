<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Resources\Item\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class CreateController extends Controller
{
    public function __invoke()
    {
        // Возвращаем форму создания или данные для формы
        return response()->json([
            'message' => 'Form data for creating product',
            'fields' => [
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
