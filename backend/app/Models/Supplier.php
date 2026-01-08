<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'contact_person', 'email', 'phone', 'address',
        'order_schedule', 'lead_time_days', 'min_order_quantity',
        'consolidation_period', 'is_active'
    ];

    protected $casts = [
        'order_schedule' => 'array',
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
}