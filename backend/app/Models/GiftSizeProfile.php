<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftSizeProfile extends Model
{
    use HasFactory;

    public const KIND_ITEM = 'item';
    public const KIND_BOX = 'box';

    protected $fillable = [
        'code', 'name', 'kind', 'width_cells', 'height_cells', 'can_rotate',
        'max_weight_grams', 'default_markup_amount', 'simple_constructor_enabled',
        'simple_tea_count', 'simple_sweet_count', 'is_active',
    ];

    protected $casts = [
        'width_cells' => 'integer',
        'height_cells' => 'integer',
        'can_rotate' => 'boolean',
        'max_weight_grams' => 'integer',
        'default_markup_amount' => 'decimal:2',
        'simple_constructor_enabled' => 'boolean',
        'simple_tea_count' => 'integer',
        'simple_sweet_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function simpleRequirements(): ?array
    {
        if (!$this->simple_constructor_enabled || $this->kind !== self::KIND_BOX) {
            return null;
        }

        return [
            'tea_count' => (int) $this->simple_tea_count,
            'sweet_count' => (int) $this->simple_sweet_count,
            'total_items' => (int) $this->simple_tea_count
                + (int) $this->simple_sweet_count,
            'allow_duplicate_products' => true,
        ];
    }

    public function productSizes()
    {
        return $this->hasMany(ProductSize::class);
    }

    public function gifts()
    {
        return $this->hasMany(Gift::class);
    }
}
