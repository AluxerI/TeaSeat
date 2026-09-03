<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSize extends Model
{
    use HasFactory;

    public const ROLE_TEA = 'tea';
    public const ROLE_SWEET = 'sweet';
    public const ROLE_GENERAL = 'general';

    protected $fillable = [
        'product_id', 'gift_size_profile_id', 'packaging_template_id', 'label', 'product_quantity',
        'constructor_role', 'is_active',
    ];

    protected $casts = [
        'product_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sizeProfile()
    {
        return $this->belongsTo(GiftSizeProfile::class, 'gift_size_profile_id');
    }

    public function packagingTemplate()
    {
        return $this->belongsTo(
            ConstructorPackagingTemplate::class,
            'packaging_template_id'
        );
    }

    public function assertConstructorReady(): void
    {
        if (!$this->is_active || !$this->product?->is_available) {
            throw new DomainException('Выбранный товар недоступен в конструкторе');
        }
        if (!$this->sizeProfile?->is_active || $this->sizeProfile->kind !== GiftSizeProfile::KIND_ITEM) {
            throw new DomainException('Для товара не настроен активный размер конструктора');
        }
        $this->product->assertValidSaleQuantity((int) $this->product_quantity);
    }
}
