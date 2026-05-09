<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Item\ProductResource;

class CartResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_name' => $this->status_name,
            
            // Товары в корзине
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            
            // Итоговые суммы
            'products_total' => (float) $this->products_total,
            'promotion_discount' => (float) $this->promotion_discount,
            'personal_discount' => (float) $this->personal_discount,
            'cart_discount' => (float) ($this->cart_discount ?? 0),
            'shipping_cost' => (float) $this->shipping_cost,
            'final_total' => (float) $this->final_total,
            
            // Информация о доставке (если есть)
            'shipping_address' => $this->whenLoaded('shippingAddress', function() {
                return [
                    'id' => $this->shippingAddress->id,
                    'city' => $this->shippingAddress->city,
                    'street' => $this->shippingAddress->street,
                    'postal_code' => $this->shippingAddress->postal_code,
                    'full_address' => $this->shippingAddress->getFullAddress(),
                ];
            }),
            
            'delivery_method' => $this->whenLoaded('deliveryMethod', function() {
                return [
                    'id' => $this->deliveryMethod->id,
                    'name' => $this->deliveryMethod->name,
                    'cost' => (float) $this->deliveryMethod->cost,
                    'estimated_days' => $this->deliveryMethod->getEstimatedDaysFormatted(),
                ];
            }),
            
            // Флаги
            'is_supplier_order' => $this->is_supplier_order ?? false,
            'can_checkout' => $this->canBeCheckedOut(),
            
            // Даты
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'updated_at' => $this->updated_at?->format('d.m.Y H:i'),
        ];
    }
}

class CartItemResource extends JsonResource
{
    public function toArray($request)
    {
        $product = $this->whenLoaded('product');
        
        // Используем кешированные данные из модели Product
        $productData = $product ? $product->getAllData() : null;
        
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            
            // Информация о товаре (из кеша)
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image' => $productData['main_image_url'] ?? $product->main_image_url,
                'weight_grams' => $product->weight_grams,
                'is_available' => $productData['is_available'] ?? true,
            ] : null,
            
            // Цены с учётом скидок
            'unit_price' => (float) $this->unit_price,
            'promotion_discount_percent' => (float) ($this->promotion_discount_percent ?? 0),
            'personal_discount_percent' => (float) ($this->personal_discount_percent ?? 0),
            'final_unit_price' => (float) $this->final_unit_price,
            'total_price' => (float) $this->total_price,
            
            // Сумма сэкономленного
            'saved_amount' => (float) (($this->unit_price - $this->final_unit_price) * $this->quantity),
        ];
    }
}