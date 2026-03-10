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
            'brand' => 'brands_total',
            'product' => 'products_total',
            'category' => 'categories_total',
            'subcategory' => 'subcategories_total',
            'sub_subcategory' => 'sub_subcategories_total',
            'order' => 'pending_orders',
            'promotion' => 'active_promotions',
            'discount' => 'active_discounts',
            'review' => 'reviews_total',
            'inventory' => 'low_stock',
            'warehouse' => 'warehouses_active',
            'supplier' => 'suppliers_total', 
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
                (SELECT COUNT(*) FROM warehouses WHERE is_active = true) as warehouses_active,
                (SELECT COUNT(*) FROM suppliers WHERE is_active = true) as suppliers_active,
                (SELECT COUNT(*) FROM suppliers) as suppliers_total,
                (SELECT COUNT(*) FROM products WHERE is_available = true) as products_in_stock,
                (SELECT COUNT(*) FROM products) as products_total,
                (SELECT COUNT(*) FROM categories) as categories_total,
                (SELECT COUNT(*) FROM subcategories) as subcategories_total,
                (SELECT COUNT(*) FROM sub_subcategories) as sub_subcategories_total,
                (SELECT COUNT(*) FROM brands) as brands_total,
                (SELECT COUNT(*) FROM promotions WHERE is_active = true AND (end_date IS NULL OR end_date >= NOW())) as active_promotions,
                (SELECT COUNT(*) FROM discounts WHERE is_active = true AND (end_at IS NULL OR end_at >= NOW())) as active_discounts,
                (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
                (SELECT COUNT(*) FROM reviews) as reviews_total,
                (SELECT COUNT(*) FROM users WHERE is_active = true) as users_active,
                (SELECT COUNT(*) FROM inventories WHERE quantity < 10) as low_stock
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
            'pending_orders' => (int) ($stats->pending_orders ?? 0),
            'reviews_total' => (int) ($stats->reviews_total ?? 0),
            'users_active' => (int) ($stats->users_active ?? 0),
            'low_stock' => (int) ($stats->low_stock ?? 0),
        ];
    }
}