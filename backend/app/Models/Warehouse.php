<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Warehouse extends Model
{
    use HasFactory;

    public const TYPE_WAREHOUSE = 'warehouse';
    public const TYPE_STORE = 'store';

    protected $fillable = [
        'name',
        'city',
        'location',
        'type',
        'is_active',
        'is_online_fulfillment_enabled',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online_fulfillment_enabled' => 'boolean',
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_warehouse')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function activeUsers()
    {
        return $this->users()->wherePivot('is_active', true);
    }

    public function staffDevices()
    {
        return $this->hasMany(StaffDevice::class, 'last_warehouse_id');
    }

    public function fulfillmentIssues()
    {
        return $this->hasMany(FulfillmentIssue::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'inventories')
            ->withPivot(
                'quantity',
                'reserved_online_quantity',
                'reserved_seller_quantity',
                'last_restock_date'
            );
    }

    /**
     * Получить данные склада
     */
    public function getAllData(): array
    {
        $key = "warehouse.{$this->id}.all";
        
        return Cache::remember($key, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'city' => $this->city,
                'type' => $this->type,
                'is_active' => $this->is_active,
                'is_online_fulfillment_enabled' => $this->is_online_fulfillment_enabled,
                'inventories_count' => $this->inventories()->count(),
                'total_quantity' => $this->inventories()->sum('quantity'),
                'available_quantity' => Inventory::sumOnlineAvailable(
                    $this->inventories()->onlineFulfillment()
                ),
                'created_at' => $this->created_at?->format('d.m.Y'),
            ];
        });
    }

    /**
     * Ключи кеша для очистки
     */
    public function getStats(): array
    {
        $key = "warehouse.{$this->id}.stats";
        
        return Cache::remember($key, 300, function () {
            return [
                'inventories_count' => $this->inventories()->count(),
                'total_quantity' => $this->inventories()->sum('quantity'),
                'available_quantity' => Inventory::sumOnlineAvailable(
                    $this->inventories()->onlineFulfillment()
                ),
            ];
        });
    }
    
    protected function getCacheKeys(): array
    {
        return [
            "warehouse.{$this->id}.all",
            "warehouse.{$this->id}.stats",
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
        static::saved(function ($warehouse) {
            $warehouse->clearCache();
            Cache::forget('available_cities');

            if ($warehouse->wasChanged([
                'city',
                'is_active',
                'is_online_fulfillment_enabled',
            ])) {
                $warehouse->refreshInventoryProductCaches(
                    $warehouse->getOriginal('city')
                );
            }
        });

        static::deleting(function ($warehouse) {
            $warehouse->setRelation(
                'productsForCacheInvalidation',
                $warehouse->products()->get()
            );
        });

        static::deleted(function ($warehouse) {
            $warehouse->clearCache();
            Cache::forget('available_cities');

            if (!$warehouse->relationLoaded('productsForCacheInvalidation')) {
                return;
            }

            $warehouse->getRelation('productsForCacheInvalidation')
                ->each(function (Product $product) use ($warehouse) {
                    $warehouse->forgetProductLocationCaches(
                        $product,
                        $warehouse->city
                    );
                    $product->updateCacheFields();
                });
        });
    }

    private function refreshInventoryProductCaches(?string $oldCity): void
    {
        $this->products()->get()->each(function (Product $product) use ($oldCity) {
            $this->forgetProductLocationCaches($product, $oldCity);
            $this->forgetProductLocationCaches($product, $this->city);
            $product->updateCacheFields();
        });
    }

    private function forgetProductLocationCaches(Product $product, ?string $city): void
    {
        Cache::forget("product_{$product->id}_available_cities");

        if ($city) {
            Cache::forget("product_{$product->id}_city_{$city}_quantity");
        }
    }
    /**
     * Scope для активных складов
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnlineFulfillment($query)
    {
        return $query
            ->where('is_active', true)
            ->where('is_online_fulfillment_enabled', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
    

    /**
     * Scope для складов в определенном городе
     */
    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

}
