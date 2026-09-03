<?php

namespace App\Filament\Resources\ConstructorPackagingTemplateResource\Pages;

use App\Filament\Resources\ConstructorPackagingTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConstructorPackagingTemplates extends ListRecords
{
    protected static string $resource = ConstructorPackagingTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
