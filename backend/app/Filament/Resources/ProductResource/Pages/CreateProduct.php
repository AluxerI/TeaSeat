<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        // Обработка временных изображений
        $tempImages = session()->get('temp_product_images', []);
        
        foreach ($tempImages as $index => $tempPath) {
            if (Storage::disk('public')->exists($tempPath)) {
                $newPath = 'products/' . $this->record->id . '/' . basename($tempPath);
                Storage::disk('public')->move($tempPath, $newPath);
                
                $this->record->images()->create([
                    'path' => $newPath,
                    'disk' => 'public',
                    'sort_order' => $index,
                    'is_main' => $index === 0, // Первое изображение - главное
                    'is_background' => false,
                ]);
            }
        }
        
        session()->forget('temp_product_images');
    }
}