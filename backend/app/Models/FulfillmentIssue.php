<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FulfillmentIssue extends Model
{
    use HasFactory;

    public const REASON_ONLINE_RESERVATION_CONFLICT = 'online_reservation_conflict';
    public const REASON_PHYSICAL_STOCK_DISCREPANCY = 'physical_stock_discrepancy';

    public const STATUS_WAITING = 'waiting';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'source_order_id',
        'product_id',
        'warehouse_id',
        'manager_id',
        'reason',
        'shortage_quantity',
        'reserved_online_before',
        'reserved_seller_before',
        'status',
    ];

    protected $casts = [
        'manager_id' => 'integer',
        'shortage_quantity' => 'integer',
        'reserved_online_before' => 'integer',
        'reserved_seller_before' => 'integer',
    ];

    public function sourceOrder()
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
