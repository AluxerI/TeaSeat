<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NameProduct extends Model
{
    use HasFactory;
    protected $table = 'nameproduct';
    public $timestamps = false;

     protected $fillable = ['nameitem', 'description', 'imageitem'];
     
    public function product()
    {
        return $this->hasOne(Products::class, 'idname', 'id');
    }
}
