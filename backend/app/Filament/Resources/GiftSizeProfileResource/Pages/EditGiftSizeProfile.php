<?php

namespace App\Filament\Resources\GiftSizeProfileResource\Pages;

use App\Filament\Resources\GiftSizeProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGiftSizeProfile extends EditRecord
{
    protected static string $resource = GiftSizeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
