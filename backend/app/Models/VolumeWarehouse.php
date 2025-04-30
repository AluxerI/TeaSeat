<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolumeWarehouse extends Model
{
    protected $table = 'volumewarehouse';

    public function product()
    {
        return $this->belongsTo(Products::class, 'idproduct');
    }
}
