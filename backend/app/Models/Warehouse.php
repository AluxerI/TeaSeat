<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'location',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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

    // public function products()
    // {
    //     return $this->belongsToMany(Inventory::class, 'inventories',  'warehouse_id', 'product_id');
    // }
}
