<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use App\Models\Inventory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventory extends EditRecord
{
    protected static string $resource = InventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    // Исправляем сигнатуру метода
    protected function resolveRecord(int | string $key): \Illuminate\Database\Eloquent\Model
    {
        $parts = explode('-', (string) $key);
        if (count($parts) === 2) {
            return Inventory::where('product_id', $parts[0])
                ->where('warehouse_id', $parts[1])
                ->firstOrFail();
        }
        
        return parent::resolveRecord($key);
    }
}