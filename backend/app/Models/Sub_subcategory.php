<?php
// app/Models/Sub_Subcategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasIcon;

class Sub_Subcategory extends Model
{
    use HasFactory, HasIcon;

    protected $table = 'sub_subcategories';

    protected $fillable = ['subcategory_id', 'name', 'icon'];

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'sub_subcategory_products', 
            'sub_subcategory_id',    
            'product_id'          
        );
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id', 'subcategory');
    }

}