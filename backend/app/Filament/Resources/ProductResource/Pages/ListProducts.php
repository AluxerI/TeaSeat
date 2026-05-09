<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('import')
                ->label('Импорт из Excel')
                ->icon('heroicon-o-arrow-up-on-square')
                ->url(ProductResource::getUrl('import')),
            Actions\Action::make('import-images')
                ->label('Импорт изображений')
                ->icon('heroicon-o-photo')
                ->url(ProductResource::getUrl('import-images')),
        ];
    }
}
