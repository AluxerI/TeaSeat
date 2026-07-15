<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderGift extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'gift_id', 'client_instance_id', 'gift_version', 'name',
        'description', 'quantity', 'markup_unit_amount', 'markup_total_amount',
        'components_base_total', 'components_discount_amount', 'total_price',
        'layout_snapshot',
    ];

    protected $casts = [
        'gift_version' => 'integer',
        'quantity' => 'integer',
        'markup_unit_amount' => 'decimal:2',
        'markup_total_amount' => 'decimal:2',
        'components_base_total' => 'decimal:2',
        'components_discount_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'layout_snapshot' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }

    public function items()
    {
        return $this->hasMany(OrderProduct::class)->orderBy('gift_item_sort_order');
    }
}
