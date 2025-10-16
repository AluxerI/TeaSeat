<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'products_total',
        'promotion_discount',
        'personal_discount',
        'cart_discount',
        'shipping_cost',
        'final_total',
        'shipping_address_id',
        'warehouse_id',
        'promotion_id',
        'applied_promotion_code',
        'shipping_method',
        'tracking_number',
        'customer_notes',
        'internal_notes',
        'confirmed_at',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at'
    ];

    protected $casts = [
        'products_total' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
        'personal_discount' => 'decimal:2',
        'cart_discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'final_total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    const STATUS_CART = 'cart';
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function shippingAddress()
    {
        return $this->belongsTo(AddressClient::class, 'shipping_address_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function isCart(): bool
    {
        return $this->status === self::STATUS_CART;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PROCESSING]);
    }
    public function determineWarehouse($shippingAddress = null)
    {
        // Логика автоматического выбора склада
        // Например: ближайший склад к адресу доставки
        // Или склад с максимальным количеством товара
        
        if ($shippingAddress) {
            return Warehouse::nearestTo($shippingAddress->city)->first();
        }
        
        // Возвращаем склад по умолчанию
        return Warehouse::default()->first();
    }
}
