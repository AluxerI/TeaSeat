<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddressClient extends Model
{
    // Указываем, что модель связана с таблицей 'address_client'
    protected $table = 'address_client';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}