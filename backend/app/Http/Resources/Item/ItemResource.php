<?php

namespace App\Http\Resources\Item;

use App\Services\PriceCalculatorService;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request)
    {
        $priceData = app(PriceCalculatorService::class)
            ->calculateForProduct($this->resource);

        // Основная информация о товаре
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ingredients' => $this->ingredients,
            'description' => $this->description,
            'weight_grams' => $this->weight_grams,
            
            // Цены и скидки
            'price' => $this->price,
            'pricing' => $priceData,
            
            // Информация о бренде
            'brand' => $this->brand->name ?? null,
            
            // Информация о категориях (через подкатегории)
            'category' => $this->getCategoryPath(),
            
            // Данные о наличии на складах
            'inventory' => $this->getInventoryData(),
            'total_quantity' => $this->inventories->sum('quantity'),
            
            // Акции и скидки
            'promotions' => $this->getPromotionsData(),
            'available_discounts' => $this->getDiscountsData(),
        ];
    }

     /**
     * Формирует путь категории для товара.
     */
    protected function getCategoryPath()
    {
        // Получаем первую связанную подкатегорию (если есть)
        $subSubcategory = $this->sub_subcategories->first();
        
        if (!$subSubcategory) {
            return null;
        }
        
        // Возвращаем информацию о категории, подкатегории и подподкатегории
        return [
            'category' => $subSubcategory->subcategory->category->name ?? null,
            'subcategory' => $subSubcategory->subcategory->name ?? null,
            'sub_subcategory' => $subSubcategory->name,
        ];
    }

    protected function getInventoryData()
    {
        return $this->inventories->map(function ($inventory) {
            return [
                'warehouse_id' => $inventory->warehouse_id,
                'warehouse_name' => $inventory->warehouse->name,
                'quantity' => $inventory->quantity,
                'last_restock_date' => $inventory->last_restock_date,
            ];
        });
    }
    /**
     * Данные об акциях на товар
     */
    protected function getPromotionsData()
    {
        return $this->promotions->map(function ($promotion) {
            return [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'description' => $promotion->description,
                'discount_percent' => $promotion->discount_percent,
                'start_date' => $promotion->start_date,
                'end_date' => $promotion->end_date,
            ];
        });
    }

    /**
     * Данные о доступных скидках
     */
    protected function getDiscountsData()
    {
        return $this->discounts->map(function ($discount) {
            return [
                'id' => $discount->id,
                'name' => $discount->name,
                'code' => $discount->code,
                'type' => $discount->type,
                'value' => $discount->value,
                'start_at' => $discount->start_at,
                'end_at' => $discount->end_at,
            ];
        });
    }
}