<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sub_subcategoryProduct extends Model
{
    protected $table = 'sub_subcategory_products';
    public $timestamps = false;
    public $incrementing = false;
    use HasFactory;
    protected $fillable = ['product_id', 'sub_subcategory_id', 'icon'];
}
