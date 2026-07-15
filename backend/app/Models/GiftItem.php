<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'gift_id', 'product_size_id', 'client_item_id', 'position_x',
        'position_y', 'is_rotated', 'sort_order',
    ];

    protected $casts = [
        'position_x' => 'integer',
        'position_y' => 'integer',
        'is_rotated' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }

    public function productSize()
    {
        return $this->belongsTo(ProductSize::class);
    }
}
