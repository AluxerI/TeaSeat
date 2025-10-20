<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

     protected $fillable = [
        'name', 'city', 'location', 'is_active',
        'is_supplier', 'supplier_name', 'supplier_contact',
        'order_schedule', 'lead_time_days', 'min_order_quantity', 
        'consolidation_period'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_supplier' => 'boolean',
        'order_schedule' => 'array',
        'lead_time_days' => 'integer',
        'min_order_quantity' => 'integer',
        'consolidation_period' => 'integer'
    ];

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

    public function supplierOrders()
    {
        return $this->hasMany(SupplierOrder::class, 'supplier_id');
    }

    /**
     * Scope для поставщиков
     */
    public function scopeSuppliers($query)
    {
        return $query->where('is_supplier', true);
    }

    /**
     * Scope для обычных складов
     */
    public function scopeRegularWarehouses($query)
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

    /**
     * Инвентарь на складе
     */
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Товары на складе
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'inventories')
            ->withPivot('quantity', 'last_restock_date');
    }
}
