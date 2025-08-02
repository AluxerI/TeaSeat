<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    public function apply(float $price): float
    {
    return match ($this->type) {
        'percentage' => $price * (1 - $this->value / 100),
        'fixed'      => max($price - $this->value, 0), // Не меньше 0
    };
    }
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    // Связь с пользователями
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_used', 'activated_at']);
    }

    // Связь с товарами
    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
