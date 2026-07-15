<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderManagementService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
    private ?string $requestedStatus = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->disabled(fn (): bool =>
                    $this->record->stock_reserved_at !== null
                    || $this->record->sales_channel === Order::SALES_CHANNEL_SELLER),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->sales_channel === Order::SALES_CHANNEL_SELLER) {
            // До появления отдельного manager workflow в Filament разрешаем
            // сохранить только внутреннюю заметку. Остальные поля PWA-заказа
            // являются складским документом и меняются через SellerOrderService.
            $this->requestedStatus = $this->record->status;

            return [
                'internal_notes' => $data['internal_notes']
                    ?? $this->record->internal_notes,
            ];
        }

        $this->requestedStatus = $data['status'] ?? $this->record->status;
        // Статус применит общий сервис после сохранения позиций заказа.
        $data['status'] = $this->record->status;

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->requestedStatus === null || $this->requestedStatus === $this->record->status) {
            return;
        }

        $this->record = app(OrderManagementService::class)->updateOrderStatus(
            $this->record,
            $this->requestedStatus,
            'Изменено через админ-панель',
            Auth::id()
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Заказ успешно обновлен';
    }
}
