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
    //     // что-то для оптимизации -_-
    // protected $with = ['subSubcategory.subcategory.category', 'brand'];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }
    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'product_id', 'id');
    }

    public function sub_subcategories()
    {
        return $this->belongsToMany(Sub_subcategory::class, 'sub_subcategory_products');
    }

    // Удобный метод для доступа к первой под-подкатегории
    public function getMainSubSubcategoryAttribute()
    {
        return $this->sub_subcategories->first();
    }
}

