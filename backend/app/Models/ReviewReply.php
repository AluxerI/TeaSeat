<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReply extends Model
{
    protected $fillable = [
        'review_id', 'author_id', 'author_name', 'body', 'edited_at',
    ];

    protected $casts = ['edited_at' => 'datetime'];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }
}
