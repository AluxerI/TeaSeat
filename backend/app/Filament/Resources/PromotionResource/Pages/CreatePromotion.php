<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Filament\Resources\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        $data = $this->data;

        // Сохраняем связи с категориями
        if (isset($data['categories']) && !empty($data['categories'])) {
            $record->categories()->sync($data['categories']);
        }

        // Сохраняем связи с подкатегориями
        if (isset($data['subcategories']) && !empty($data['subcategories'])) {
            $record->subcategories()->sync($data['subcategories']);
        }

        // Сохраняем связи с под-подкатегориями
        if (isset($data['sub_subcategories']) && !empty($data['sub_subcategories'])) {
            $record->subSubcategories()->sync($data['sub_subcategories']);
        }

        // Сохраняем связи с товарами
        if (isset($data['products']) && !empty($data['products'])) {
            $record->products()->sync($data['products']);
        }
    }
}