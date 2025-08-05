<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles;

    // Связь со скидками
    public function discounts()
    {
        return $this->belongsToMany(Discount::class)
            ->withPivot(['is_used', 'activated_at']);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',       // Добавляем новые поля из миграции
        'is_active',      // Добавляем новые поля из миграции
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',  // Добавляем приведение типа для нового поля
        'deleted_at' => 'datetime', // Для softDeletes
    ];

    protected $dates = [
        'deleted_at'     // Для совместимости (если используете старую версию Laravel)
    ];
}
