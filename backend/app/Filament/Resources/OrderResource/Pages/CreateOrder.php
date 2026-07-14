<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderManagementService;
use App\Services\WarehouseService;
use Filament\Resources\Pages\CreateRecord;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Auth;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
    private string $requestedStatus = Order::STATUS_PENDING;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->requestedStatus = $data['status'] ?? Order::STATUS_PENDING;
        $data['status'] = Order::STATUS_PENDING;

        return $data;
    }

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

        if (
            $record->sales_channel === Order::SALES_CHANNEL_ONLINE
            && !$record->is_supplier_order
        ) {
            $record->loadMissing(['items.product', 'shippingAddress']);
            $warehouseService = app(WarehouseService::class);
            $allocation = $warehouseService->determineWarehousesForOrder(
                $record,
                $record->shippingAddress
            );
            $partialOrders = $warehouseService->createPartialOrders(
                $record,
                $allocation,
                $record->shippingAddress
            );
            $warehouseService->reserveOnlineStockForOrders(
                $partialOrders,
                Auth::id()
            );
            $record->update(['stock_reserved_at' => now()]);
        }
        
        OrderStatusHistory::create([
            'order_id' => $record->id,
            'from_status' => $record->status,
            'to_status' => $record->status,
            'changed_by' => Auth::id(),
            'notes' => 'Заказ создан через админ-панель'
        ]);

        if ($this->requestedStatus !== Order::STATUS_PENDING) {
            $this->record = app(OrderManagementService::class)->updateOrderStatus(
                $record,
                $this->requestedStatus,
                'Начальный статус выбран при создании через админ-панель',
                Auth::id()
            );
        }
    }
}
