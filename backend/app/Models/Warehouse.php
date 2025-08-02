<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;
    public function products()
    {
        return $this->belongsToMany(Inventory::class, 'inventories',  'warehouse_id', 'product_id');
    }
}
