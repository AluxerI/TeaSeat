<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_OPENING_BALANCE = 'opening_balance';
    public const TYPE_ONLINE_RESERVE = 'online_reserve';
    public const TYPE_ONLINE_RELEASE = 'online_release';
    public const TYPE_ONLINE_SALE = 'online_sale';
    public const TYPE_ONLINE_RETURN = 'online_return';
    public const TYPE_SELLER_RESERVE = 'seller_reserve';
    public const TYPE_SELLER_RELEASE = 'seller_release';
    public const TYPE_SELLER_SALE = 'seller_sale';
    public const TYPE_RESTOCK = 'restock';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';

    protected $fillable = [
        'inventory_id',
        'product_id',
        'warehouse_id',
        'order_id',
        'actor_id',
        'type',
        'physical_delta',
        'reserved_online_delta',
        'reserved_seller_delta',
        'physical_before',
        'physical_after',
        'reserved_online_before',
        'reserved_online_after',
        'reserved_seller_before',
        'reserved_seller_after',
        'reason',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'physical_delta' => 'integer',
        'reserved_online_delta' => 'integer',
        'reserved_seller_delta' => 'integer',
        'physical_before' => 'integer',
        'physical_after' => 'integer',
        'reserved_online_before' => 'integer',
        'reserved_online_after' => 'integer',
        'reserved_seller_before' => 'integer',
        'reserved_seller_after' => 'integer',
        'metadata' => 'array',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
