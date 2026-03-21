<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Filament\Resources\PromotionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $data = $this->data;

        // Обновляем связи с категориями
        if (isset($data['categories'])) {
            $record->categories()->sync($data['categories']);
        }

        // Обновляем связи с подкатегориями
        if (isset($data['subcategories'])) {
            $record->subcategories()->sync($data['subcategories']);
        }

        // Обновляем связи с под-подкатегориями
        if (isset($data['sub_subcategories'])) {
            $record->subSubcategories()->sync($data['sub_subcategories']);
        }

        // Обновляем связи с товарами
        if (isset($data['products'])) {
            $record->products()->sync($data['products']);
        }
    }
}