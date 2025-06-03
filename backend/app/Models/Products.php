<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'products';
    protected $guarded = false;
    public function nameProduct()
    {
        return $this->hasOne(NameProduct::class,  'id','idname');
    }

    public function VolumeWarehouse()
    {
        return $this->hasOne(VolumeWarehouse::class, 'idproduct');
    }

    public function category()
    {
        return $this->hasOne(Products::class, 'idcategory', 'id');
    }
}


