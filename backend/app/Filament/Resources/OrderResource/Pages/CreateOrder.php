<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Auth;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Перенаправляем на индекс вместо view
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Показываем уведомление об успешном создании
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Заказ успешно создан';
    }

    /**
     * Добавляем запись в историю статусов после создания
     */
    protected function afterCreate(): void
    {
        $record = $this->record;
        
        OrderStatusHistory::create([
            'order_id' => $record->id,
            'from_status' => $record->status,
            'to_status' => $record->status,
            'changed_by' => Auth::id(),
            'notes' => 'Заказ создан через админ-панель'
        ]);
    }
}