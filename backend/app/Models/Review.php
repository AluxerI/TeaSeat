<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Получить данные отзыва
     */
    public function getAllData(): array
    {
        $key = "review.{$this->product_id}.{$this->user_id}.all";
        
        return Cache::remember($key, 3600, function () {
            return [
                'product_id' => $this->product_id,
                'product_name' => $this->product?->name,
                'user_id' => $this->user_id,
                'user_name' => $this->user?->name,
                'user_email' => $this->user?->email,
                'rating' => $this->rating,
                'rating_stars' => $this->rating_stars,
                'comment' => $this->comment,
                'created_at' => $this->created_at?->format('d.m.Y H:i'),
            ];
        });
    }

    public function getReviewKeyAttribute(): string
    {
        return $this->product_id . '-' . $this->user_id;
    }

    public function getRouteKeyName()
    {
        return 'review_key';
    }

    public function getRatingStarsAttribute(): string
    {
        $full = str_repeat('★', $this->rating);
        $empty = str_repeat('☆', 5 - $this->rating);
        return $full . $empty;
    }

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "review.{$this->product_id}.{$this->user_id}.all",
            "product.{$this->product_id}.reviews",
            "user.{$this->user_id}.reviews",
        ];
    }

    /**
     * События модели
     */
    protected static function booted()
    {
        static::saved(function ($review) {
            $review->clearCache();
        });

        static::deleted(function ($review) {
            $review->clearCache();
        });
    }

    /**
     * Очистка кеша (для трейта)
     */
    public function clearCache(): void
    {
        foreach ($this->getCacheKeys() as $key) {
            Cache::forget($key);
        }
    }
}