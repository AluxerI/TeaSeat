<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'category';
    protected $guarded = false;
    public function product()
    {
        return $this->hasOne(Products::class, 'idcategory', 'id');
    }
}
