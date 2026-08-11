<?php

namespace App\Services;

use App\Models\Order;
use DomainException;
use Illuminate\Support\Collection;

class CartSelectionService
{
    /**
     * @return array{order: Order, explicit: bool, item_ids: array<int, int>, gift_ids: array<int, int>, hash: string}
     */
    public function resolve(
        Order $cart,
        ?array $cartItemIds = null,
        ?array $cartGiftIds = null
    ): array {
        $cart->loadMissing(['items.product', 'gifts.items.product']);

        $explicit = $cartItemIds !== null || $cartGiftIds !== null;
        if (!$explicit) {
            return [
                'order' => $cart,
                'explicit' => false,
                'item_ids' => $cart->items
                    ->whereNull('order_gift_id')
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all(),
                'gift_ids' => $cart->gifts
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all(),
                'hash' => $this->hash(null, null),
            ];
        }

        $itemIds = $this->normalizeIds($cartItemIds ?? []);
        $giftIds = $this->normalizeIds($cartGiftIds ?? []);
        if ($itemIds === [] && $giftIds === []) {
            throw new DomainException('Выберите хотя бы один товар или подарочный набор');
        }

        $standaloneItems = $cart->items->whereNull('order_gift_id');
        $this->assertOwnedIds($itemIds, $standaloneItems->pluck('id'), 'товар');
        $this->assertOwnedIds($giftIds, $cart->gifts->pluck('id'), 'подарочный набор');

        $selectedGifts = $cart->gifts
            ->whereIn('id', $giftIds)
            ->values();
        $selectedItems = $standaloneItems
            ->whereIn('id', $itemIds)
            ->concat($cart->items->whereIn('order_gift_id', $giftIds))
            ->values();

        $selection = clone $cart;
        $selection->setRelation('items', $selectedItems);
        $selection->setRelation('gifts', $selectedGifts);

        return [
            'order' => $selection,
            'explicit' => true,
            'item_ids' => $itemIds,
            'gift_ids' => $giftIds,
            'hash' => $this->hash($itemIds, $giftIds),
        ];
    }

    public function hash(?array $cartItemIds, ?array $cartGiftIds): string
    {
        $explicit = $cartItemIds !== null || $cartGiftIds !== null;
        $payload = $explicit
            ? [
                'mode' => 'selected',
                'cart_item_ids' => $this->normalizeIds($cartItemIds ?? []),
                'cart_gift_ids' => $this->normalizeIds($cartGiftIds ?? []),
            ]
            : ['mode' => 'all'];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<int, int> */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function assertOwnedIds(array $requested, Collection $available, string $label): void
    {
        $availableIds = $available->map(fn ($id): int => (int) $id)->all();
        if (array_diff($requested, $availableIds) !== []) {
            throw new DomainException("Выбранный {$label} отсутствует в активной корзине");
        }
    }
}
