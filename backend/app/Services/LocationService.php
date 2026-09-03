<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Inventory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class LocationService
{
    public function __construct(
        private ProductRatingService $productRatingService
    ) {
    }

    /**
     * Получить товары доступные в конкретном городе
     */
    public function getProductsAvailableInCity(string $city, array $filters = [])
    {         
        $query = $this->productRatingService->withPublicAggregates(Product::with([
            'brand', 
            'sub_subcategories.subcategory.category',
            'promotions', 
            'inventories.warehouse',
            'suppliers'
        ]))->individualSale();

        // 🎯 ФИЛЬТРАЦИЯ ПО ТИПУ ДОСТУПНОСТИ
        if (!empty($filters['availability'])) {
            if ($filters['availability'] === 'local') {
                // Только товары на локальных складах
                $warehouseIds = $this->getWarehouseIdsInCity($city);
                $query->whereHas('inventories', function($query) use ($warehouseIds) {
                    $query->whereIn('warehouse_id', $warehouseIds)
                          ->availableForOnline();
                });
            } elseif ($filters['availability'] === 'supplier') {
                // Только товары у поставщиков
                $query->whereHas('suppliers', function($query) {
                    $query->where('product_supplier.is_active', true)
                          ->where('suppliers.is_active', true);
                });
            }
        } else {
            // По умолчанию - все доступные товары
            $warehouseIds = $this->getWarehouseIdsInCity($city);
            $query->where(function($q) use ($warehouseIds) {
                $q->whereHas('inventories', function($query) use ($warehouseIds) {
                    $query->whereIn('warehouse_id', $warehouseIds)
                          ->availableForOnline();
                })->orWhereHas('suppliers', function($query) {
                    $query->where('product_supplier.is_active', true)
                          ->where('suppliers.is_active', true);
                });
            });
        }
        
        // СТАНДАРТНЫЕ ФИЛЬТРЫ
        if (!empty($filters['category_id'])) {
            $query->whereHas('sub_subcategories.subcategory.category', function($q) use ($filters) {
                $q->where('id', $filters['category_id']);
            });
        }
    
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }
    
        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }
    
        // ЛОГИКА ПАГИНАЦИИ
        $totalProducts = $query->count();
        $paginationThreshold = 1000;
        
        if ($totalProducts >= $paginationThreshold) {
            $products = $query->paginate($filters['per_page'] ?? 24);
            return $products;
        } else {
            $products = $query->get();
            return $products;
        }
    }

    /**
     * Получить обогащенные продукты с информацией о доступности
     */
    public function getEnrichedProductsInCity(string $city, array $filters = [])
    {
        $products = $this->getProductsAvailableInCity($city, $filters);
        
        // Если это пагинация
        if ($products instanceof LengthAwarePaginator) {
            $products->getCollection()->transform(function($product) use ($city) {
                return $this->enrichProductWithAvailability($product, $city);
            });
            return $products;
        }
        
        // Если это коллекция
        return $products->map(function($product) use ($city) {
            return $this->enrichProductWithAvailability($product, $city);
        });
    }

    /**
     * Обогатить продукт информацией о доступности
     */
    public function enrichProductWithAvailability(Product $product, string $city): array
    {
        // 1. Доступный остаток: физический минус оба вида резерва.
        $warehouseIds = $this->getWarehouseIdsInCity($city);
        $localQuantity = Inventory::sumOnlineAvailable(
            $product->inventories()
                ->whereIn('warehouse_id', $warehouseIds)
                ->onlineFulfillment()
        );
        $isLocallyAvailable = $localQuantity > 0;

        // 2. Наличие у поставщиков (виртуальное)
        $suppliers = $product->suppliers;
        $isSupplierAvailable = $suppliers->isNotEmpty();
        $bestSupplier = $suppliers->sortBy('pivot.lead_time_days')->first();

        return [
            'product' => $product, // ← сохраняем оригинальную модель Product
            'availability' => [
                'is_available_locally' => $isLocallyAvailable,
                'local_quantity' => $localQuantity,

                'is_available_from_supplier' => $isSupplierAvailable,
                'supplier_info' => $bestSupplier ? [
                    'supplier_id' => $bestSupplier->id,
                    'supplier_name' => $bestSupplier->name,
                    'lead_time_days' => $bestSupplier->pivot->lead_time_days,
                    'min_order_quantity' => $bestSupplier->pivot->min_order_quantity,
                    'estimated_delivery' => $bestSupplier->pivot->lead_time_days . ' дней',
                    'cost_price' => $bestSupplier->pivot->cost_price
                ] : null,
                
                'is_available' => $isLocallyAvailable || $isSupplierAvailable,
                'availability_type' => $isLocallyAvailable ? 'local' : ($isSupplierAvailable ? 'supplier' : 'none'),
                'availability_description' => $this->getAvailabilityDescription($isLocallyAvailable, $isSupplierAvailable, $localQuantity, $bestSupplier),
                'delivery_timeline' => $isLocallyAvailable ? '1-3 дня' : ($bestSupplier ? $bestSupplier->pivot->lead_time_days . ' дней' : 'недоступен')
            ]
        ];
    }

    /**
     * Получить описание доступности
     */
    private function getAvailabilityDescription(bool $local, bool $supplier, int $localQty, ?Supplier $bestSupplier): string
    {
        if ($local) {
            return "В наличии: {$localQty} шт.";
        }

        if ($supplier && $bestSupplier) {
            $days = $bestSupplier->pivot->lead_time_days;
            return "Под заказ ({$days} дней)";
        }

        return "Нет в наличии";
    }

    /**
     * Получить ID складов в городе
     */
    private function getWarehouseIdsInCity(string $city): array
    {
        return Warehouse::where('city', $city)
            ->onlineFulfillment()
            ->pluck('id')
            ->toArray();
    }

    /**
     * Получить все доступные города со складами
     */
    public function getAvailableCities()
    {
        return Cache::remember('available_cities', 3600, function () {
            return Warehouse::onlineFulfillment()
                ->distinct()
                ->pluck('city')
                ->filter()
                ->values();
        });
    }

    /**
     * Проверить доступность товара в городе (только локальные склады)
     */
    public function isProductAvailableInCity(Product $product, string $city): bool
    {
        $warehouseIds = $this->getWarehouseIdsInCity($city);

        return $product->inventories()
            ->whereIn('warehouse_id', $warehouseIds)
            ->availableForOnline()
            ->exists();
    }

    /**
     * Получить общее количество товара в городе (только локальные склады)
     */
    public function getProductQuantityInCity(Product $product, string $city): int
    {
        $cacheKey = "product_{$product->id}_city_{$city}_quantity";
        
        return Cache::remember($cacheKey, 300, function () use ($product, $city) {
            $warehouseIds = $this->getWarehouseIdsInCity($city);
            
            return Inventory::sumOnlineAvailable(
                $product->inventories()
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->onlineFulfillment()
            );
        });
    }

    /**
     * Проверить доступность товара у поставщиков
     */
    public function isProductAvailableFromSuppliers(Product $product): bool
    {
        return $product->suppliers()
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Получить лучшего поставщика для товара
     */
    public function getBestSupplierForProduct(Product $product): ?Supplier
    {
        return $product->suppliers()
            ->where('is_active', true)
            ->orderBy('pivot.lead_time_days')
            ->first();
    }

}
