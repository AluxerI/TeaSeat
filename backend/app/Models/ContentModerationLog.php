<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentModerationLog extends Model
{
    public const UPDATED_AT = null;
    public const ACTION_HIDE = 'hide';
    public const ACTION_RESTORE = 'restore';
    public const REASON_SPAM = 'spam';
    public const REASON_ABUSE = 'abuse';
    public const REASON_PERSONAL_DATA = 'personal_data';
    public const REASON_OFF_TOPIC = 'off_topic';
    public const REASON_FRAUD = 'fraud';
    public const REASON_OTHER = 'other';

    protected $fillable = [
        'review_id', 'order_feedback_id', 'moderator_id', 'moderator_name',
        'action', 'reason_code', 'comment',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function orderFeedback()
    {
        return $this->belongsTo(OrderFeedback::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id')->withTrashed();
    }

    /** @return array<string, string> */
    public static function reasonOptions(): array
    {
        return [
            self::REASON_SPAM => 'Спам или реклама',
            self::REASON_ABUSE => 'Оскорбления или угрозы',
            self::REASON_PERSONAL_DATA => 'Персональные данные',
            self::REASON_OFF_TOPIC => 'Не относится к товару или заказу',
            self::REASON_FRAUD => 'Мошенничество или заведомо ложные сведения',
            self::REASON_OTHER => 'Другое нарушение',
        ];
    }
}
