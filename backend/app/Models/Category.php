<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            Sub_Subcategory::class,
            'subcategory_id', // Внешний ключ в под-подкатегориях
            'sub_subcategory_id', // Внешний ключ в продуктах
            'id', // Локальный ключ в категориях
            'id' // Локальный ключ в под-подкатегориях
        );
    }
}
