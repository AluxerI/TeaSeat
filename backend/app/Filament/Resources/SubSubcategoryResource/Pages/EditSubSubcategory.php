<?php

namespace App\Filament\Resources\SubSubcategoryResource\Pages;

use App\Filament\Resources\SubSubcategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubSubcategory extends EditRecord
{
    protected static string $resource = SubSubcategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
