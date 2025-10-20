<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeliveryMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'cost',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
        'available_cities'
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
        'available_cities' => 'array'
    ];

    /**
     * Заказы с этим способом доставки
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope для активных методов доставки
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Проверить доступность в городе
     */
    public function isAvailableInCity(string $city): bool
    {
        // ДОБАВИТЬ проверку is_active
        if (!$this->is_active) {
            return false;
        }
        
        if (empty($this->available_cities)) {
            return true;
        }
    
        return in_array($city, $this->available_cities);
    }

    /**
     * Получить estimated_days в формате "1-3 дня"
     */
    public function getEstimatedDaysFormatted(): string
    {
        if ($this->estimated_days_min && $this->estimated_days_max) {
            return "{$this->estimated_days_min}-{$this->estimated_days_max} дней";
        } elseif ($this->estimated_days_min) {
            return "от {$this->estimated_days_min} дней";
        } elseif ($this->estimated_days_max) {
            return "до {$this->estimated_days_max} дней";
        }

        return 'уточняется';
    }
}