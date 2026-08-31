<?php

namespace App\Services;

use App\Models\Gift;
use App\Models\GiftSizeProfile;
use App\Models\Order;
use App\Models\OrderGift;
use App\Models\OrderProduct;
use App\Models\ProductSize;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GiftConstructorService
{
    public function __construct(
        protected GiftLayoutService $layoutService,
        protected GiftAvailabilityService $availabilityService,
        protected PricingService $pricingService
    ) {
    }

    public function options(?string $role = null, bool $simpleOnly = false): array
    {
        $boxes = GiftSizeProfile::query()
            ->where('kind', GiftSizeProfile::KIND_BOX)
            ->where('is_active', true)
            ->when($simpleOnly, fn ($query) => $query
                ->where('simple_constructor_enabled', true))
            ->orderBy('name')
            ->get();
        $sizes = $this->availableProductSizes($role);

        return [
            'cell_size_mm' => (int) config('gifts.cell_size_mm', 10),
            'boxes' => $boxes,
            'product_sizes' => $sizes,
        ];
    }

    public function productsForBox(int $boxId): array
    {
        $box = $this->box($boxId);
        $sizes = $this->availableProductSizes()
            ->filter(fn (ProductSize $size): bool =>
                $this->layoutService->canPlaceSingleItem($box, $size)
            )
            ->values();

        return [
            'box' => $box,
            'product_sizes' => $sizes,
        ];
    }

    public function prepareAdvanced(array $payload): array
    {
        $box = $this->box((int) $payload['box_profile_id']);
        $sizes = $this->sizesForItems($payload['items']);
        $layout = $this->layoutService->validate($box, $sizes, $payload['items']);

        return compact('box', 'sizes', 'layout');
    }

    public function prepareSimple(array $payload): array
    {
        $box = $this->box((int) $payload['box_profile_id']);
        $requirements = $box->simpleRequirements();
        if ($requirements === null) {
            throw new DomainException(
                'Выбранная коробка недоступна в простом конструкторе'
            );
        }

        $teaIds = array_map('intval', (array) $payload['tea_product_size_ids']);
        $sweetIds = array_map('intval', (array) $payload['sweet_product_size_ids']);
        $this->assertSimpleCount(
            $teaIds,
            $requirements['tea_count'],
            'чая'
        );
        $this->assertSimpleCount(
            $sweetIds,
            $requirements['sweet_count'],
            'сладостей'
        );

        $availableSizes = $this->sizesForIds([...$teaIds, ...$sweetIds]);
        $tea = $this->sizesInInputOrder($teaIds, $availableSizes);
        $sweets = $this->sizesInInputOrder($sweetIds, $availableSizes);

        if ($tea->contains(fn (ProductSize $size): bool =>
            $size->constructor_role !== ProductSize::ROLE_TEA
        )) {
            throw new DomainException('В позиции чая выбран недопустимый товар');
        }
        if ($sweets->contains(fn (ProductSize $size): bool =>
            $size->constructor_role !== ProductSize::ROLE_SWEET
        )) {
            throw new DomainException('В позиции сладости выбран недопустимый товар');
        }

        // Порядок и повторы из запроса сохраняются: каждая позиция получает
        // собственное размещение, а одинаковые SKU агрегируются при резерве.
        $sizes = $tea->concat($sweets)->values();
        $layout = $this->layoutService->autoPlace($box, $sizes);

        return compact('box', 'sizes', 'layout');
    }

    public function quote(User $user, array $prepared, array $payload): array
    {
        $giftQuantity = (int) ($payload['quantity'] ?? 1);
        $required = $this->requiredProducts(
            $prepared['layout'],
            $prepared['sizes'],
            $giftQuantity
        );
        $this->availabilityService->assertAvailable($required, $payload['city'] ?? null);

        $order = new Order([
            'user_id' => $user->id,
            'status' => Order::STATUS_CART,
        ]);
        $gift = new OrderGift([
            'quantity' => $giftQuantity,
            'markup_unit_amount' => $this->markup($prepared['box'], $payload),
            'markup_total_amount' => round($this->markup($prepared['box'], $payload) * $giftQuantity, 2),
        ]);
        $gift->id = -1;
        $items = collect();
        foreach ($prepared['layout'] as $index => $layoutItem) {
            /** @var ProductSize $size */
            $size = $prepared['sizes']->firstWhere('id', $layoutItem['product_size_id']);
            $quantity = (int) $size->product_quantity * $giftQuantity;
            $item = new OrderProduct([
                'product_id' => $size->product_id,
                'order_gift_id' => -1,
                'quantity' => $quantity,
                ...$size->product->measurementSnapshot(),
            ]);
            $item->id = -($index + 1);
            $item->setRelation('product', $size->product);
            $items->push($item);
        }
        $gift->setRelation('items', $items);
        $order->setRelation('items', $items);
        $order->setRelation('gifts', collect([$gift]));

        $quote = $this->pricingService->quoteOrder(
            $order,
            $user,
            0,
            $payload['discount_selection'] ?? null
        );

        return [
            'valid' => true,
            'box' => $prepared['box'],
            'layout' => $prepared['layout'],
            'quantity' => $giftQuantity,
            'required_products' => $required,
            'totals' => $quote,
        ];
    }

    public function create(User $user, array $prepared, array $payload): Gift
    {
        return DB::transaction(function () use ($user, $prepared, $payload): Gift {
            $gift = Gift::query()->create([
                'user_id' => $user->id,
                'gift_size_profile_id' => $prepared['box']->id,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'status' => Gift::STATUS_ACTIVE,
                'visibility' => Gift::VISIBILITY_PRIVATE,
                'markup_amount' => $this->markup($prepared['box'], $payload),
                'version' => 1,
                'layout_snapshot' => $prepared['layout'],
            ]);
            $this->replaceItems($gift, $prepared['layout']);
            return $gift->fresh($this->relations());
        });
    }

    public function update(User $user, int $giftId, array $prepared, array $payload): Gift
    {
        return DB::transaction(function () use ($user, $giftId, $prepared, $payload): Gift {
            $gift = Gift::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($giftId);
            $gift->update([
                'gift_size_profile_id' => $prepared['box']->id,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'status' => Gift::STATUS_ACTIVE,
                'markup_amount' => $this->markup($prepared['box'], $payload),
                'version' => $gift->version + 1,
                'layout_snapshot' => $prepared['layout'],
            ]);
            $gift->items()->delete();
            $this->replaceItems($gift, $prepared['layout']);
            return $gift->fresh($this->relations());
        });
    }

    public function archive(User $user, int $giftId): void
    {
        Gift::query()
            ->where('user_id', $user->id)
            ->whereKey($giftId)
            ->update(['status' => Gift::STATUS_ARCHIVED]);
    }

    public function findOwned(User $user, int $giftId): Gift
    {
        return Gift::query()
            ->where('user_id', $user->id)
            ->with($this->relations())
            ->findOrFail($giftId);
    }

    public function owned(User $user): Collection
    {
        return Gift::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', Gift::STATUS_ARCHIVED)
            ->with($this->relations())
            ->latest('updated_at')
            ->get();
    }

    /** @param array<int, array<string, mixed>> $layout */
    private function replaceItems(Gift $gift, array $layout): void
    {
        foreach ($layout as $index => $item) {
            $gift->items()->create([
                'product_size_id' => $item['product_size_id'],
                'client_item_id' => $item['client_item_id'],
                'position_x' => $item['position_x'],
                'position_y' => $item['position_y'],
                'is_rotated' => $item['is_rotated'],
                'sort_order' => $index,
            ]);
        }
    }

    private function box(int $id): GiftSizeProfile
    {
        return GiftSizeProfile::query()
            ->where('kind', GiftSizeProfile::KIND_BOX)
            ->where('is_active', true)
            ->findOrFail($id);
    }

    private function sizesForItems(array $items): Collection
    {
        return $this->sizesForIds(array_column($items, 'product_size_id'));
    }

    private function sizesForIds(array $ids): Collection
    {
        $uniqueIds = collect($ids)->map(fn ($id): int => (int) $id)->unique()->values();
        $sizes = ProductSize::query()
            ->whereIn('id', $uniqueIds)
            ->with([
                'sizeProfile',
                'product.sub_subcategories.subcategory.category',
                'product.images',
            ])
            ->get();
        if ($sizes->count() !== $uniqueIds->count()) {
            throw new DomainException('Один из форматов товара не найден');
        }
        return $sizes;
    }

    private function availableProductSizes(?string $role = null): Collection
    {
        return ProductSize::query()
            ->where('is_active', true)
            ->when($role, fn ($query) => $query->where('constructor_role', $role))
            ->whereHas('sizeProfile', fn ($query) => $query
                ->where('kind', GiftSizeProfile::KIND_ITEM)
                ->where('is_active', true))
            ->whereHas('product', fn ($query) => $query->where('is_available', true))
            ->with(['sizeProfile', 'product.images', 'product.brand'])
            ->orderBy('product_id')
            ->orderBy('product_quantity')
            ->get();
    }

    private function sizesInInputOrder(array $ids, Collection $availableSizes): Collection
    {
        return collect($ids)->map(function (int $id) use ($availableSizes): ProductSize {
            $size = $availableSizes->firstWhere('id', $id);
            if (!$size) {
                throw new DomainException('Один из форматов товара не найден');
            }
            return $size;
        });
    }

    private function assertSimpleCount(array $ids, int $required, string $label): void
    {
        if (count($ids) !== $required) {
            throw new DomainException(sprintf(
                'Для выбранной коробки требуется ровно %d позиций %s',
                $required,
                $label
            ));
        }
    }

    private function requiredProducts(array $layout, Collection $sizes, int $giftQuantity): array
    {
        $required = [];
        foreach ($layout as $item) {
            /** @var ProductSize $size */
            $size = $sizes->firstWhere('id', $item['product_size_id']);
            $required[$size->product_id] = ($required[$size->product_id] ?? 0)
                + (int) $size->product_quantity * $giftQuantity;
        }
        return $required;
    }

    private function markup(GiftSizeProfile $box, array $payload): float
    {
        // Клиент не может менять наценку: она управляется настройкой коробки.
        return round((float) $box->default_markup_amount, 2);
    }

    private function relations(): array
    {
        return [
            'sizeProfile',
            'items.productSize.sizeProfile',
            'items.productSize.product.images',
        ];
    }
}
