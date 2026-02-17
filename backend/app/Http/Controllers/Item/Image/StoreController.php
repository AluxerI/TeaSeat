<?php

namespace App\Http\Controllers\Item\Image;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    public function __invoke(Request $request)
    {
        // Валидация
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($request->product_id);
        $uploadedImages = [];

        // Определяем, есть ли уже изображения у товара
        $currentMaxSort = $product->images()->max('sort_order') ?? 0;

        foreach ($request->file('images') as $index => $image) {
            // Генерируем уникальное имя файла
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            
            // Сохраняем файл
            $path = $image->storeAs(
                'products/' . $product->id, 
                $filename, 
                'public' // диск
            );

            // Создаем запись в БД
            $productImage = ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'disk' => 'public',
                'sort_order' => $currentMaxSort + $index + 1,
                'is_main' => $product->images()->count() === 0 && $index === 0, // первое фото главное
                'alt' => $product->name, // можно задать позже
            ]);

            $uploadedImages[] = $productImage;
        }

        return response()->json([
            'message' => 'Изображения успешно загружены',
            'images' => $uploadedImages,
        ], 201);
    }
}
