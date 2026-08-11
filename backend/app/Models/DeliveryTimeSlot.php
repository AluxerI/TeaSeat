<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryTimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_method_id',
        'weekday',
        'time_from',
        'time_to',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function deliveryMethod()
    {
        return $this->belongsTo(DeliveryMethod::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function timeFrom(): string
    {
        return substr((string) $this->time_from, 0, 5);
    }

    public function timeTo(): string
    {
        return substr((string) $this->time_to, 0, 5);
    }
}
