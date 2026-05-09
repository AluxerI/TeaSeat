<?php

namespace App\Filament\Resources\SubSubcategoryResource\Pages;

use App\Filament\Resources\SubSubcategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubSubcategories extends ListRecords
{
    protected static string $resource = SubSubcategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
