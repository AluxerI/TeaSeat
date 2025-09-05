<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Resources\Item\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function __invoke(StoreItemRequest $request): JsonResponse
    {
        $this->authorize('create products');

        try {
            DB::beginTransaction();

            $product = Product::create($request->validated());

            // Прикрепляем под-подкатегории если есть
            if ($request->has('sub_subcategory_ids')) {
                $product->sub_subcategories()->attach($request->sub_subcategory_ids);
            }

            DB::commit();

            return response()->json([
                'message' => 'Товар успешно создан',
                'data' => new ProductResource($product->load(['brand', 'sub_subcategories']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Ошибка при создании товара',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}