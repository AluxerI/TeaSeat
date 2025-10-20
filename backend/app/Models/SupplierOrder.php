<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierOrder extends Model
{
    use HasFactory;

    const STATUS_CONSOLIDATING = 'consolidating';
    const STATUS_ORDERED = 'ordered';
    const STATUS_DELIVERED = 'delivered';

    protected $fillable = [
        'supplier_id',
        'scheduled_date',
        'delivery_date', 
        'status',
        'total_quantity',
        'total_amount',
        'customer_orders_count'
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'delivery_date' => 'date',
        'total_quantity' => 'integer',
        'total_amount' => 'decimal:2',
        'customer_orders_count' => 'integer'
    ];

    public function supplier()
    {
        return $this->belongsTo(Warehouse::class, 'supplier_id')->where('is_supplier', true);
    }

    public function customerOrders()
    {
        return $this->belongsToMany(Order::class, 'supplier_order_items', 'supplier_order_id', 'customer_order_id')
            ->withPivot(['product_id', 'quantity', 'unit_cost'])
            ->withTimestamps();
    }

    public function items()
    {
        return $this->hasMany(SupplierOrderItem::class);
    }

    public function canAcceptMoreOrders(): bool       //??
    {
        return $this->status === self::STATUS_CONSOLIDATING && 
               $this->scheduled_date > now();
    }

    public function getStatusName(): string
    {
        return match($this->status) {
            self::STATUS_CONSOLIDATING => 'Сбор заказов',
            self::STATUS_ORDERED => 'Заказан у поставщика',
            self::STATUS_DELIVERED => 'Поставка выполнена',
            default => 'Неизвестно'
        };
    }
}