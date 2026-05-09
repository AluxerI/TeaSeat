<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminBadgeService
{
    private static array $badges = [];
    private const CACHE_TTL = 300;

    public static function getAll(): array
    {
        if (!empty(self::$badges)) {
            return self::$badges;
        }

        self::$badges = Cache::remember('admin.badges.all', self::CACHE_TTL, function () {
            return self::calculateAllBadges();
        });

        return self::$badges;
    }

    public static function get(string $key): ?int
    {
        $badges = self::getAll();
        return $badges[$key] ?? null;
    }

    public static function getNavigationBadge(string $resource): ?string
    {
        $key = 'navigation.' . $resource;
        
        if (isset(self::$badges[$key])) {
            return self::$badges[$key];
        }

        $map = [
            // Управление каталогом
            'brand' => 'brands_total',
            'product' => 'products_total',
            'category' => 'categories_total',
            'subcategory' => 'subcategories_total',
            'sub_subcategory' => 'sub_subcategories_total',
            
            // Управление продажами
            'order' => 'pending_orders',
            
            // Маркетинг
            'promotion' => 'active_promotions',      // акции (type = 'promotion')
            'discount' => 'active_discounts',        // персональные скидки
            'coupon' => 'active_coupons', 
            
            // Модерация
            'review' => 'reviews_total',
            
            // Склад и поставщики
            'inventory' => 'low_stock_count',
            'warehouse' => 'warehouses_active',
            'supplier' => 'suppliers_active',
            
            // Управление пользователями
            'user' => 'users_active',
        ];

        $badgeKey = $map[$resource] ?? null;
        if (!$badgeKey) {
            return null;
        }

        $value = self::get($badgeKey);
        self::$badges[$key] = $value ? (string) $value : null;
        
        return self::$badges[$key];
    }

    public static function clearCache(): void
    {
        Cache::forget('admin.badges.all');
        self::$badges = [];
    }

    private static function calculateAllBadges(): array
    {
        $stats = DB::select("
            SELECT
                -- Склады
                (SELECT COUNT(*) FROM warehouses WHERE is_active = true) as warehouses_active,
                
                -- Поставщики
                (SELECT COUNT(*) FROM suppliers WHERE is_active = true) as suppliers_active,
                (SELECT COUNT(*) FROM suppliers) as suppliers_total,
                
                -- Товары
                (SELECT COUNT(*) FROM products WHERE is_available = true) as products_in_stock,
                (SELECT COUNT(*) FROM products) as products_total,
                
                -- Категории
                (SELECT COUNT(*) FROM categories) as categories_total,
                (SELECT COUNT(*) FROM subcategories) as subcategories_total,
                (SELECT COUNT(*) FROM sub_subcategories) as sub_subcategories_total,
                
                -- Бренды
                (SELECT COUNT(*) FROM brands) as brands_total,
                
                -- ✅ АКЦИИ (type = 'promotion' из таблицы discounts)
                (SELECT COUNT(*) FROM discounts 
                    WHERE type = 'promotion' 
                    AND is_active = true 
                    AND (start_date IS NULL OR start_date <= NOW()) 
                    AND (end_date IS NULL OR end_date >= NOW())
                ) as active_promotions,
                
                -- ✅ ПЕРСОНАЛЬНЫЕ СКИДКИ (type != 'promotion')
                (SELECT COUNT(*) FROM discounts 
                    WHERE type != 'promotion' 
                    AND is_active = true 
                    AND (start_date IS NULL OR start_date <= NOW()) 
                    AND (end_date IS NULL OR end_date >= NOW())
                ) as active_discounts,

                -- Акции
                (SELECT COUNT(*) FROM discounts 
                    WHERE type IN ('cart', 'shipping') 
                    AND is_active = true 
                    AND code IS NOT NULL
                    AND (start_date IS NULL OR start_date <= NOW()) 
                    AND (end_date IS NULL OR end_date >= NOW())
                ) as active_coupons,
                
                -- Заказы
                (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
                
                -- Отзывы
                (SELECT COUNT(*) FROM reviews) as reviews_total,
                
                -- Пользователи
                (SELECT COUNT(*) FROM users WHERE is_active = true) as users_active,
                
                -- Остатки
                (SELECT COUNT(*) FROM inventories WHERE quantity < 10) as low_stock_count
        ")[0];

        return [
            'warehouses_active' => (int) ($stats->warehouses_active ?? 0),
            'suppliers_active' => (int) ($stats->suppliers_active ?? 0),
            'suppliers_total' => (int) ($stats->suppliers_total ?? 0),
            'products_in_stock' => (int) ($stats->products_in_stock ?? 0),
            'products_total' => (int) ($stats->products_total ?? 0),
            'categories_total' => (int) ($stats->categories_total ?? 0),
            'subcategories_total' => (int) ($stats->subcategories_total ?? 0),
            'sub_subcategories_total' => (int) ($stats->sub_subcategories_total ?? 0),
            'brands_total' => (int) ($stats->brands_total ?? 0),
            'active_promotions' => (int) ($stats->active_promotions ?? 0),
            'active_discounts' => (int) ($stats->active_discounts ?? 0),
            'active_coupons' => (int) ($stats->active_coupons ?? 0),
            'pending_orders' => (int) ($stats->pending_orders ?? 0),
            'reviews_total' => (int) ($stats->reviews_total ?? 0),
            'users_active' => (int) ($stats->users_active ?? 0),
            'low_stock_count' => (int) ($stats->low_stock_count ?? 0),
        ];
    }
}