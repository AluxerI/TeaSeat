<?php

namespace App\Models;

<<<<<<< HEAD
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use App\Services\AdminBadgeService;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, HasApiTokens, Notifiable, SoftDeletes;

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_user')
            ->withPivot(['is_used', 'used_count', 'activated_at'])
            ->withTimestamps();
    }
    public function activeDiscounts()
    {
        return $this->discounts()
            ->where('discounts.is_active', true)
            ->whereIn('discounts.type', [
                Discount::TYPE_PERSONAL,
                Discount::TYPE_FIRST_ORDER,
                Discount::TYPE_LOYALTY,
                Discount::TYPE_REFERRAL,
            ])
            ->wherePivot('is_used', false)
            ->where(function($query) {
                $query->whereNull('discounts.start_date')
                      ->orWhere('discounts.start_date', '<=', now());
            })
            ->where(function($query) {
                $query->whereNull('discounts.end_date')
                      ->orWhere('discounts.end_date', '>=', now());
            });
    }
    public function usedDiscounts()
    {
        return $this->discounts()->wherePivot('is_used', true);
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
    
=======
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
>>>>>>> f90afea8 (Загрузка проекта без докерфайлов для фронта)
    protected $fillable = [
        'name',
        'email',
        'password',
<<<<<<< HEAD
        'phone',
        'provider',
        'provider_id',
        'timezone',       
        'is_active',      
    ];

=======
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
>>>>>>> f90afea8 (Загрузка проекта без докерфайлов для фронта)
    protected $hidden = [
        'password',
        'remember_token',
    ];

<<<<<<< HEAD
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
    /**
     * Получить данные пользователя
     */
    public function getAllData(): array
    {
        $key = "user.{$this->id}.all";
        
        return Cache::remember($key, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'is_active' => $this->is_active,
                'email_verified' => !is_null($this->email_verified_at),
                'phone_verified' => !is_null($this->phone_verified_at),
                'roles' => $this->roles->pluck('name')->toArray(),
                'orders_count' => $this->orders()->count(),
                'reviews_count' => $this->reviews()->count(),
                'created_at' => $this->created_at?->format('d.m.Y'),
            ];
        });
    }

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "user.{$this->id}.all",
        ];
    }

    /**
     * Очистка кеша
     */
    public function clearCache(): void
    {
        foreach ($this->getCacheKeys() as $key) {
            Cache::forget($key);
        }
    }

    /**
     * События модели
     */
     protected static function booted()
    {
        static::saved(function ($user) {
            $user->clearCache();
            AdminBadgeService::clearCache(); // 👈 ОЧИЩАЕМ КЕШ БЕЙДЖЕРОВ
        });

        static::deleted(function ($user) {
            $user->clearCache();
            AdminBadgeService::clearCache(); // 👈 ОЧИЩАЕМ КЕШ БЕЙДЖЕРОВ
        });
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
     * Связь "один ко многим" с таблицей сессий (если применимо).
     */
    public function sessions()
    {
        // Уточните имя модели и внешний ключ в соответствии с вашей структурой
        return $this->hasMany(Session::class, 'user_id'); 
    }
    public function cart()
    {
        return $this->hasOne(Order::class)->where('status', Order::STATUS_CART);
    }

    public function completedOrders()
    {
        return $this->hasMany(Order::class)->where('status', Order::STATUS_DELIVERED);
    }


=======
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
>>>>>>> f90afea8 (Загрузка проекта без докерфайлов для фронта)
}
