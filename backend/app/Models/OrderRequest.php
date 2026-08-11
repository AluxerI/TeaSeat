<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRequest extends Model
{
    use HasFactory;

    public const TYPE_CHANGE_DELIVERY = 'change_delivery';
    public const TYPE_CANCEL_ORDER = 'cancel_order';
    public const TYPE_ORDER_PROBLEM = 'order_problem';
    public const TYPE_OTHER = 'other';

    public const STATUS_WAITING = 'waiting';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const TYPES = [
        self::TYPE_CHANGE_DELIVERY,
        self::TYPE_CANCEL_ORDER,
        self::TYPE_ORDER_PROBLEM,
        self::TYPE_OTHER,
    ];

    public const OPEN_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_IN_REVIEW,
    ];

    protected $fillable = [
        'order_id',
        'user_id',
        'manager_id',
        'type',
        'message',
        'status',
        'manager_comment',
        'taken_at',
        'resolved_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'resolved_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
