<?php

namespace App\Filament\Resources\DeliveryTimeSlotResource\Pages;

use App\Filament\Resources\DeliveryTimeSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryTimeSlots extends ListRecords
{
    protected static string $resource = DeliveryTimeSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
