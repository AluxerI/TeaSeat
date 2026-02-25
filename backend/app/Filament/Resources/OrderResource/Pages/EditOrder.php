<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\OrderStatusHistory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $oldStatus = $record->getOriginal('status');
        $newStatus = $record->status;

        if ($oldStatus !== $newStatus) {
            OrderStatusHistory::create([
                'order_id' => $record->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'changed_by' => Auth::id(),
                'notes' => 'Изменено через админ-панель'
            ]);
        }
    }
}