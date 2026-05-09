<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Подготавливаем данные перед сохранением
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Убираем временные поля, которые не нужны в модели
        unset($data['temp_images']);
        
        return $data;
    }

    /**
     * Действия после сохранения (обработка изображений)
     */
    protected function afterSave(): void
    {
        $product = $this->record;
        
        // Обрабатываем изображения, которые были загружены в repeater
        // Они уже сохранились автоматически через relationship,
        // но нужно убедиться, что они в правильной папке
        
        foreach ($product->images as $image) {
            $currentPath = $image->path;
            
            // Проверяем, находится ли изображение в правильной папке
            $expectedPath = "products/{$product->id}/" . basename($currentPath);
            
            if ($currentPath !== $expectedPath && Storage::disk('public')->exists($currentPath)) {
                // Создаем папку для товара, если её нет
                if (!Storage::disk('public')->exists("products/{$product->id}")) {
                    Storage::disk('public')->makeDirectory("products/{$product->id}");
                }
                
                // Перемещаем файл в правильную папку
                Storage::disk('public')->move($currentPath, $expectedPath);
                
                // Обновляем путь в базе данных
                $image->update(['path' => $expectedPath]);
            }
        }
    }

    /**
     * Действия после удаления (очистка папки с изображениями)
     */
    protected function afterDelete(): void
    {
        $product = $this->record;
        
        // Удаляем папку товара со всеми изображениями
        Storage::disk('public')->deleteDirectory("products/{$product->id}");
    }

    /**
     * Получаем данные для заполнения формы
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Если нужно добавить какие-то дополнительные данные
        return $data;
    }

    /**
     * Сообщение об успешном сохранении
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Товар успешно обновлен';
    }

    /**
     * Редирект после сохранения
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}