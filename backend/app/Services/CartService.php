<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderProduct;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Inventory;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CartService
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Получить корзину пользователя (с кешированием)
     */
    public function getCart(int $userId): Order
    {
        return DB::transaction(function () use ($userId) {
            $cart = Order::with([
                'items.product',
                'shippingAddress'
            ])->firstOrCreate([
                'user_id' => $userId,
                'status' => Order::STATUS_CART
            ], [
                'products_total' => 0,
                'promotion_discount' => 0,
                'personal_discount' => 0,
                'cart_discount' => 0,
                'shipping_cost' => 0,
                'final_total' => 0
            ]);

            // Корзина может лежать открытой во время изменения акции в Filament.
            // Поэтому GET корзины обновляет только производные суммы, но не расходует лимиты.
            if ($cart->items->isNotEmpty()) {
                $this->recalculateCart($cart);
            }

            return $cart->fresh(['items.product', 'shippingAddress']);
        });
    }

    /**
     * Очистить кеш корзины
     */
    public function clearCartCache(int $userId): void
    {
        Cache::forget("user_cart_{$userId}");
    }

    /**
     * Добавить товар в корзину
     */
    public function addItem(int $userId, int $productId, int $quantity, ?string $city = null, bool $isSupplierOrder = false): Order
    {
        return DB::transaction(function () use ($userId, $productId, $quantity, $city, $isSupplierOrder) {
            $cart = $this->getCart($userId);
            $product = Product::with(['inventories.warehouse', 'suppliers'])->findOrFail($productId);
            $product->assertValidSaleQuantity($quantity);

            // Для обычных заказов проверяем город
            if (!$isSupplierOrder && $city) {
                $this->validateCityCompatibility($cart, $productId, $city);
                $this->validateCityAvailability($productId, $city, $quantity);
            }

            // Для заказов у поставщика проверяем поставщиков
            if ($isSupplierOrder) {
                $this->validateSupplierAvailability($product, $quantity);
            }

            // Для обычных заказов проверяем общее наличие
            if (!$isSupplierOrder && !$city) {
                $this->validateGlobalAvailability($productId, $quantity);
            }

            $this->upsertCartItem($cart, $product, $quantity);
            $this->recalculateCart($cart);
            $this->clearCartCache($userId);

            return $cart->fresh(['items.product']);
        });
    }

    /**
     * Проверить доступность товара у поставщиков
     */
    private function validateSupplierAvailability(Product $product, int $quantity): void
    {
        $suppliers = $product->suppliers()
            ->where('product_supplier.is_active', true)
            ->where('suppliers.is_active', true)
            ->get();
        
        if ($suppliers->isEmpty()) {
            throw new DomainException("Этот товар недоступен для заказа у поставщиков");
        }
        
        $supplier = $suppliers->first();
        $minOrderQuantity = $supplier->pivot->min_order_quantity ?? 1;
        
        if ($quantity < $minOrderQuantity) {
            throw new DomainException("Минимальный заказ у поставщика: {$minOrderQuantity}");
        }
    }

    /**
     * Обновить количество товара в корзине
     */
    public function updateItemQuantity(int $userId, int $itemId, int $quantity): Order
    {
        return DB::transaction(function () use ($userId, $itemId, $quantity) {
            $cart = $this->getUserCart($userId);
            $cartItem = $cart->items()->with('product')->findOrFail($itemId);

            if ($quantity === 0) {
                $cartItem->delete();
            } else {
                $cartItem->product->assertValidSaleQuantity($quantity);
                $this->validateGlobalAvailability($cartItem->product_id, $quantity);

                $this->updateCartItem($cartItem, $quantity);
            }

            $this->recalculateCart($cart);
            $this->clearCartCache($userId);

            return $cart->fresh(['items.product']);
        });
    }

    /**
     * Удалить товар из корзины
     */
    public function removeItem(int $userId, int $itemId): Order
    {
        return DB::transaction(function () use ($userId, $itemId) {
            $cart = $this->getUserCart($userId);
            $cartItem = $cart->items()->findOrFail($itemId);
            
            $cartItem->delete();
            $this->recalculateCart($cart);
            $this->clearCartCache($userId);

            return $cart->fresh(['items.product']);
        });
    }

    /**
     * Очистить корзину
     */
    public function clearCart(int $userId): Order
    {
        return DB::transaction(function () use ($userId) {
            $cart = $this->getCart($userId);
            $cart->items()->delete();
            $this->recalculateCart($cart);
            $this->clearCartCache($userId);
            return $cart;
        });
    }

    /**
     * Проверить совместимость по городу
     */
    private function validateCityCompatibility(Order $cart, int $productId, string $city): void
    {
        if ($cart->items->isEmpty()) {
            return;
        }

        // Получаем города для всех товаров в корзине
        $incompatibleItems = $cart->items->load(['product.inventories' => function($query) {
                $query->availableForOnline()->with('warehouse');
            }])
            ->filter(function ($item) use ($city) {
                $itemCities = $item->product->inventories->pluck('warehouse.city')->unique();
                return !$itemCities->contains($city);
            });

        if ($incompatibleItems->isNotEmpty()) {
            $incompatibleProductNames = $incompatibleItems->pluck('product.name')->join(', ');
            
            throw new DomainException(
                "Не все товары в корзине доступны в городе {$city}. " .
                "Следующие товары недоступны: {$incompatibleProductNames}"
            );
        }

        // Проверить доступность нового товара в городе
        $productCities = $this->getProductCities($productId);
        if (!$productCities->contains($city)) {
            $availableCities = $productCities->join(', ');
            throw new DomainException(
                "Товар недоступен в городе {$city}. " .
                "Этот товар доступен в: " . ($availableCities ?: 'не определённых городах')
            );
        }
    }

    /**
     * Проверить наличие товара в указанном городе
     */
    private function validateCityAvailability(int $productId, string $city, int $quantity): void
    {
        $availableInCity = $this->getAvailableQuantityInCity($productId, $city);
        
        if ($availableInCity < $quantity) {
            throw new DomainException(
                "Недостаточно товара в наличии в городе {$city}. Доступно: {$availableInCity}"
            );
        }
    }

    /**
     * Проверить общее наличие товара
     */
    private function validateGlobalAvailability(int $productId, int $quantity): void
    {
        $totalAvailable = $this->getTotalAvailableQuantity($productId);
        
        if ($totalAvailable < $quantity) {
            throw new DomainException("Недостаточно товара в наличии. Доступно: {$totalAvailable}");
        }
    }

    /**
     * Получить количество товара в городе (с кешем)
     */
    private function getAvailableQuantityInCity(int $productId, string $city): int
    {
        $cacheKey = "product_{$productId}_city_{$city}_quantity";
        
        return Cache::remember($cacheKey, 60, function () use ($productId, $city) {
            $warehouseIds = Warehouse::where('city', $city)
                ->onlineFulfillment()
                ->pluck('id');
            
            return Inventory::sumOnlineAvailable(
                Inventory::query()
                    ->where('product_id', $productId)
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->onlineFulfillment()
            );
        });
    }

    /**
     * Получить общее количество товара
     */
    private function getTotalAvailableQuantity(int $productId): int
    {
        $cacheKey = "product_{$productId}_total_quantity";
        
        return Cache::remember($cacheKey, 60, function () use ($productId) {
            return Inventory::sumOnlineAvailable(
                Inventory::query()
                    ->where('product_id', $productId)
                    ->onlineFulfillment()
            );
        });
    }

    /**
     * Получить города где доступен товар
     */
    private function getProductCities(int $productId): \Illuminate\Support\Collection
    {
        $cacheKey = "product_{$productId}_available_cities";
        
        return Cache::remember($cacheKey, 3600, function () use ($productId) {
            return Inventory::where('product_id', $productId)
                ->availableForOnline()
                ->with('warehouse')
                ->get()
                ->pluck('warehouse.city')
                ->unique()
                ->values();
        });
    }

    /**
     * Добавить или обновить товар в корзине
     */
    private function upsertCartItem(Order $cart, Product $product, int $quantity): void
    {
        $cartItem = $cart->items()->where('product_id', $product->id)->first();

        if ($cartItem) {
            $this->updateCartItem($cartItem, $quantity);
        } else {
            $this->createCartItem($cart, $product, $quantity);
        }
    }

    /**
     * Создать запись товара в корзине
     */
    private function createCartItem(Order $cart, Product $product, int $quantity): void
    {
        $baseTotal = $product->baseTotalForQuantity($quantity);

        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            ...$product->measurementSnapshot(),
            'unit_price' => $product->price,
            'promotion_discount_percent' => 0,
            'personal_discount_percent' => 0,
            'final_unit_price' => $product->price,
            'total_price' => $baseTotal,
        ]);
    }

    /**
     * Обновить запись товара в корзине
     */
    private function updateCartItem(OrderProduct $cartItem, int $quantity): void
    {
        $product = $cartItem->product;

        $cartItem->update([
            'quantity' => $quantity,
            ...$product->measurementSnapshot(),
            'unit_price' => $product->price,
            'promotion_discount_percent' => 0,
            'personal_discount_percent' => 0,
            'final_unit_price' => $product->price,
            'total_price' => $product->baseTotalForQuantity($quantity),
        ]);
    }

    /**
     * Пересчитать итоговые суммы корзины
     */
    private function recalculateCart(Order $cart): void
    {
        $cart->load('items.product');

        if ($cart->items->isEmpty()) {
            $cart->update([
                'products_total' => 0,
                'promotion_discount' => 0,
                'personal_discount' => 0,
                'cart_discount' => 0,
                'shipping_discount' => 0,
                'final_total' => 0,
                'discount_id' => null,
                'applied_promotion_code' => null,
                'pricing_snapshot' => null,
            ]);
            return;
        }

        $quote = $this->pricingService->quoteOrder(
            $cart,
            User::findOrFail($cart->user_id)
        );
        $this->pricingService->applyQuoteToOrder($cart, $quote);
    }

    /**
     * Получить корзину пользователя (только существующую)
     */
    private function getUserCart(int $userId): Order
    {
        return Order::where('user_id', $userId)
            ->where('status', Order::STATUS_CART)
            ->firstOrFail();
    }
}
