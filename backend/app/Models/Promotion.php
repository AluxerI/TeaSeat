<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Promotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'discount_percent',
        'is_active',
        'start_date',
        'end_date',
        'code',
        'type',
        'min_order_amount',
        'usage_limit',
        'used_count',
        'usage_per_user',
        'is_public'
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'usage_per_user' => 'integer',
    ];

    const TYPE_PRODUCT = 'product';
    const TYPE_CART = 'cart';
    const TYPE_SHIPPING = 'shipping';

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_promotions');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }
}