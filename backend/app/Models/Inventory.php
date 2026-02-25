<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;
    protected $primaryKey = null; // Отключаем автоинкрементный id
    public $incrementing = false; // Важно для составного ключа

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    // Создаем виртуальный ключ для Filament
    public function getInventoryKeyAttribute(): string
    {
        return $this->product_id . '-' . $this->warehouse_id;
    }

    // Указываем, какой атрибут использовать как ключ для маршрутов
    public function getRouteKeyName()
    {
        return 'inventory_key';
    }

}
