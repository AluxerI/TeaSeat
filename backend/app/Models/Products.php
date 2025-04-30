<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    protected $table = 'products';
    public function nameProduct()
    {
        return $this->hasOne(NameProduct::class, 'id');
    }

    public function VolumeWarehouse()
    {
        return $this->hasOne(VolumeWarehouse::class, 'idproduct');
    }
}


