<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFeedback extends Model
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN = 'hidden';

    protected $table = 'order_feedback';

    protected $fillable = [
        'order_id', 'user_id', 'delivery_rating', 'packing_rating',
        'service_rating', 'comment', 'status', 'customer_edited_at',
    ];

    protected $casts = [
        'delivery_rating' => 'integer',
        'packing_rating' => 'integer',
        'service_rating' => 'integer',
        'customer_edited_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function moderationLogs()
    {
        return $this->hasMany(ContentModerationLog::class)->latest('created_at')->latest('id');
    }

    public function latestModeration()
    {
        return $this->hasOne(ContentModerationLog::class)->latestOfMany(['created_at', 'id']);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }
}
