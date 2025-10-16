<?php

namespace App\Services;

use App\Models\Order;
use App\Models\AddressClient;

class WarehouseService
{
    public function determineWarehouseForOrder(Order $order, AddressClient $shippingAddress): int
    {
        // Логика выбора ближайшего склада к адресу доставки
        // Можно использовать геолокацию или выбрать склад в том же городе
        
        $warehouse = \App\Models\Warehouse::nearestTo($shippingAddress->city)
            ->whereHas('inventories', function($query) use ($order) {
                $query->whereIn('product_id', $order->items->pluck('product_id'));
            })
            ->first();

        if (!$warehouse) {
            throw new \Exception('Не найден подходящий склад для заказа');
        }

        return $warehouse->id;
    }
}