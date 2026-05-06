<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Cache;
use App\Traits\ResetsAdminBadges;
use App\Services\AdminBadgeService;


class Order extends Model
{
    use HasFactory, SoftDeletes, ResetsAdminBadges;

    protected $fillable = [
        'user_id',
        'contact_name',     
        'contact_phone',      
        'contact_email',
        'status',
        'products_total',
        'promotion_discount',
        'personal_discount',
        'cart_discount',
        'shipping_cost',
        'final_total',
        'shipping_address_id',
        'warehouse_id',
        'discount_id',
        'applied_promotion_code',
        'delivery_method_id',
        'payment_method',
        'tracking_number',
        'customer_notes',
        'internal_notes',
        'confirmed_at',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'parent_order_id',
        'supplier_order_id',  'supplier_order_id', // связь с консолидированным заказом
        'is_supplier_order'
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
        'is_supplier_order' => 'boolean'
    ];

    // Статусы заказа
    const STATUS_CART = 'cart';
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    // Способы оплаты
    const PAYMENT_CASH = 'cash';
    const PAYMENT_CARD = 'card';
    const PAYMENT_ONLINE = 'online';

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

    public function deliveryMethod()
    {
        return $this->belongsTo(DeliveryMethod::class, 'delivery_method_id'); 
    }
    /**
     * Получить данные заказа для отображения
     */
    public function getAllData(): array
    {
        $key = "order.{$this->id}.all";
        
        return Cache::remember($key, 300, function () {
            return [
                'id' => $this->id,
                'order_number' => $this->order_number,
                'user_id' => $this->user_id,
                'user_name' => $this->user?->name,
                'user_email' => $this->user?->email,
                'status' => $this->status,
                'status_name' => $this->status_name,
                'final_total' => (float) $this->final_total,
                'items_count' => $this->items()->count(),
                'created_at' => $this->created_at?->format('d.m.Y H:i'),
                'is_supplier_order' => $this->is_supplier_order,
            ];
        });
    }

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "order.{$this->id}.all",
            "user.{$this->user_id}.orders",
        ];
    }

    /**
     * События модели
     */
    protected static function booted()
    {
        static::saved(function ($order) {
            Cache::forget("order.{$order->id}.all");
            Cache::forget("user.{$order->user_id}.orders");
            AdminBadgeService::clearCache();
        });
    
        static::deleted(function ($order) {
            Cache::forget("order.{$order->id}.all");
            Cache::forget("user.{$order->user_id}.orders");
            AdminBadgeService::clearCache();
        });
    }

        /**
     * Проверить, можно ли оформить заказ
     */
    public function canBeCheckedOut(): bool
    {
        return $this->status === self::STATUS_CART && 
               $this->items->isNotEmpty() &&
               $this->final_total > 0;
    }

    /**
     * Scope для заказов (исключая корзины)
     */
    public function scopeRealOrders($query)
    {
        return $query->where('status', '!=', self::STATUS_CART);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }

    public function isCart(): bool
    {
        return $this->status === self::STATUS_CART;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function partialOrders()
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }
    
    public function parentOrder()
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

     /**
     * Accessor для номера заказа
     */
    public function getOrderNumberAttribute(): string
    {
        return 'TE-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Accessor для названия статуса
     */
    public function getStatusNameAttribute(): string
    {
        $statuses = [
            self::STATUS_CART => 'Корзина',
            self::STATUS_PENDING => 'Ожидает подтверждения',
            self::STATUS_CONFIRMED => 'Подтвержден',
            self::STATUS_PROCESSING => 'Обрабатывается',
            self::STATUS_SHIPPED => 'Отправлен',
            self::STATUS_DELIVERED => 'Доставлен',
            self::STATUS_CANCELLED => 'Отменен',
        ];

        return $statuses[$this->status] ?? 'Неизвестно';
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


    public function supplierOrder()
    {
        return $this->belongsTo(SupplierOrder::class, 'supplier_order_id');
    }

    public function supplierOrderItems()
    {
        return $this->hasMany(SupplierOrderItem::class, 'customer_order_id');
    }

    public function isSupplierOrder(): bool
    {
        return $this->is_supplier_order || $this->supplier_order_id !== null;
    }
     /**
     * История смены статусов
     */
    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Получить название статуса на русском
     */
    public static function getStatusName(?string $status = null): string|array
    {
        $statuses = [
            self::STATUS_CART => 'Корзина',
            self::STATUS_PENDING => 'Ожидает подтверждения',
            self::STATUS_CONFIRMED => 'Подтвержден',
            self::STATUS_PROCESSING => 'В обработке',
            self::STATUS_SHIPPED => 'Отправлен',
            self::STATUS_DELIVERED => 'Доставлен',
            self::STATUS_CANCELLED => 'Отменен',
        ];
    
        if ($status === null) {
            return $statuses;
        }
    
        return $statuses[$status] ?? $status;
    }

    /**
     * Пересчитать общую сумму товаров на основе сохраненных цен в order_products
     */
    public function recalculateProductsTotal(): void
    {
        $total = $this->items()->sum('total_price');
        $this->updateQuietly(['products_total' => $total]);
    }
    
}
