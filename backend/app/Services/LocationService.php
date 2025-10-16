<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;


class LocationService
{
    /**
     * Получить все доступные города со складами
     */
    public function getAvailableCities()
    {
        return Cache::remember('available_cities', 3600, function () {
            return Warehouse::active()
                ->distinct()
                ->pluck('city')
                ->filter()
                ->values();
        });
    }

    /**
     * Получить ID складов в указанном городе
     */
    public function getWarehouseIdsInCity(string $city): array
    {
        return Cache::remember("warehouses_in_city_{$city}", 3600, function () use ($city) {
            return Warehouse::where('city', $city)
                ->active()
                ->pluck('id')
                ->toArray();
        });
    }

    /**
     * Получить товары доступные в конкретном городе
     */
    public function getProductsAvailableInCity(string $city, array $filters = [])
    {
        $warehouseIds = $this->getWarehouseIdsInCity($city);
    
        $query = Product::with([
                'brand', 
                'sub_subcategories.subcategory.category',
                'promotions', 
                'inventories.warehouse'
            ])
            ->whereHas('inventories', function($query) use ($warehouseIds) {
                $query->whereIn('warehouse_id', $warehouseIds)
                      ->where('quantity', '>', 0);
            });
        
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
    
        // Сохраняем твою логику пагинации по порогу
        $totalProducts = $query->count();
        $paginationThreshold = 1000;
        
        if ($totalProducts >= $paginationThreshold) {
            return $query->paginate($filters['per_page'] ?? 24);
        } else {
            return $query->get();
        }
    }

    /**
     * Проверить доступность товара в городе
     */
    public function isProductAvailableInCity(Product $product, string $city): bool
    {
        $warehouseIds = $this->getWarehouseIdsInCity($city);

        return $product->inventories()
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('quantity', '>', 0)
            ->exists();
    }

    /**
     * Получить общее количество товара в городе
     */
    public function getProductQuantityInCity(Product $product, string $city): int
    {
        $warehouseIds = $this->getWarehouseIdsInCity($city);

        return $product->inventories()
            ->whereIn('warehouse_id', $warehouseIds)
            ->sum('quantity');
            
    }
}