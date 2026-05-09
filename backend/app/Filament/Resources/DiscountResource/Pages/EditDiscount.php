<?php

namespace App\Filament\Resources\DiscountResource\Pages;

use App\Filament\Resources\DiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDiscount extends EditRecord
{
    protected static string $resource = DiscountResource::class;

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

        if (isset($data['categories'])) {
            $record->categories()->sync($data['categories']);
        }

        if (isset($data['subcategories'])) {
            $record->subcategories()->sync($data['subcategories']);
        }

        if (isset($data['sub_subcategories'])) {
            $record->subSubcategories()->sync($data['sub_subcategories']);
        }

        if (isset($data['products'])) {
            $record->products()->sync($data['products']);
        }

        if (isset($data['users'])) {
            $record->users()->sync($data['users']);
        }
    }
}