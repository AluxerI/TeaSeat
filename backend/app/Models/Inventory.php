<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Traits\ClearsModelCache;

class Inventory extends Model
{
    use HasFactory, ClearsModelCache; 
    
    protected $table = 'inventories';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;  // 👈 ИСПРАВЛЕНО: должно быть true
    public $timestamps = true; 

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'last_restock_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
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
                'quantity' => $this->quantity,
                'last_restock_date' => $this->last_restock_date,
                'is_low_stock' => $this->quantity < 10,
                'is_out_of_stock' => $this->quantity <= 0,
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
            "inventory.{$this->id}.all",  // 👈 ИСПРАВЛЕНО
            "product.{$this->product_id}.total_quantity",
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
            }
        });

        static::deleted(function ($inventory) {
            $inventory->clearCache();
            if ($inventory->product) {
                $inventory->product->clearCache();
            }
        });
    }
}
