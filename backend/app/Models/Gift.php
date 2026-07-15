<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gift extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'user_id', 'gift_size_profile_id', 'name', 'description', 'status',
        'visibility', 'markup_amount', 'version', 'layout_snapshot',
    ];

    protected $casts = [
        'markup_amount' => 'decimal:2',
        'version' => 'integer',
        'layout_snapshot' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sizeProfile()
    {
        return $this->belongsTo(GiftSizeProfile::class, 'gift_size_profile_id');
    }

    public function items()
    {
        return $this->hasMany(GiftItem::class)->orderBy('sort_order');
    }
}
