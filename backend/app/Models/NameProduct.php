<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NameProduct extends Model
{
    protected $table = 'nameproduct';

    public function product()
    {
        return $this->belongsTo(Products::class, 'id');
    }
}
