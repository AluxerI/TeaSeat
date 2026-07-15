<?php

namespace App\Models;

use App\Services\AdminBadgeService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasRoles, HasApiTokens, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_SELLER = 'seller';
    public const ROLE_PICKER = 'picker';
    public const ROLE_COURIER = 'courier';
    public const ROLE_USER = 'user';

    public const STAFF_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_MANAGER,
        self::ROLE_SELLER,
        self::ROLE_PICKER,
        self::ROLE_COURIER,
    ];

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

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function activeWarehouses()
    {
        return $this->warehouses()
            ->wherePivot('is_active', true)
            ->where('warehouses.is_active', true);
    }

    public function staffDevices()
    {
        return $this->hasMany(StaffDevice::class);
    }

    public function managedFulfillmentIssues()
    {
        return $this->hasMany(FulfillmentIssue::class, 'manager_id');
    }

    public function pickedOrders()
    {
        return $this->hasMany(Order::class, 'picker_id');
    }

    public function courierOrders()
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(self::STAFF_ROLES);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }

    public function hasWarehouseAccess(int $warehouseId): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MANAGER])) {
            return true;
        }

        return $this->activeWarehouses()
            ->whereKey($warehouseId)
            ->exists();
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
        static::saved(function (User $user) {
            $user->clearCache();
            AdminBadgeService::clearCache(); // 👈 ОЧИЩАЕМ КЕШ БЕЙДЖЕРОВ

            if ($user->wasChanged('is_active') && !$user->is_active) {
                $user->tokens()->delete();
                $user->sessions()->delete();
            }
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

    public function gifts()
    {
        return $this->hasMany(Gift::class);
    }

    public function completedOrders()
    {
        return $this->hasMany(Order::class)->where('status', Order::STATUS_DELIVERED);
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlists')
            ->withTimestamps()
            ->orderBy('wishlists.created_at', 'desc');
    }

    public function isProductInWishlist($productId): bool
    {
        return $this->wishlist()->where('product_id', $productId)->exists();
    }

}
