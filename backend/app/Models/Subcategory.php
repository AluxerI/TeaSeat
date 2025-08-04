<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    use HasFactory;
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Связь с под-подкатегориями (детьми)
    public function sub_subcategories()
    {
        return $this->hasMany(Sub_subcategory::class);
    }
}
