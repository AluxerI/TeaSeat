<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Traits\ClearsModelCache;

class Supplier extends Model
{
    use HasFactory, ClearsModelCache;

    protected $fillable = [
        'name', 
        'contact_person', 
        'email', 
        'phone', 
        'address',
        'city',           // ← добавить
        'order_schedule', 
        'lead_time_days', 
        'min_order_quantity',
        'consolidation_period', 
        'is_active'
    ];

    protected $casts = [
        'order_schedule' => 'array',  // ← для KeyValue поля
        'is_active' => 'boolean'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            ->withPivot(['cost_price', 'lead_time_days', 'min_order_quantity', 'is_active'])
            ->withTimestamps();
    }

    public function supplierOrders()
    {
        return $this->hasMany(SupplierOrder::class);
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

    public function getAllData(): array
    {
        $key = "supplier.{$this->id}.all";
        
        return Cache::remember($key, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'contact_person' => $this->contact_person,
                'email' => $this->email,
                'phone' => $this->phone,
                'city' => $this->city,
                'is_active' => $this->is_active,
                'products_count' => $this->products()->count(),
                'orders_count' => $this->supplierOrders()->count(),
                'lead_time_days' => $this->lead_time_days,
                'created_at' => $this->created_at?->format('d.m.Y'),
            ];
        });
    }

    protected function getCacheKeys(): array
    {
        return [
            "supplier.{$this->id}.all",
        ];
    }
}