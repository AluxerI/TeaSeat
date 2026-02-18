<?php

namespace App\Http\Controllers\Item\Image;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;

class DeleteController extends Controller
{
    public function __invoke($id)
    {
        $image = ProductImage::findOrFail($id);
        
        // Удаляем файл
        Storage::disk($image->disk)->delete($image->path);
        
        // Удаляем запись из БД
        $image->delete();

        return response()->json(['message' => 'Изображение удалено']);
    }

}
