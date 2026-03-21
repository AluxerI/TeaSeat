<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'city', 'location', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'inventories')
            ->withPivot('quantity', 'last_restock_date');
    }

    public function supplierOrders()
    {
        return $this->hasMany(SupplierOrder::class, 'supplier_id');
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
                'is_active' => $this->is_active,
                'inventories_count' => $this->inventories()->count(),
                'total_quantity' => $this->inventories()->sum('quantity'),
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
            ];
        });
    }
    
    protected function getCacheKeys(): array
    {
        return [
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
        });

        static::deleted(function ($warehouse) {
            $warehouse->clearCache();
        });
    }
    public function getNextOrderDate(): \Carbon\Carbon
    {
        $today = now();
        $schedule = $this->order_schedule ?? ['days' => [8, 18, 28], 'type' => 'monthly'];
        
        foreach ($schedule['days'] as $day) {
            $nextDate = $today->copy()->day($day);
            if ($nextDate->gte($today)) {
                return $nextDate;
            }
        }
        
        return $today->copy()->addMonth()->day($schedule['days'][0]);
    }

    public function activeSupplierOrders()
    {
        return $this->hasMany(SupplierOrder::class)
            ->where('status', SupplierOrder::STATUS_CONSOLIDATING)
            ->where('scheduled_date', '>=', now());
    }

    /**
     * Scope для поставщиков
     */
    public function scopeSuppliers($query)
    {
        return $query->where('is_supplier', true);
    }

    public function scopePhysicalWarehouses($query)
    {
        return $query->where('is_supplier', false);
    }
    /**
     * Scope для активных складов
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    

    /**
     * Scope для складов в определенном городе
     */
    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

}