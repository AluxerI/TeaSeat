<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\FulfillmentIssue;
use App\Models\Gift;
use App\Models\ManagerOrderAdjustment;
use App\Models\Order;
use App\Models\OrderGift;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagerOrderItemService
{
    public function __construct(
        protected ManagerAccessService $accessService,
        protected ManagerOrderService $orderService,
        protected WarehouseService $warehouseService,
        protected PricingService $pricingService
    ) {
    }

    /** @return array<int, int> */
    public function activeWarehouseIds(User $manager): array
    {
        return $this->orderService->activeWarehouseIds($manager);
    }

    public function changeQuantity(
        User $manager,
        int $orderId,
        int $itemId,
        int $quantity,
        string $operationId,
        string $reason,
        ?int $fulfillmentIssueId = null
    ): array {
        return $this->perform(
            $manager,
            $orderId,
            $operationId,
            $reason,
            $fulfillmentIssueId,
            function (Order $order) use ($itemId, $quantity): array {
                $item = $this->lockStandaloneItem($order, $itemId);
                $item->product->assertValidSaleQuantity($quantity);

                $oldQuantity = (int) $item->quantity;
                if ($oldQuantity === $quantity) {
                    throw new DomainException('Количество товара не изменилось');
                }

                $before = $this->lineSnapshot($item);
                $oldUses = $this->discountUses($item, $oldQuantity);
                $newUses = $this->discountUses($item, $quantity);
                $ratio = $quantity / max(1, $oldQuantity);
                $baseTotal = round(
                    (float) $item->unit_price * $quantity
                    / max(1, (int) $item->price_unit_quantity),
                    2
                );
                $finalTotal = round(
                    (float) $item->final_unit_price * $quantity
                    / max(1, (int) $item->price_unit_quantity),
                    2
                );
                $promotionAmount = round(
                    (float) $item->promotion_discount_amount * $ratio,
                    2
                );
                $selectedAmount = round(
                    (float) $item->selected_discount_amount * $ratio,
                    2
                );
                $snapshot = array_merge($item->pricing_snapshot ?? [], [
                    'quantity' => $quantity,
                    'discount_uses' => $newUses,
                    'base_total' => $baseTotal,
                    'promotion_discount_amount' => $promotionAmount,
                    'selected_discount_amount' => $selectedAmount,
                    'final_total' => $finalTotal,
                    'manager_price_policy' => 'checkout_price_preserved',
                ]);

                $item->update([
                    'quantity' => $quantity,
                    'promotion_discount_amount' => $promotionAmount,
                    'selected_discount_amount' => $selectedAmount,
                    'total_price' => $finalTotal,
                    'pricing_snapshot' => $snapshot,
                ]);

                $this->adjustLineUsage(
                    $order,
                    $item,
                    $newUses - $oldUses,
                    false
                );

                return [
                    'action' => ManagerOrderAdjustment::ACTION_QUANTITY_CHANGED,
                    'order_product_id' => $item->id,
                    'order_gift_id' => null,
                    'before' => $before,
                    'after' => $this->lineSnapshot($item->fresh('product')),
                ];
            }
        );
    }

    public function addProduct(
        User $manager,
        int $orderId,
        int $productId,
        int $quantity,
        string $operationId,
        string $reason,
        ?int $fulfillmentIssueId = null
    ): array {
        return $this->perform(
            $manager,
            $orderId,
            $operationId,
            $reason,
            $fulfillmentIssueId,
            function (Order $order) use ($productId, $quantity): array {
                $product = $this->currentCatalogProduct($productId);
                $this->assertNoStandaloneProduct($order, $product->id);
                $quote = $this->pricingService->quoteProductLine(
                    $product,
                    $quantity,
                    $order->user
                );
                $item = $order->items()->create(
                    $this->currentPriceAttributes($quote)
                );
                $this->consumeCurrentPromotion($order, $quote);

                return [
                    'action' => ManagerOrderAdjustment::ACTION_PRODUCT_ADDED,
                    'order_product_id' => $item->id,
                    'order_gift_id' => null,
                    'before' => null,
                    'after' => $this->lineSnapshot($item->fresh('product')),
                ];
            }
        );
    }

    public function replaceProduct(
        User $manager,
        int $orderId,
        int $itemId,
        int $productId,
        int $quantity,
        string $operationId,
        string $reason,
        ?int $fulfillmentIssueId = null
    ): array {
        return $this->perform(
            $manager,
            $orderId,
            $operationId,
            $reason,
            $fulfillmentIssueId,
            function (Order $order) use ($itemId, $productId, $quantity): array {
                $item = $this->lockStandaloneItem($order, $itemId);
                if ((int) $item->product_id === $productId) {
                    throw new DomainException(
                        'Для этого товара используйте изменение количества'
                    );
                }

                $product = $this->currentCatalogProduct($productId);
                $this->assertNoStandaloneProduct($order, $product->id, $item->id);
                $before = $this->lineSnapshot($item);

                $this->adjustLineUsage(
                    $order,
                    $item,
                    -$this->discountUses($item, (int) $item->quantity),
                    false
                );
                $quote = $this->pricingService->quoteProductLine(
                    $product,
                    $quantity,
                    $order->user
                );
                $item->update($this->currentPriceAttributes($quote));
                $this->consumeCurrentPromotion($order, $quote);

                return [
                    'action' => ManagerOrderAdjustment::ACTION_PRODUCT_REPLACED,
                    'order_product_id' => $item->id,
                    'order_gift_id' => null,
                    'before' => $before,
                    'after' => $this->lineSnapshot($item->fresh('product')),
                ];
            }
        );
    }

    public function removeProduct(
        User $manager,
        int $orderId,
        int $itemId,
        string $operationId,
        string $reason,
        ?int $fulfillmentIssueId = null
    ): array {
        return $this->perform(
            $manager,
            $orderId,
            $operationId,
            $reason,
            $fulfillmentIssueId,
            function (Order $order) use ($itemId): array {
                $item = $this->lockStandaloneItem($order, $itemId);
                $before = $this->lineSnapshot($item);
                $this->adjustLineUsage(
                    $order,
                    $item,
                    -$this->discountUses($item, (int) $item->quantity),
                    false
                );
                $itemId = (int) $item->id;
                $item->delete();

                return [
                    'action' => ManagerOrderAdjustment::ACTION_PRODUCT_REMOVED,
                    'order_product_id' => $itemId,
                    'order_gift_id' => null,
                    'before' => $before,
                    'after' => null,
                ];
            }
        );
    }

    public function removeGift(
        User $manager,
        int $orderId,
        int $giftId,
        string $operationId,
        string $reason,
        ?int $fulfillmentIssueId = null
    ): array {
        return $this->perform(
            $manager,
            $orderId,
            $operationId,
            $reason,
            $fulfillmentIssueId,
            function (Order $order) use ($giftId): array {
                /** @var OrderGift|null $gift */
                $gift = OrderGift::query()
                    ->where('order_id', $order->id)
                    ->whereKey($giftId)
                    ->lockForUpdate()
                    ->with('items.product')
                    ->first();
                if (!$gift) {
                    throw (new ModelNotFoundException())->setModel(
                        OrderGift::class,
                        [$giftId]
                    );
                }

                $before = $this->giftSnapshot($gift);
                foreach ($gift->items as $item) {
                    $this->adjustLineUsage(
                        $order,
                        $item,
                        -$this->discountUses($item, (int) $item->quantity),
                        false
                    );
                }
                $gift->delete();

                return [
                    'action' => ManagerOrderAdjustment::ACTION_GIFT_REMOVED,
                    'order_product_id' => null,
                    'order_gift_id' => $giftId,
                    'before' => $before,
                    'after' => null,
                ];
            }
        );
    }

    public function replaceGift(
        User $manager,
        int $orderId,
        int $orderGiftId,
        int $giftId,
        int $giftVersion,
        int $quantity,
        string $operationId,
        string $reason,
        ?int $fulfillmentIssueId = null
    ): array {
        return $this->perform(
            $manager,
            $orderId,
            $operationId,
            $reason,
            $fulfillmentIssueId,
            function (Order $order) use (
                $orderGiftId,
                $giftId,
                $giftVersion,
                $quantity
            ): array {
                /** @var OrderGift|null $oldGift */
                $oldGift = OrderGift::query()
                    ->where('order_id', $order->id)
                    ->whereKey($orderGiftId)
                    ->lockForUpdate()
                    ->with('items.product')
                    ->first();
                if (!$oldGift) {
                    throw (new ModelNotFoundException())->setModel(
                        OrderGift::class,
                        [$orderGiftId]
                    );
                }
                $before = $this->giftSnapshot($oldGift);
                foreach ($oldGift->items as $item) {
                    $this->adjustLineUsage(
                        $order,
                        $item,
                        -$this->discountUses($item, (int) $item->quantity),
                        false
                    );
                }
                $oldGift->delete();

                /** @var Gift|null $gift */
                $gift = Gift::query()
                    ->where('user_id', $order->user_id)
                    ->where('status', Gift::STATUS_ACTIVE)
                    ->whereKey($giftId)
                    ->lockForUpdate()
                    ->with([
                        'items.productSize.sizeProfile',
                        'items.productSize.product.sub_subcategories.subcategory.category',
                    ])
                    ->first();
                if (!$gift) {
                    throw new DomainException(
                        'Новый подарочный набор не принадлежит покупателю или недоступен'
                    );
                }
                if ((int) $gift->version !== $giftVersion) {
                    throw new DomainException(
                        'Подарочный набор изменился; обновите его версию'
                    );
                }

                $newGift = $order->gifts()->create([
                    'gift_id' => $gift->id,
                    'client_instance_id' => (string) Str::uuid(),
                    'gift_version' => $gift->version,
                    'name' => $gift->name,
                    'description' => $gift->description,
                    'quantity' => $quantity,
                    'markup_unit_amount' => $gift->markup_amount,
                    'markup_total_amount' => round(
                        (float) $gift->markup_amount * $quantity,
                        2
                    ),
                    'layout_snapshot' => $gift->layout_snapshot,
                ]);

                $componentsBase = 0.0;
                $componentsDiscount = 0.0;
                $componentsFinal = 0.0;
                foreach ($gift->items as $giftItem) {
                    $size = $giftItem->productSize;
                    $size->assertConstructorReady();
                    $componentQuantity = (int) $size->product_quantity * $quantity;
                    $quote = $this->pricingService->quoteProductLine(
                        $size->product,
                        $componentQuantity,
                        $order->user
                    );
                    $newGift->items()->create(
                        $this->currentPriceAttributes($quote) + [
                            'order_id' => $order->id,
                            'product_size_id' => $size->id,
                            'gift_item_client_id' => $giftItem->client_item_id,
                            'gift_item_quantity' => $size->product_quantity,
                            'gift_item_sort_order' => $giftItem->sort_order,
                        ]
                    );
                    $this->consumeCurrentPromotion($order, $quote);
                    $componentsBase += (float) $quote['base_total'];
                    $componentsDiscount += (float) $quote['promotion_discount_amount'];
                    $componentsFinal += (float) $quote['final_total'];
                }

                $markup = round((float) $newGift->markup_total_amount, 2);
                $newGift->update([
                    'components_base_total' => round($componentsBase, 2),
                    'components_discount_amount' => round($componentsDiscount, 2),
                    'total_price' => round($componentsFinal + $markup, 2),
                ]);

                return [
                    'action' => ManagerOrderAdjustment::ACTION_GIFT_REPLACED,
                    'order_product_id' => null,
                    'order_gift_id' => $newGift->id,
                    'before' => $before,
                    'after' => $this->giftSnapshot(
                        $newGift->fresh(['items.product'])
                    ),
                ];
            }
        );
    }

    /** @return array{order: Order, adjustment: ManagerOrderAdjustment, already_applied: bool} */
    private function perform(
        User $manager,
        int $orderId,
        string $operationId,
        string $reason,
        ?int $fulfillmentIssueId,
        callable $mutation
    ): array {
        $result = DB::transaction(function () use (
            $manager,
            $orderId,
            $operationId,
            $reason,
            $fulfillmentIssueId,
            $mutation
        ): array {
            $order = $this->lockAccessibleRoot($manager, $orderId);
            $this->accessService->assertAllOrderLocations($manager, $order);
            $existing = ManagerOrderAdjustment::query()
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ((int) $existing->order_id !== (int) $order->id) {
                    throw new DomainException(
                        'Идентификатор операции уже использован для другого заказа'
                    );
                }

                return ['adjustment_id' => $existing->id, 'already_applied' => true];
            }

            $this->assertEditable($order);

            $issue = $this->lockIssue(
                $manager,
                $order,
                $fulfillmentIssueId
            );
            $beforeTotals = $this->totalsSnapshot($order);
            $this->retireCurrentFulfillment($order, $manager->id);

            $change = $mutation($order);
            if (!$order->items()->exists()) {
                throw new DomainException(
                    'Нельзя удалить последнюю позицию: отмените заказ целиком'
                );
            }

            $this->recalculateRootTotals($order);
            $this->refreshPricingSnapshot($order);
            $this->createReplacementFulfillment($manager, $order);

            $adjustment = ManagerOrderAdjustment::query()->create([
                'operation_id' => $operationId,
                'order_id' => $order->id,
                'manager_id' => $manager->id,
                'fulfillment_issue_id' => $issue?->id,
                'action' => $change['action'],
                'order_product_id' => $change['order_product_id'],
                'order_gift_id' => $change['order_gift_id'],
                'reason' => trim($reason),
                'before_snapshot' => [
                    'target' => $change['before'],
                    'totals' => $beforeTotals,
                ],
                'after_snapshot' => [
                    'target' => $change['after'],
                    'totals' => $this->totalsSnapshot($order->fresh()),
                ],
                'created_at' => now(),
            ]);

            $entry = sprintf(
                '[%s] Менеджер #%d %s изменил состав заказа (%s). Причина: %s',
                now()->toIso8601String(),
                $manager->id,
                trim((string) $manager->name),
                $this->actionLabel($change['action']),
                trim($reason)
            );
            $order->update([
                'was_edited' => true,
                'internal_notes' => trim(
                    ($order->internal_notes ? $order->internal_notes . "\n" : '')
                    . $entry
                ),
                'stock_reserved_at' => now(),
                'stock_released_at' => null,
            ]);

            return ['adjustment_id' => $adjustment->id, 'already_applied' => false];
        }, 3);

        return [
            'order' => $this->orderService->findAccessible($manager, $orderId),
            'adjustment' => ManagerOrderAdjustment::query()
                ->with(['manager', 'fulfillmentIssue'])
                ->findOrFail($result['adjustment_id']),
            'already_applied' => $result['already_applied'],
        ];
    }

    private function lockAccessibleRoot(User $manager, int $orderId): Order
    {
        $query = Order::query()
            ->realOrders()
            ->whereNull('parent_order_id');
        $this->accessService->scopeOrders($query, $manager);

        /** @var Order|null $order */
        $order = $query->whereKey($orderId)->lockForUpdate()->first();
        if (!$order) {
            throw (new ModelNotFoundException())->setModel(Order::class, [$orderId]);
        }

        $parts = Order::query()
            ->where('parent_order_id', $order->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $order->setRelation('partialOrders', $parts);
        $order->loadMissing('user', 'shippingAddress');
        OrderProduct::query()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return $order;
    }

    private function assertEditable(Order $order): void
    {
        if ($order->sales_channel !== Order::SALES_CHANNEL_ONLINE
            || $order->is_supplier_order) {
            throw new DomainException(
                'Состав можно менять только у интернет-заказа покупателя'
            );
        }
        if ($order->paid_at !== null) {
            throw new DomainException(
                'Состав оплаченного заказа нельзя менять до реализации доплаты и возврата'
            );
        }
        if (!in_array($order->status, [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_MANAGER_REVIEW,
        ], true)) {
            throw new DomainException(
                'Состав заказа можно менять только до начала физической сборки'
            );
        }
        if (!$order->shippingAddress) {
            throw new DomainException('У заказа отсутствует адрес для перераспределения');
        }

        $orders = collect([$order])->concat($order->partialOrders);
        $started = $orders->contains(fn (Order $item): bool =>
            $item->stock_committed_at !== null
            || $item->picking_started_at !== null
            || $item->ready_for_delivery_at !== null
            || $item->picker_id !== null
            || $item->courier_id !== null
            || in_array($item->status, [
                Order::STATUS_PROCESSING,
                Order::STATUS_READY_FOR_DELIVERY,
                Order::STATUS_SHIPPED,
                Order::STATUS_AWAITING_RECEIPT,
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
                Order::STATUS_COMPLETED,
            ], true)
        );
        if ($started) {
            throw new DomainException(
                'Сборка или доставка уже началась; сначала верните заказ в безопасное состояние'
            );
        }
    }

    private function lockIssue(
        User $manager,
        Order $order,
        ?int $fulfillmentIssueId
    ): ?FulfillmentIssue {
        if ($fulfillmentIssueId === null) {
            return null;
        }

        $orderIds = collect([$order->id])
            ->concat($order->partialOrders->pluck('id'))
            ->map(fn ($id): int => (int) $id);
        $issue = FulfillmentIssue::query()
            ->whereIn('source_order_id', $orderIds)
            ->whereKey($fulfillmentIssueId)
            ->lockForUpdate()
            ->first();
        if (!$issue) {
            throw (new ModelNotFoundException())->setModel(
                FulfillmentIssue::class,
                [$fulfillmentIssueId]
            );
        }

        $this->accessService->assertIssueAccess($manager, $issue);
        if ($issue->status !== FulfillmentIssue::STATUS_IN_REVIEW) {
            throw new DomainException(
                'Перед изменением по проблеме менеджер должен взять её в рассмотрение'
            );
        }
        if (!$this->accessService->isAdmin($manager)
            && (int) $issue->manager_id !== (int) $manager->id) {
            throw new AuthorizationException('Проблема взята другим менеджером');
        }

        return $issue;
    }

    private function retireCurrentFulfillment(Order $order, int $managerId): void
    {
        if ($order->partialOrders->isNotEmpty()) {
            foreach ($order->partialOrders as $part) {
                $this->warehouseService->releaseOnlineStockForOrder(
                    $part,
                    $managerId,
                    'Перераспределение резерва после изменения состава заказа менеджером'
                );
                $part->delete();
            }

            return;
        }

        if ($order->stock_reserved_at !== null) {
            $this->warehouseService->releaseOnlineStockForOrder(
                $order,
                $managerId,
                'Перераспределение резерва после изменения состава заказа менеджером'
            );
        }
    }

    private function createReplacementFulfillment(User $manager, Order $order): void
    {
        $allocation = $this->warehouseService->determineWarehousesForOrder(
            $order,
            $order->shippingAddress
        );
        $parts = $this->warehouseService->createPartialOrders(
            $order,
            $allocation,
            $order->shippingAddress
        );

        foreach ($parts as $part) {
            $this->accessService->assertWarehouseAccess(
                $manager,
                (int) $part->warehouse_id
            );
            if ($part->destination_warehouse_id !== null) {
                $this->accessService->assertWarehouseAccess(
                    $manager,
                    (int) $part->destination_warehouse_id
                );
            }
            $part->update([
                'status' => $order->status,
                'confirmed_at' => $order->confirmed_at,
            ]);
        }

        $this->warehouseService->reserveOnlineStockForOrders(
            $parts,
            $manager->id
        );
    }

    private function lockStandaloneItem(Order $order, int $itemId): OrderProduct
    {
        /** @var OrderProduct|null $item */
        $item = OrderProduct::query()
            ->where('order_id', $order->id)
            ->whereNull('order_gift_id')
            ->whereKey($itemId)
            ->lockForUpdate()
            ->with('product')
            ->first();
        if (!$item) {
            throw (new ModelNotFoundException())->setModel(
                OrderProduct::class,
                [$itemId]
            );
        }

        return $item;
    }

    private function currentCatalogProduct(int $productId): Product
    {
        /** @var Product|null $product */
        $product = Product::query()
            ->whereKey($productId)
            ->where('is_available', true)
            ->lockForUpdate()
            ->first();
        if (!$product || !$product->canBeSoldIndividually()) {
            throw new DomainException('Товар недоступен для отдельного заказа на сайте');
        }

        return $product;
    }

    private function assertNoStandaloneProduct(
        Order $order,
        int $productId,
        ?int $exceptItemId = null
    ): void {
        $query = $order->items()
            ->whereNull('order_gift_id')
            ->where('product_id', $productId);
        if ($exceptItemId !== null) {
            $query->where('id', '!=', $exceptItemId);
        }
        if ($query->exists()) {
            throw new DomainException(
                'Товар уже есть отдельной строкой; измените её количество'
            );
        }
    }

    private function currentPriceAttributes(array $quote): array
    {
        $promotion = $quote['promotion'] ?? null;
        $promotionPercent = $promotion
            && ($promotion['value_type'] ?? null) === Discount::VALUE_PERCENT
                ? (float) $promotion['value']
                : 0.0;
        $snapshot = array_merge($quote, [
            'selected_discount' => null,
            'selected_discount_amount' => 0.0,
            'manager_price_policy' => 'current_catalog_price',
        ]);

        return [
            'product_id' => $quote['product_id'],
            'quantity' => $quote['quantity'],
            'stock_unit' => $quote['stock_unit'],
            'sale_step' => $quote['sale_step'],
            'price_unit_quantity' => $quote['price_unit_quantity'],
            'unit_price' => $quote['unit_price'],
            'promotion_discount_id' => $promotion['id'] ?? null,
            'selected_discount_id' => null,
            'promotion_discount_percent' => $promotionPercent,
            'personal_discount_percent' => 0,
            'promotion_discount_amount' => $quote['promotion_discount_amount'],
            'selected_discount_amount' => 0,
            'final_unit_price' => $quote['final_unit_price'],
            'total_price' => $quote['final_total'],
            'pricing_snapshot' => $snapshot,
        ];
    }

    private function consumeCurrentPromotion(Order $order, array $quote): void
    {
        $promotionId = $quote['promotion']['id'] ?? null;
        if ($promotionId === null) {
            return;
        }

        $this->pricingService->adjustOrderUsage(
            $order,
            $order->user,
            [(int) $promotionId => (int) $quote['discount_uses']],
            true
        );
    }

    private function adjustLineUsage(
        Order $order,
        OrderProduct $item,
        int $usesDelta,
        bool $enforcePositiveLimits
    ): void {
        if ($usesDelta === 0) {
            return;
        }

        $deltas = [];
        foreach (array_filter([
            $item->promotion_discount_id,
            $item->selected_discount_id,
        ]) as $discountId) {
            $deltas[(int) $discountId] =
                ($deltas[(int) $discountId] ?? 0) + $usesDelta;
        }
        if ($deltas !== []) {
            $this->pricingService->adjustOrderUsage(
                $order,
                $order->user,
                $deltas,
                $enforcePositiveLimits
            );
        }
    }

    private function discountUses(OrderProduct $item, int $quantity): int
    {
        return $item->stock_unit === Product::STOCK_UNIT_GRAM
            ? 1
            : $quantity;
    }

    private function recalculateRootTotals(Order $order): void
    {
        $items = $order->items()->get();
        $giftMarkup = round((float) $order->gifts()->sum('markup_total_amount'), 2);
        $baseTotal = round($items->sum(function (OrderProduct $item): float {
            return (float) $item->unit_price * (int) $item->quantity
                / max(1, (int) $item->price_unit_quantity);
        }) + $giftMarkup, 2);
        $promotionDiscount = round(
            (float) $items->sum('promotion_discount_amount'),
            2
        );
        $personalDiscount = round(
            (float) $items->sum('selected_discount_amount'),
            2
        );
        $discountedItems = round((float) $items->sum('total_price') + $giftMarkup, 2);
        $cartDiscount = min(
            (float) $order->cart_discount,
            $discountedItems
        );
        $shippingNet = max(
            0,
            (float) $order->shipping_cost - (float) $order->shipping_discount
        );

        $order->updateQuietly([
            'products_total' => $baseTotal,
            'promotion_discount' => $promotionDiscount,
            'personal_discount' => $personalDiscount,
            'cart_discount' => round($cartDiscount, 2),
            'final_total' => round(
                max(0, $discountedItems - $cartDiscount) + $shippingNet,
                2
            ),
        ]);
        $order->refresh();
    }

    private function refreshPricingSnapshot(Order $order): void
    {
        $snapshot = is_array($order->pricing_snapshot)
            ? $order->pricing_snapshot
            : [];
        $snapshot = array_merge($snapshot, [
            'products_total' => (float) $order->products_total,
            'promotion_discount' => (float) $order->promotion_discount,
            'personal_discount' => (float) $order->personal_discount,
            'cart_discount' => (float) $order->cart_discount,
            'shipping_cost' => (float) $order->shipping_cost,
            'shipping_discount' => (float) $order->shipping_discount,
            'final_total' => (float) $order->final_total,
            'lines' => $order->items()
                ->orderBy('id')
                ->get()
                ->map(fn (OrderProduct $item): array =>
                    $item->pricing_snapshot ?? $this->lineSnapshot($item))
                ->values()
                ->all(),
            'manager_adjusted_at' => now()->toIso8601String(),
        ]);
        $order->updateQuietly(['pricing_snapshot' => $snapshot]);
        $order->pricing_snapshot = $snapshot;
    }

    private function lineSnapshot(OrderProduct $item): array
    {
        return [
            'id' => (int) $item->id,
            'product_id' => (int) $item->product_id,
            'product_name' => $item->product?->name,
            'order_gift_id' => $item->order_gift_id
                ? (int) $item->order_gift_id
                : null,
            'quantity' => (int) $item->quantity,
            'stock_unit' => $item->stock_unit,
            'sale_step' => (int) $item->sale_step,
            'price_unit_quantity' => (int) $item->price_unit_quantity,
            'unit_price' => (float) $item->unit_price,
            'promotion_discount_id' => $item->promotion_discount_id
                ? (int) $item->promotion_discount_id
                : null,
            'selected_discount_id' => $item->selected_discount_id
                ? (int) $item->selected_discount_id
                : null,
            'promotion_discount_amount' => (float) $item->promotion_discount_amount,
            'selected_discount_amount' => (float) $item->selected_discount_amount,
            'final_unit_price' => (float) $item->final_unit_price,
            'total_price' => (float) $item->total_price,
            'pricing_snapshot' => $item->pricing_snapshot,
        ];
    }

    private function giftSnapshot(OrderGift $gift): array
    {
        return [
            'id' => (int) $gift->id,
            'gift_id' => $gift->gift_id ? (int) $gift->gift_id : null,
            'name' => $gift->name,
            'quantity' => (int) $gift->quantity,
            'markup_total_amount' => (float) $gift->markup_total_amount,
            'total_price' => (float) $gift->total_price,
            'items' => $gift->items
                ->map(fn (OrderProduct $item): array => $this->lineSnapshot($item))
                ->values()
                ->all(),
        ];
    }

    private function totalsSnapshot(Order $order): array
    {
        return [
            'products_total' => (float) $order->products_total,
            'promotion_discount' => (float) $order->promotion_discount,
            'personal_discount' => (float) $order->personal_discount,
            'cart_discount' => (float) $order->cart_discount,
            'shipping_cost' => (float) $order->shipping_cost,
            'shipping_discount' => (float) $order->shipping_discount,
            'final_total' => (float) $order->final_total,
        ];
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            ManagerOrderAdjustment::ACTION_QUANTITY_CHANGED => 'изменение количества',
            ManagerOrderAdjustment::ACTION_PRODUCT_ADDED => 'добавление товара',
            ManagerOrderAdjustment::ACTION_PRODUCT_REPLACED => 'замена товара',
            ManagerOrderAdjustment::ACTION_PRODUCT_REMOVED => 'удаление товара',
            ManagerOrderAdjustment::ACTION_GIFT_REPLACED => 'замена подарочного набора',
            ManagerOrderAdjustment::ACTION_GIFT_REMOVED => 'удаление подарочного набора',
            default => $action,
        };
    }
}
