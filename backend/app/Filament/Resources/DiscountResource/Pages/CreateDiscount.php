<?php

namespace App\Filament\Resources\DiscountResource\Pages;

use App\Filament\Resources\DiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscount extends CreateRecord
{
    protected static string $resource = DiscountResource::class;

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

        // Сохраняем связи с пользователями
        if (isset($data['users']) && !empty($data['users'])) {
            $record->users()->sync($data['users']);
        }
    }
}