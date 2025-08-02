<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    // public $timestamps = false;
    protected $table = 'products';
    protected $guarded = false;

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }
    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'product_id', 'id');
    }
}

