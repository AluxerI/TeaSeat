<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, HasApiTokens, Notifiable, SoftDeletes;

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_users')
            ->withPivot(['is_used', 'activated_at']);  // использована ли, когда активирована?
    }
    
    public function createTokenWithLimit($name = 'auth-token', $abilities = ['*'], $limit = 5)
    {
        // Получаем текущие токены пользователя
        $tokens = $this->tokens()->orderBy('last_used_at', 'desc');
        
        // Если превышен лимит, удаляем самые старые
        if ($tokens->count() >= $limit) {
            $tokens->skip($limit - 1)->take(PHP_INT_MAX)->delete();
        }
        
        return $this->createToken($name, $abilities);
    }
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'provider',
        'provider_id',
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

    public function addresses()
    {
        return $this->hasMany(AddressClient::class, 'user_id');
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

     protected static function boot()
    {
        parent::boot();

        // Событие, срабатывающее ПЕРЕД мягким удалением пользователя
        static::deleting(function ($user) {
            // Удаляем все токены Sanctum этого пользователя
            $user->tokens()->delete();
            
            // Если сессии хранятся в БД, удаляем записи сессий
            // Предполагается, что у вас есть модель Session и связь 'sessions'
            $user->sessions()->delete();
            
            // Можно добавить здесь удаление других связанных данных
            // Например: $user->posts()->delete();
        });
    }

    /**
     * Связь "один ко многим" с таблицей personal_access_tokens.
     */
    // public function tokens()
    // {
    //     return $this->hasMany(PersonalAccessToken::class);
    // }

    /**
     * Связь "один ко многим" с таблицей сессий (если применимо).
     */
    public function sessions()
    {
        // Уточните имя модели и внешний ключ в соответствии с вашей структурой
        return $this->hasMany(Session::class, 'user_id'); 
    }
}
