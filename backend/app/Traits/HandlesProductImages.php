<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use App\Models\Product;


trait HandlesProductImages
{
    protected function moveTempImagesToProductFolder(Product $product, array $tempPaths): void
    {
        foreach ($tempPaths as $tempPath) {
            if (Storage::disk('public')->exists($tempPath)) {
                $newPath = 'products/' . $product->id . '/' . basename($tempPath);
                Storage::disk('public')->move($tempPath, $newPath);
                
                // Обновляем путь в базе данных
                $product->images()->create([
                    'path' => $newPath,
                    'disk' => 'public',
                    'is_main' => false,
                    'is_background' => false,
                    'sort_order' => 0,
                ]);
            }
        }
    }
}