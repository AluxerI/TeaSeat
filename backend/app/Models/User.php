<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, HasApiTokens, Notifiable;

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_users')
            ->withPivot(['is_used', 'activated_at']);  // использована ли, когда активирована?
    }
    

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'timezone',       
        'is_active',      
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',  
        'deleted_at' => 'datetime', 
    ];

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    
    public function phoneVerificationCodes()
    {
        return $this->hasMany(PhoneVerificationCode::class);
    }
}
