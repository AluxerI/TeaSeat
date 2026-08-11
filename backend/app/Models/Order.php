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
        'sales_channel',
        'contact_name',     
        'contact_phone',      
        'contact_email',
        'status',
        'products_total',
        'promotion_discount',
        'personal_discount',
        'cart_discount',
        'shipping_cost',
        'shipping_discount',
        'final_total',
        'pricing_snapshot',
        'shipping_address_id',
        'warehouse_id',
        'destination_warehouse_id',
        'picker_id',
        'courier_id',
        'discount_id',
        'applied_promotion_code',
        'delivery_method_id',
        'delivery_time_slot_id',
        'scheduled_delivery_date',
        'delivery_time_from',
        'delivery_time_to',
        'payment_method',
        'tracking_number',
        'customer_notes',
        'internal_notes',
        'confirmed_at',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'stock_reserved_at',
        'stock_committed_at',
        'stock_released_at',
        'picking_started_at',
        'ready_for_delivery_at',
        'courier_assigned_at',
        'courier_arrived_at',
        'received_at',
        'discount_usage_released_at',
        'parent_order_id',
        'supplier_order_id', // связь с консолидированным заказом
        'is_supplier_order',
        'checkout_idempotency_key',
        'client_order_id',
        'seller_revision',
        'last_payload_hash',
        'seller_device_id',
        'was_edited',
        'seller_occurred_at',
        'seller_synced_at',
        'seller_reviewed_at',
        'seller_escalated_at',
        'seller_completed_at',
        'seller_discount_usage_consumed_at',
    ];

    protected $casts = [
        'products_total' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
        'personal_discount' => 'decimal:2',
        'cart_discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_discount' => 'decimal:2',
        'final_total' => 'decimal:2',
        'pricing_snapshot' => 'array',
        'scheduled_delivery_date' => 'date',
        'confirmed_at' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'stock_reserved_at' => 'datetime',
        'stock_committed_at' => 'datetime',
        'stock_released_at' => 'datetime',
        'picking_started_at' => 'datetime',
        'ready_for_delivery_at' => 'datetime',
        'courier_assigned_at' => 'datetime',
        'courier_arrived_at' => 'datetime',
        'received_at' => 'datetime',
        'discount_usage_released_at' => 'datetime',
        'seller_revision' => 'integer',
        'was_edited' => 'boolean',
        'seller_occurred_at' => 'datetime',
        'seller_synced_at' => 'datetime',
        'seller_reviewed_at' => 'datetime',
        'seller_escalated_at' => 'datetime',
        'seller_completed_at' => 'datetime',
        'seller_discount_usage_consumed_at' => 'datetime',
        'is_supplier_order' => 'boolean'
    ];

    // Статусы заказа
    const STATUS_CART = 'cart';
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_AWAITING_RECEIPT = 'awaiting_receipt';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SELLER_REVIEW = 'seller_review';
    const STATUS_MANAGER_REVIEW = 'manager_review';
    const STATUS_COMPLETED = 'completed';

    const SALES_CHANNEL_ONLINE = 'online';
    const SALES_CHANNEL_SELLER = 'seller';
    const SALES_CHANNEL_INTERNAL = 'internal';

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

    public function gifts()
    {
        return $this->hasMany(OrderGift::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function feedback()
    {
        return $this->hasOne(OrderFeedback::class);
    }

    public function sellerDevice()
    {
        return $this->belongsTo(StaffDevice::class, 'seller_device_id');
    }

    public function fulfillmentIssues()
    {
        return $this->hasMany(FulfillmentIssue::class, 'source_order_id');
    }

    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function picker()
    {
        return $this->belongsTo(User::class, 'picker_id');
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function shippingAddress()
    {
        return $this->belongsTo(AddressClient::class, 'shipping_address_id');
    }

    public function deliveryMethod()
    {
        return $this->belongsTo(DeliveryMethod::class, 'delivery_method_id'); 
    }

    public function deliveryTimeSlot()
    {
        return $this->belongsTo(DeliveryTimeSlot::class);
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
        static::deleting(function (Order $order) {
            if ($order->sales_channel === self::SALES_CHANNEL_SELLER) {
                throw new \DomainException(
                    'Продажу продавца нельзя удалить: она является складским документом'
                );
            }
        });

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
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
            self::STATUS_COMPLETED,
        ], true);
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
            self::STATUS_READY_FOR_DELIVERY => 'Готов к передаче',
            self::STATUS_SHIPPED => 'Отправлен',
            self::STATUS_AWAITING_RECEIPT => 'Ожидает приёмки',
            self::STATUS_DELIVERED => 'Доставлен',
            self::STATUS_CANCELLED => 'Отменен',
            self::STATUS_SELLER_REVIEW => 'Требует проверки продавца',
            self::STATUS_MANAGER_REVIEW => 'Передан менеджеру',
            self::STATUS_COMPLETED => 'Завершен',
        ];

        return $statuses[$this->status] ?? 'Неизвестно';
    }


    public function canBeCancelled(): bool
    {
        $statusAllowsCancellation = in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING,
            self::STATUS_SELLER_REVIEW,
        ], true);

        if (!$statusAllowsCancellation || $this->parent_order_id) {
            return $statusAllowsCancellation;
        }

        return !$this->hasStartedPhysicalFulfillment();
    }

    public function canBeCancelledByManager(): bool
    {
        if ($this->status !== self::STATUS_MANAGER_REVIEW) {
            return $this->canBeCancelled();
        }

        if ($this->parent_order_id) {
            return true;
        }

        return !$this->hasStartedPhysicalFulfillment();
    }

    private function hasStartedPhysicalFulfillment(): bool
    {
        // После начала физической сборки отмена требует отдельного решения
        // менеджера: часть товара уже может быть упакована или перемещаться.
        return $this->partialOrders()
            ->where(function ($query): void {
                $query->whereNotNull('stock_committed_at')
                    ->orWhereIn('status', [
                        self::STATUS_PROCESSING,
                        self::STATUS_READY_FOR_DELIVERY,
                        self::STATUS_SHIPPED,
                        self::STATUS_AWAITING_RECEIPT,
                        self::STATUS_DELIVERED,
                    ]);
            })
            ->exists();
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
            self::STATUS_READY_FOR_DELIVERY => 'Готов к передаче',
            self::STATUS_SHIPPED => 'Отправлен',
            self::STATUS_AWAITING_RECEIPT => 'Ожидает приёмки',
            self::STATUS_DELIVERED => 'Доставлен',
            self::STATUS_CANCELLED => 'Отменен',
            self::STATUS_SELLER_REVIEW => 'Требует проверки продавца',
            self::STATUS_MANAGER_REVIEW => 'Передан менеджеру',
            self::STATUS_COMPLETED => 'Завершен',
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
        $total = (float) $this->items()->sum('total_price')
            + (float) $this->gifts()->sum('markup_total_amount');
        $this->updateQuietly(['products_total' => $total]);
    }

    public function isWarehouseTransfer(): bool
    {
        return $this->parent_order_id !== null
            && $this->destination_warehouse_id !== null
            && (int) $this->warehouse_id !== (int) $this->destination_warehouse_id;
    }
    
}
