<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use App\Traits\ClearsModelCache;

class Inventory extends Model
{
    use HasFactory, ClearsModelCache; 

    public const ONLINE_AVAILABLE_EXPRESSION =
        'GREATEST(quantity - reserved_online_quantity - reserved_seller_quantity, 0)';
    
    protected $table = 'inventories';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;  // 👈 ИСПРАВЛЕНО: должно быть true
    public $timestamps = true; 

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_online_quantity',
        'reserved_seller_quantity',
        'last_restock_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_online_quantity' => 'integer',
        'reserved_seller_quantity' => 'integer',
        'last_restock_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalQuantity(): int
    {
        return (int) $this->quantity;
    }

    public function availableQuantity(): int
    {
        return max(
            0,
            (int) $this->quantity
                - (int) $this->reserved_online_quantity
                - (int) $this->reserved_seller_quantity
        );
    }

    public function shortageQuantity(): int
    {
        return max(
            0,
            (int) $this->reserved_online_quantity
                + (int) $this->reserved_seller_quantity
                - (int) $this->quantity
        );
    }

    public function scopeOnlineFulfillment(Builder $query): Builder
    {
        return $query->whereHas('warehouse', fn (Builder $warehouseQuery) =>
            $warehouseQuery
                ->where('is_active', true)
                ->where('is_online_fulfillment_enabled', true)
        );
    }

    public function scopeAvailableForOnline(Builder $query): Builder
    {
        return $query
            ->onlineFulfillment()
            ->whereRaw(self::ONLINE_AVAILABLE_EXPRESSION . ' > 0');
    }

    public static function sumOnlineAvailable(Builder|Relation $query): int
    {
        $aggregateQuery = clone ($query instanceof Relation
            ? $query->getQuery()
            : $query);

        return (int) $aggregateQuery
            ->reorder()
            ->select([])
            ->selectRaw('COALESCE(SUM(' . self::ONLINE_AVAILABLE_EXPRESSION . '), 0) AS aggregate')
            ->value('aggregate');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Получить данные для отображения
     */
    public function getAllData(): array
    {
        $key = "inventory.{$this->id}.all";  // 👈 ИСПРАВЛЕНО: используем id, а не составной ключ
        
        return Cache::remember($key, 300, function () {
            return [
                'id' => $this->id,
                'product_id' => $this->product_id,
                'product_name' => $this->product?->name,
                'product_price' => $this->product?->price,
                'warehouse_id' => $this->warehouse_id,
                'warehouse_name' => $this->warehouse?->name,
                'warehouse_city' => $this->warehouse?->city,
                'warehouse_type' => $this->warehouse?->type,
                'is_online_fulfillment_enabled' =>
                    $this->warehouse?->is_online_fulfillment_enabled ?? false,
                'quantity' => $this->quantity,
                'reserved_online_quantity' => $this->reserved_online_quantity,
                'reserved_seller_quantity' => $this->reserved_seller_quantity,
                'available_quantity' => $this->availableQuantity(),
                'shortage_quantity' => $this->shortageQuantity(),
                'last_restock_date' => $this->last_restock_date,
                'is_low_stock' => $this->availableQuantity() < 10,
                'is_out_of_stock' => $this->availableQuantity() <= 0,
            ];
        });
    }

    /**
     * 👈 УДАЛИТЬ этот метод - он больше не нужен
     */
    // public function getInventoryKeyAttribute(): string
    // {
    //     return $this->product_id . '-' . $this->warehouse_id;
    // }

    /**
     * 👈 УДАЛИТЬ этот метод - он больше не нужен
     */
    // public function resolveRouteBinding($value, $field = null)
    // {
    //     $parts = explode('-', $value);
    //     if (count($parts) === 2) {
    //         return $this->where('product_id', $parts[0])
    //                     ->where('warehouse_id', $parts[1])
    //                     ->first();
    //     }
    //     
    //     return parent::resolveRouteBinding($value, $field);
    // }

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "inventory.{$this->id}.all",
            "product.{$this->product_id}.total_quantity",
            "product.{$this->product_id}.all",
            "product_{$this->product_id}_total_quantity",
            "product_{$this->product_id}_available_cities",
            "product_{$this->product_id}_city_{$this->warehouse?->city}_quantity",
        ];
    }

    /**
     * События модели
     */
    protected static function booted()
    {
        static::saved(function ($inventory) {
            $inventory->clearCache();
            if ($inventory->product) {
                $inventory->product->clearCache();
                $inventory->product->updateCacheFields();
            }
        });

        static::deleted(function ($inventory) {
            $inventory->clearCache();
            if ($inventory->product) {
                $inventory->product->clearCache();
                $inventory->product->updateCacheFields();
            }
        });
    }
}
