<?php

namespace App\Traits;

use App\Services\AdminBadgeService;

trait HasNavigationBadge
{
    public static function getNavigationBadge(): ?string
    {
        $resource = class_basename(static::class);
        
        $map = [
            // Управление каталогом
            'BrandResource' => 'brand',
            'ProductResource' => 'product',
            'CategoryResource' => 'category',
            'SubcategoryResource' => 'subcategory',
            'SubSubcategoryResource' => 'sub_subcategory',
            
            // Управление продажами
            'OrderResource' => 'order',
            
            // Маркетинг
            'PromotionResource' => 'promotion',
            'DiscountResource' => 'discount',
            
            // Модерация
            'ReviewResource' => 'review',
            
            // Склад и поставщики
            'InventoryResource' => 'inventory',
            'WarehouseResource' => 'warehouse',
            'SupplierResource' => 'supplier', // 👈 ДОБАВЛЕНО
            
            // Управление пользователями
            'UserResource' => 'user', // 👈 ДОБАВЛЕНО
        ];

        $key = $map[$resource] ?? null;
        
        if (!$key) {
            return null;
        }

        return AdminBadgeService::getNavigationBadge($key);
    }
}