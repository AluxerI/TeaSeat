<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Http\Resources\Item\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UpdateController extends Controller
{
    public function __invoke(UpdateItemRequest $request, Product $product): JsonResponse
    {
        $this->authorize('edit products');

        try {
            DB::beginTransaction();

            $product->update($request->validated());

            // Синхронизируем под-подкатегории если есть
            if ($request->has('sub_subcategory_ids')) {
                $product->sub_subcategories()->sync($request->sub_subcategory_ids);
            }

            DB::commit();

            return response()->json([
                'message' => 'Товар успешно обновлен',
                'data' => new ProductResource($product->fresh(['brand', 'sub_subcategories']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Ошибка при обновлении товара',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}