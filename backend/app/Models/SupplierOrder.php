<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierOrderItem::class);
    }

    public function customerOrders()
    {
        return $this->belongsToMany(Order::class, 'supplier_order_items', 'supplier_order_id', 'customer_order_id')
            ->withPivot(['product_id', 'quantity', 'unit_cost'])
            ->withTimestamps();
    }

    /**
     * Получить данные заказа
     */
    public function getAllData(): array
    {
        $key = "supplier_order.{$this->id}.all";
        
        return Cache::remember($key, 300, function () {
            return [
                'id' => $this->id,
                'supplier_id' => $this->supplier_id,
                'supplier_name' => $this->supplier?->name,
                'status' => $this->status,
                'status_name' => $this->getStatusName(),
                'scheduled_date' => $this->scheduled_date?->format('d.m.Y'),
                'delivery_date' => $this->delivery_date?->format('d.m.Y'),
                'total_quantity' => $this->total_quantity,
                'total_amount' => (float) $this->total_amount,
                'customer_orders_count' => $this->customer_orders_count,
            ];
        });
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

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "supplier_order.{$this->id}.all",
            "supplier.{$this->supplier_id}.orders",
        ];
    }

    /**
     * Очистка кеша
     */
    public function clearCache(): void
    {
        foreach ($this->getCacheKeys() as $key) {
            Cache::forget($key);
        }
    }

    /**
     * События модели
     */
    protected static function booted()
    {
        static::saved(function ($supplierOrder) {
            $supplierOrder->clearCache();
        });

        static::deleted(function ($supplierOrder) {
            $supplierOrder->clearCache();
        });
    }

    public function canAcceptMoreOrders(): bool       //??
    {
        return $this->status === self::STATUS_CONSOLIDATING && 
               $this->scheduled_date > now();
    }
}