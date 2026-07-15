<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Collection;

class GiftAvailabilityService
{
    /** @param array<int, int> $requiredByProduct */
    public function assertAvailable(array $requiredByProduct, ?string $city = null): void
    {
        foreach ($requiredByProduct as $productId => $required) {
            $query = Inventory::query()
                ->where('product_id', $productId)
                ->onlineFulfillment();

            if ($city !== null) {
                $warehouseIds = Warehouse::query()
                    ->where('city', $city)
                    ->onlineFulfillment()
                    ->pluck('id');
                $query->whereIn('warehouse_id', $warehouseIds);
            }

            $available = Inventory::sumOnlineAvailable($query);
            if ($available < $required) {
                throw new DomainException(sprintf(
                    'Недостаточно товара #%d. Требуется: %d, доступно: %d',
                    $productId,
                    $required,
                    $available
                ));
            }
        }
    }

    /**
     * Проверяет корзину целиком: обычные строки и компоненты подарков суммируются.
     */
    public function assertCartAvailable(Order $cart, ?string $city = null): void
    {
        $cart->loadMissing('items');
        $required = $cart->items
            ->groupBy('product_id')
            ->map(fn (Collection $items): int => (int) $items->sum('quantity'))
            ->all();
        $this->assertAvailable($required, $city);
    }
}
