<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';
    
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'comment'
    ];

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Пользователь, оставивший отзыв
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Товар, к которому оставлен отзыв
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Получить составной ключ для маршрутов
     */
    public function getRouteKeyName()
    {
        return 'review_key';
    }

    /**
     * Виртуальный атрибут для составного ключа
     */
    public function getReviewKeyAttribute(): string
    {
        return $this->product_id . '-' . $this->user_id;
    }

    /**
     * Получить рейтинг звездами
     */
    public function getRatingStarsAttribute(): string
    {
        $full = str_repeat('★', $this->rating);
        $empty = str_repeat('☆', 5 - $this->rating);
        return $full . $empty;
    }
}