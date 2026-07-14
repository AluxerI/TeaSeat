<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_uuid',
        'name',
        'last_warehouse_id',
        'personal_access_token_id',
        'last_sync_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lastWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'last_warehouse_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'seller_device_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
