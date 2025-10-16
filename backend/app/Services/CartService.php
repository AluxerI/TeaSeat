<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderProduct;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        protected PriceCalculatorService $priceCalculator,
        protected InventoryService $inventoryService
    ) {}

    /**
     * Получить корзину пользователя
     */
    public function getCart(int $userId): Order
    {
        return Order::with([
            'items.product', 
            'items.product.promotions',
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
    }

    /**
     * Добавить товар в корзину
     */
    public function addItem(int $userId, int $productId, int $quantity, ?string $city = null): Order
    {
        return DB::transaction(function () use ($userId, $productId, $quantity, $city) {
            $cart = $this->getCart($userId);
            $product = Product::with(['inventories.warehouse'])->findOrFail($productId);

            if ($city) {
                $this->validateCityCompatibility($cart, $productId, $city);
                $this->validateCityAvailability($productId, $city, $quantity);
            }

            $this->validateGlobalAvailability($productId, $quantity);

            $priceCalculation = $this->priceCalculator->calculateForProduct(
                $product, 
                User::find($userId)
            );
            
            $this->upsertCartItem($cart, $product, $quantity, $priceCalculation);
            $this->recalculateCart($cart);

            return $cart->fresh(['items.product', 'items.product.promotions']);
        });
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
                $this->validateGlobalAvailability($cartItem->product_id, $quantity);
                
                $priceCalculation = $this->priceCalculator->calculateForProduct(
                    $cartItem->product, 
                    User::find($userId)
                );

                $this->updateCartItem($cartItem, $quantity, $priceCalculation);
            }

            $this->recalculateCart($cart);
            return $cart->fresh(['items.product', 'items.product.promotions']);
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

            return $cart->fresh(['items.product', 'items.product.promotions']);
        });
    }

    /**
     * Очистить корзину и сменить город
     */
    public function clearCart(int $userId): Order
    {
        return DB::transaction(function () use ($userId) {
            $cart = $this->getCart($userId);
            $cart->items()->delete();
            $this->recalculateCart($cart);
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

        $incompatibleItems = $cart->items->load(['product.inventories' => function($query) {
                $query->where('quantity', '>', 0)->with('warehouse');
            }])
            ->filter(function ($item) use ($city) {
                $itemCities = $item->product->inventories->pluck('warehouse.city');
                return !$itemCities->contains($city);
            });

        if ($incompatibleItems->isNotEmpty()) {
            $incompatibleProductNames = $incompatibleItems->pluck('product.name')->join(', ');
            $incompatibleCities = $incompatibleItems->flatMap(function($item) {
                return $item->product->inventories->pluck('warehouse.city');
            })->unique()->join(', ');
            
            throw new \Exception(
                "Не все товары в корзине доступны в городе {$city}. " .
                "Следующие товары недоступны: {$incompatibleProductNames}. " .
                "Они доступны в: {$incompatibleCities}"
            );
        }

        // Проверить доступность нового товара в городе
        $productCities = $this->getProductCities($productId);
        if (!$productCities->contains($city)) {
            throw new \Exception(
                "Товар недоступен в городе {$city}. " .
                "Этот товар доступен в: " . $productCities->join(', ')
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
            throw new \Exception(
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
            throw new \Exception("Недостаточно товара в наличии. Доступно: {$totalAvailable}");
        }
    }

    /**
     * Получить количество товара в городе
     */
    private function getAvailableQuantityInCity(int $productId, string $city): int
    {
        $warehouseIds = Warehouse::where('city', $city)->pluck('id');
        
        return Inventory::where('product_id', $productId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('quantity', '>', 0)
            ->sum('quantity');
    }

    /**
     * Получить общее количество товара
     */
    private function getTotalAvailableQuantity(int $productId): int
    {
        return Inventory::where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->sum('quantity');
    }

    /**
     * Получить города где доступен товар
     */
    private function getProductCities(int $productId): \Illuminate\Support\Collection
    {
        return Inventory::where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->with('warehouse')
            ->get()
            ->pluck('warehouse.city')
            ->unique()
            ->values();
    }

    /**
     * Добавить или обновить товар в корзине
     */
    private function upsertCartItem(Order $cart, Product $product, int $quantity, array $priceCalculation): void
    {
        $cartItem = $cart->items()->where('product_id', $product->id)->first();

        if ($cartItem) {
            $this->updateCartItem($cartItem, $quantity, $priceCalculation);
        } else {
            $this->createCartItem($cart, $product, $quantity, $priceCalculation);
        }
    }

    /**
     * Создать запись товара в корзине
     */
    private function createCartItem(Order $cart, Product $product, int $quantity, array $priceCalculation): void
    {
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $priceCalculation['base_price'],
            'promotion_discount_percent' => $priceCalculation['promotion_discount'],
            'personal_discount_percent' => $priceCalculation['personal_discount'],
            'final_unit_price' => $priceCalculation['final_price'],
            'total_price' => $quantity * $priceCalculation['final_price']
        ]);
    }

    /**
     * Обновить запись товара в корзине
     */
    private function updateCartItem(OrderProduct $cartItem, int $quantity, array $priceCalculation): void
    {
        $cartItem->update([
            'quantity' => $quantity,
            'unit_price' => $priceCalculation['base_price'],
            'promotion_discount_percent' => $priceCalculation['promotion_discount'],
            'personal_discount_percent' => $priceCalculation['personal_discount'],
            'final_unit_price' => $priceCalculation['final_price'],
            'total_price' => $quantity * $priceCalculation['final_price']
        ]);
    }

    /**
     * Пересчитать итоговые суммы корзины
     */
    private function recalculateCart(Order $cart): void
    {
        $cart->load('items');

        $productsTotal = 0;
        $promotionDiscount = 0;
        $personalDiscount = 0;
        $finalTotal = 0;

        foreach ($cart->items as $item) {
            $productsTotal += $item->quantity * $item->unit_price;
            $promotionDiscount += $item->quantity * ($item->unit_price * $item->promotion_discount_percent / 100);
            $personalDiscount += $item->quantity * ($item->unit_price * $item->personal_discount_percent / 100);
            $finalTotal += $item->total_price;
        }

        $cart->update([
            'products_total' => $productsTotal,
            'promotion_discount' => $promotionDiscount,
            'personal_discount' => $personalDiscount,
            'final_total' => $finalTotal - $cart->cart_discount
        ]);
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