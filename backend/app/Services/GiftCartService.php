<?php

namespace App\Services;

use App\Models\Gift;
use App\Models\Order;
use App\Models\OrderGift;
use App\Models\ProductSize;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class GiftCartService
{
    public function __construct(
        protected CartService $cartService,
        protected GiftAvailabilityService $availabilityService
    ) {
    }

    public function add(
        User $user,
        int $giftId,
        int $giftVersion,
        int $quantity,
        string $clientInstanceId,
        ?string $city = null
    ): Order {
        return DB::transaction(function () use (
            $user,
            $giftId,
            $giftVersion,
            $quantity,
            $clientInstanceId,
            $city
        ): Order {
            $cart = $this->cartService->getCart($user->id);
            $cart = Order::query()->lockForUpdate()->findOrFail($cart->id);
            $existing = $cart->gifts()
                ->where('client_instance_id', $clientInstanceId)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ((int) $existing->gift_id !== $giftId
                    || (int) $existing->gift_version !== $giftVersion
                    || (int) $existing->quantity !== $quantity) {
                    throw new DomainException('client_instance_id уже использован для другого подарка');
                }
                return $cart->fresh(['items.product', 'gifts.items.product']);
            }

            $gift = Gift::query()
                ->where('user_id', $user->id)
                ->where('status', Gift::STATUS_ACTIVE)
                ->with([
                    'items.productSize.sizeProfile',
                    'items.productSize.product.sub_subcategories.subcategory.category',
                ])
                ->findOrFail($giftId);
            if ((int) $gift->version !== $giftVersion) {
                throw new DomainException('Подарок был изменён. Обновите его перед добавлением в корзину');
            }

            $orderGift = $cart->gifts()->create([
                'gift_id' => $gift->id,
                'client_instance_id' => $clientInstanceId,
                'gift_version' => $gift->version,
                'name' => $gift->name,
                'description' => $gift->description,
                'quantity' => $quantity,
                'markup_unit_amount' => $gift->markup_amount,
                'markup_total_amount' => round((float) $gift->markup_amount * $quantity, 2),
                'layout_snapshot' => $gift->layout_snapshot,
            ]);

            foreach ($gift->items as $giftItem) {
                /** @var ProductSize $size */
                $size = $giftItem->productSize;
                $size->assertConstructorReady();
                $componentQuantity = (int) $size->product_quantity * $quantity;
                $cart->items()->create([
                    'product_id' => $size->product_id,
                    'order_gift_id' => $orderGift->id,
                    'product_size_id' => $size->id,
                    'gift_item_client_id' => $giftItem->client_item_id,
                    'gift_item_quantity' => $size->product_quantity,
                    'gift_item_sort_order' => $giftItem->sort_order,
                    'quantity' => $componentQuantity,
                    ...$size->product->measurementSnapshot(),
                    'unit_price' => $size->product->price,
                    'promotion_discount_percent' => 0,
                    'personal_discount_percent' => 0,
                    'final_unit_price' => $size->product->price,
                    'total_price' => $size->product->baseTotalForQuantity($componentQuantity),
                ]);
            }

            $this->availabilityService->assertCartAvailable($cart->fresh('items'), $city);
            return $this->cartService->refreshPricing($cart);
        });
    }

    public function updateQuantity(
        User $user,
        int $orderGiftId,
        int $quantity,
        ?string $city = null
    ): Order {
        return DB::transaction(function () use ($user, $orderGiftId, $quantity, $city): Order {
            $cart = $this->cartService->getCart($user->id);
            $cart = Order::query()->lockForUpdate()->findOrFail($cart->id);
            /** @var OrderGift $gift */
            $gift = $cart->gifts()->with('items')->lockForUpdate()->findOrFail($orderGiftId);
            $gift->update([
                'quantity' => $quantity,
                'markup_total_amount' => round((float) $gift->markup_unit_amount * $quantity, 2),
            ]);
            foreach ($gift->items as $item) {
                $item->update([
                    'quantity' => (int) $item->gift_item_quantity * $quantity,
                ]);
            }
            $this->availabilityService->assertCartAvailable($cart->fresh('items'), $city);
            return $this->cartService->refreshPricing($cart);
        });
    }

    public function remove(User $user, int $orderGiftId): Order
    {
        return DB::transaction(function () use ($user, $orderGiftId): Order {
            $cart = $this->cartService->getCart($user->id);
            $cart = Order::query()->lockForUpdate()->findOrFail($cart->id);
            $cart->gifts()->lockForUpdate()->findOrFail($orderGiftId)->delete();
            return $this->cartService->refreshPricing($cart);
        });
    }
}
