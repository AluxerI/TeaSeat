<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use App\Services\WarehouseService;
use Filament\Resources\Pages\CreateRecord;

class CreateInventory extends CreateRecord
{
    protected static string $resource = InventoryResource::class;

    protected function afterCreate(): void
    {
        app(WarehouseService::class)->recordOpeningBalance(
            $this->record,
            auth()->id()
        );
    }
}
