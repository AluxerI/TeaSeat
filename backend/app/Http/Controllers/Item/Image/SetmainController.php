<?php

namespace App\Http\Controllers\Item\Image;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetmainController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $image = ProductImage::findOrFail($id);
        
        // Проверяем права (если нужно)
        $this->authorize('edit products');
        
        DB::transaction(function () use ($image) {
            // Сбрасываем флаг is_main у всех изображений этого товара
            $image->product->images()->update(['is_main' => false]);
            
            // Устанавливаем главным текущее изображение
            $image->update(['is_main' => true]);
            
            // Очищаем кеш товара
            $image->product->clearCache();
        });

        return response()->json([
            'success' => true,
            'message' => 'Главное изображение обновлено',
            'data' => [
                'image_id' => $image->id,
                'is_main' => true,
                'image_url' => $image->image_url,
            ]
        ]);
    }
}