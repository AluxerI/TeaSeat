<?php

namespace App\Filament\Resources\DeliveryTimeSlotResource\Pages;

use App\Filament\Resources\DeliveryTimeSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryTimeSlot extends EditRecord
{
    protected static string $resource = DeliveryTimeSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => !$this->record->orders()->exists()),
        ];
    }
}
