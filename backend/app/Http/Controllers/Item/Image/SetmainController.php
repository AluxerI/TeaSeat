<?php

namespace App\Http\Controllers\Item\Image;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductImage;

class SetmainController extends Controller
{
    public function __invoke(Request $request, $id)
    {
    //     $image = ProductImage::findOrFail($id);
        
    //     // Сбрасываем флаг is_main у всех изображений товара
    //     $image->product->images()->update(['is_main' => false]);
        
    //     // Устанавливаем главным текущее
    //     $image->update(['is_main' => true]);

    //     return response()->json(['message' => 'Главное изображение обновлено']);
    }
}
