<?php

namespace App\Traits;

use App\Services\AdminBadgeService;

trait HasNavigationBadge
{
    public static function getNavigationBadge(): ?string
    {
        $resource = class_basename(static::class);
        
        $map = [
            'BrandResource' => 'brand',
            'ProductResource' => 'product',
            'CategoryResource' => 'category',
            'SubcategoryResource' => 'subcategory',
            'SubSubcategoryResource' => 'sub_subcategory',
            'OrderResource' => 'order',
            'PromotionResource' => 'promotion',
            'DiscountResource' => 'discount',
            'ReviewResource' => 'review',
            'InventoryResource' => 'inventory',
            'WarehouseResource' => 'warehouse',
        ];

        $key = $map[$resource] ?? null;
        
        if (!$key) {
            return null;
        }

        return AdminBadgeService::getNavigationBadge($key);
    }
}