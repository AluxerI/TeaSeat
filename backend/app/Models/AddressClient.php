<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AddressClient extends Model
{
    use HasFactory;

    protected $table = 'address_client';

    protected $fillable = [
        'user_id',
        'street',
        'city',
        'postal_code'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'shipping_address_id');
    }

    /**
     * Получить полный адрес одной строкой
     */
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->postal_code,
            $this->city,
            $this->street
        ]);

        return implode(', ', $parts);
    }

    /**
     * Получить адрес для отображения (короткий формат)
     */
    public function getShortAddress(): string
    {
        return $this->city . ($this->street ? ', ' . $this->street : '');
    }
    
}