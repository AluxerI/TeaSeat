<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagerOrderAdjustment extends Model
{
    use HasFactory;

    public const ACTION_QUANTITY_CHANGED = 'quantity_changed';
    public const ACTION_PRODUCT_ADDED = 'product_added';
    public const ACTION_PRODUCT_REPLACED = 'product_replaced';
    public const ACTION_PRODUCT_REMOVED = 'product_removed';
    public const ACTION_GIFT_REPLACED = 'gift_replaced';
    public const ACTION_GIFT_REMOVED = 'gift_removed';

    public $timestamps = false;

    protected $fillable = [
        'operation_id',
        'order_id',
        'manager_id',
        'fulfillment_issue_id',
        'action',
        'order_product_id',
        'order_gift_id',
        'reason',
        'before_snapshot',
        'after_snapshot',
        'created_at',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function fulfillmentIssue()
    {
        return $this->belongsTo(FulfillmentIssue::class);
    }
}
