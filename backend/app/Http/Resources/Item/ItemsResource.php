<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemsResource extends JsonResource
{
    public function toArray($request)
    {
        $products = $this['products'];
        
        return [
            'city' => $this['city'] ?? null,
            'meta' => [
                'total_products' => $this['total_products'],
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total_pages' => $products->lastPage(),
            ],
            
            'filters' => [
                'categories' => $this['categories']->map(function($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->category,
                        'subcategories' => $category->subcategories->map(function($subcategory) {
                            return [
                                'id' => $subcategory->id,
                                'name' => $subcategory->subcategory,
                                'sub_subcategories' => $subcategory->sub_subcategories->map(function($subsub) {
                                    return [
                                        'id' => $subsub->id,
                                        'name' => $subsub->sub_subcategory,
                                        'products_count' => $subsub->available_products_count
                                    ];
                                })
                            ];
                        })
                    ];
                })
            ],
            
            'products' => $products->map(function($item) {
                $product = $item['product'];
                $availability = $item['availability'];
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'image' => $product->image,
                    'brand' => $product->brand->name,
                    
                    // Информация о доступности
                    'availability' => [
                        'type' => $availability['availability_type'],
                        'description' => $availability['availability_description'],
                        'is_available' => $availability['is_available'],
                        'delivery_timeline' => $availability['delivery_timeline'],
                        
                        // Детали
                        'local_quantity' => $availability['local_quantity'],
                        'is_available_locally' => $availability['is_available_locally'],
                        'is_available_from_supplier' => $availability['is_available_from_supplier'],
                        
                        // Для фронтенда - флаги для UI
                        'can_add_to_cart' => $availability['is_available'],
                        'requires_supplier_order' => !$availability['is_available_locally'] && $availability['is_available_from_supplier'],
                        'show_supplier_badge' => !$availability['is_available_locally'] && $availability['is_available_from_supplier']
                    ],
                    
                    // Дополнительная информация для поставщиков
                    'supplier_info' => $availability['supplier_info'] ? [
                        'supplier_name' => $availability['supplier_info']['supplier_name'],
                        'estimated_delivery' => $availability['supplier_info']['estimated_delivery'],
                        'lead_time_days' => $availability['supplier_info']['lead_time_days']
                    ] : null
                ];
            })
        ];
    }
}