<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sub_subcategory extends Model
{
    use HasFactory;
    // Связь с подкатегорией (родителем)
    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    // Связь с продуктами (многие-ко-многим через промежуточную таблицу)
    public function products()
    {
        return $this->belongsToMany(Product::class, 'sub_subcategory_products');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id', 'subcategory');
    }
}
