<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Review extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN = 'hidden';

    protected $table = 'reviews';

    protected $fillable = [
        'user_id',
        'product_id',
        'order_product_id',
        'rating',
        'comment',
        'status',
        'customer_edited_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'customer_edited_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class);
    }

    public function reply()
    {
        return $this->hasOne(ReviewReply::class);
    }

    public function moderationLogs()
    {
        return $this->hasMany(ContentModerationLog::class)
            ->latest('created_at')
            ->latest('id');
    }

    public function latestModeration()
    {
        return $this->hasOne(ContentModerationLog::class)
            ->latestOfMany(['created_at', 'id']);
    }

    public function scopePublished($query)
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('order_product_id');
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
                'status' => $this->status,
                'verified_purchase' => $this->order_product_id !== null,
                'created_at' => $this->created_at?->format('d.m.Y H:i'),
            ];
        });
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
