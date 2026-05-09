<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $category = $this->record;
        
        // Проверяем и перемещаем главное изображение
        if ($category->main_image_path && !str_contains($category->main_image_path, "categories/{$category->id}/")) {
            $this->moveImageToCategoryFolder($category, $category->main_image_path, 'main_image_path');
        }
        
        // Проверяем и перемещаем фоновое изображение
        if ($category->background_image_path && !str_contains($category->background_image_path, "categories/{$category->id}/")) {
            $this->moveImageToCategoryFolder($category, $category->background_image_path, 'background_image_path');
        }
    }

    protected function moveImageToCategoryFolder($category, $currentPath, $field): void
    {
        if (!Storage::disk('public')->exists($currentPath)) {
            return;
        }

        $newFilename = time() . '_' . uniqid() . '.' . pathinfo($currentPath, PATHINFO_EXTENSION);
        $newPath = "categories/{$category->id}/images/{$newFilename}";
        
        // Создаем папку, если её нет
        Storage::disk('public')->makeDirectory("categories/{$category->id}/images");
        
        // Перемещаем файл
        Storage::disk('public')->move($currentPath, $newPath);
        
        // Обновляем путь в базе
        $category->update([$field => $newPath]);
    }

    protected function afterDelete(): void
    {
        // Удаляем папку категории со всеми изображениями
        Storage::disk('public')->deleteDirectory("categories/{$this->record->id}");
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Категория успешно обновлена';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}