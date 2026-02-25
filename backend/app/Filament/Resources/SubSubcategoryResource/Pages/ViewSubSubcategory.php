<?php

namespace App\Filament\Resources\SubSubcategoryResource\Pages;

use App\Filament\Resources\SubSubcategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSubSubcategory extends ViewRecord
{
    protected static string $resource = SubSubcategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }
}