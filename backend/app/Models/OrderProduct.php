<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'order_gift_id',
        'product_size_id',
        'gift_item_client_id',
        'gift_item_quantity',
        'gift_item_sort_order',
        'quantity',
        'stock_unit',
        'sale_step',
        'price_unit_quantity',
        'unit_price',
        'promotion_discount_id',
        'selected_discount_id',
        'promotion_discount_percent',  
        'personal_discount_percent',   
        'promotion_discount_amount',
        'selected_discount_amount',
        'final_unit_price',            
        'total_price',
        'pricing_snapshot',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'promotion_discount_percent' => 'decimal:2',
        'personal_discount_percent' => 'decimal:2',
        'promotion_discount_amount' => 'decimal:2',
        'selected_discount_amount' => 'decimal:2',
        'final_unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'pricing_snapshot' => 'array',
        'quantity' => 'integer',
        'sale_step' => 'integer',
        'price_unit_quantity' => 'integer',
        'gift_item_quantity' => 'integer',
        'gift_item_sort_order' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orderGift()
    {
        return $this->belongsTo(OrderGift::class);
    }

    public function productSize()
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function promotionDiscount()
    {
        return $this->belongsTo(Discount::class, 'promotion_discount_id');
    }

    public function selectedDiscount()
    {
        return $this->belongsTo(Discount::class, 'selected_discount_id');
    }

    // Автоматический расчет при сохранении
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->quantity && $model->final_unit_price && !$model->isDirty('total_price')) {
                $priceUnitQuantity = max(1, (int) ($model->price_unit_quantity ?: 1));
                $model->total_price = round(
                    $model->quantity * $model->final_unit_price / $priceUnitQuantity,
                    2
                );
            }
        });
    }
    protected static function booted()
    {
        static::saved(function ($orderProduct) {
            if ($orderProduct->order) {
                $orderProduct->order->recalculateProductsTotal();
            }
        });
    
        static::deleted(function ($orderProduct) {
            if ($orderProduct->order) {
                $orderProduct->order->recalculateProductsTotal();
            }
        });
    }
}
