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
        'quantity',
        'unit_price',
        'promotion_discount_percent',  
        'personal_discount_percent',   
        'final_unit_price',            
        'total_price'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'promotion_discount_percent' => 'decimal:2',
        'personal_discount_percent' => 'decimal:2',
        'final_unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Автоматический расчет при сохранении
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->quantity && $model->final_unit_price) {
                $model->total_price = $model->quantity * $model->final_unit_price;
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