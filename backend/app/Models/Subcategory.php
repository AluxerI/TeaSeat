<?php
// app/Models/Subcategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasIcon;

class Subcategory extends Model
{
    use HasFactory, HasIcon;

    protected $fillable = ['category_id', 'name', 'icon'];

    protected $hidden = [
        'laravel_through_key',
        // другие служебные поля
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sub_subcategories()
    {
        return $this->hasMany(Sub_Subcategory::class);
    }
}