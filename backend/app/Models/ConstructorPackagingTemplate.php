<?php

namespace App\Models;

use App\Traits\HasImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ConstructorPackagingTemplate extends Model
{
    use HasFactory, HasImage;

    public const KIND_POUCH = 'pouch';
    public const KIND_WRAPPER = 'wrapper';
    public const KIND_JAR = 'jar';
    public const KIND_OTHER = 'other';

    protected $fillable = [
        'code', 'name', 'kind', 'image_path', 'disk', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function productSizes()
    {
        return $this->hasMany(ProductSize::class, 'packaging_template_id');
    }

    public function getPathAttribute(): ?string
    {
        return $this->image_path;
    }

    protected static function booted(): void
    {
        static::deleted(function (ConstructorPackagingTemplate $template): void {
            if ($template->image_path) {
                Storage::disk($template->disk ?: 'public')->delete($template->image_path);
            }
        });
    }
}
