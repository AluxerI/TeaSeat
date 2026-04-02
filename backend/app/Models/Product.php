<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\ClearsModelCache;
use App\Traits\ResetsAdminBadges;

class Product extends Model
{
    use HasFactory, ClearsModelCache, ResetsAdminBadges;

    protected $table = 'products';


    protected $fillable = [
        'name',
        'ingredients',
        'description',
        'brand_id',
        'price',
        'weight_grams',
        'sold_count',
        'is_available',
        'total_quantity',
        'cached_data',
    ];
    protected $guarded = false;

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'total_quantity' => 'integer',
        'cached_data' => 'array',
    ];

    /**
     * Отношения
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class)->select(['id', 'name']);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->select([
            'id', 'product_id', 'path', 'is_main', 'is_background', 'sort_order'
        ]);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class)->select([
            'product_id', 'warehouse_id', 'quantity'
        ]);
    }

    public function sub_subcategories()
    {
        return $this->belongsToMany(
            Sub_Subcategory::class,
            'sub_subcategory_products',
            'product_id',
            'sub_subcategory_id'
        )->select(['sub_subcategories.id', 'sub_subcategories.name', 'sub_subcategories.subcategory_id']);
    }

    // Тяжелые отношения - используем только где нужно
    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'product_promotions');
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_products');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->withPivot(['cost_price', 'lead_time_days', 'min_order_quantity', 'is_active'])
            ->where('product_supplier.is_active', true)
            ->where('suppliers.is_active', true)
            ->withTimestamps();
    }

    /**
     * Получить путь к главному изображению
     */
    private function getMainImagePath(): ?string
    {
        $key = "product.{$this->id}.main_image_path";

        return Cache::remember($key, 3600, function () {
            return $this->images()
                ->where('is_main', true)
                ->value('path');
        });
    }


    public function sub_subcategory()
    {
        return $this->belongsToMany(Sub_Subcategory::class, 'sub_subcategory_products', 'product_id', 'sub_subcategory_id');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_products')
            ->withPivot(['quantity', 'unit_price', 'final_unit_price', 'total_price'])
            ->withTimestamps();
    }
    /**
     * Получить URL главного изображения
     */
    private function getMainImageUrl(): ?string
    {
        $key = "product.{$this->id}.main_image_url";

        return Cache::remember($key, 3600, function () {
            $image = $this->images()
                ->where('is_main', true)
                ->first();

            return $image?->image_url;
        });
    }

    /**
     * Получить фоновое изображение (путь)
     */
    private function getBackgroundImagePath(): ?string
    {
        $key = "product.{$this->id}.background_image_path";

        return Cache::remember($key, 3600, function () {
            return $this->images()
                ->where('is_background', true)
                ->value('path');
        });
    }

    /**
     * Получить URL фонового изображения
     */
    private function getBackgroundImageUrl(): ?string
    {
        $key = "product.{$this->id}.background_image_url";

        return Cache::remember($key, 3600, function () {
            $image = $this->images()
                ->where('is_background', true)
                ->first();

            return $image?->image_url;
        });
    }

    /**
     * Получить данные галереи
     */
    private function getGalleryData(): array
    {
        $key = "product.{$this->id}.gallery_data";

        return Cache::remember($key, 3600, function () {
            return $this->images()
                ->where('is_main', false)
                ->where('is_background', false)
                ->orderBy('sort_order')
                ->get()
                ->map(fn($img) => [
                    'path' => $img->path,
                    'url' => $img->image_url,
                    'alt' => $img->alt,
                    'title' => $img->title,
                    'sort_order' => $img->sort_order,
                ])
                ->values()
                ->toArray();
        });
    }

    /**
     * Получить путь по категориям
     */
    private function getCategoryPath(): ?array
    {
        $key = "product.{$this->id}.category_path";
        
        return Cache::remember($key, 3600, function () {
            $subSubcategory = $this->sub_subcategories()
                ->with(['subcategory.category' => function($q) {
                    $q->select(['id', 'name']);
                }])
                ->first();
            
            if (!$subSubcategory) return null;
            
            return [
                'category' => $subSubcategory->subcategory->category->name ?? null,
                'subcategory' => $subSubcategory->subcategory->name ?? null,
                'sub_subcategory' => $subSubcategory->name,
            ];
        });
    }

    /**
     * Получить общее количество на складах
     */
    private function getTotalQuantity(): float
    {
        $key = "product.{$this->id}.total_quantity";

        return Cache::remember($key, 300, function () {
            $inventories = $this->inventories()->get();
            $total = 0;

            foreach ($inventories as $inv) {
                if ($inv->unit === 'gram') {
                    $total += $inv->weight_quantity;
                } else {
                    $total += $inv->quantity;
                }
            }

            return $total;
        });
    }

    /**
     * Получить все данные товара одним ключом
     */
    public function getAllData(): array
    {
        $key = "product.{$this->id}.all";

        return Cache::remember($key, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'price' => (float) $this->price,
                'main_image_path' => $this->getMainImagePath(),
                'main_image_url' => $this->getMainImageUrl(),
                'background_image_path' => $this->getBackgroundImagePath(),
                'background_image_url' => $this->getBackgroundImageUrl(),
                'gallery_data' => $this->getGalleryData(),
                'category_path' => $this->getCategoryPath(),
                'total_quantity' => $this->getTotalQuantity(),
                'sold_count' => $this->sold_count,
                'is_available' => $this->is_available,
                'created_at' => $this->created_at?->format('d.m.Y'),
            ];
        });
    }

    /**
     * Данные для таблицы
     */
    public function getTableRow(): array
    {
        $key = "product.{$this->id}.table";
        
        return Cache::remember($key, 3600, function () {
            $data = $this->getAllData();
            
            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'price' => $data['price'],
                'main_image' => $data['main_image_url'],
                'category' => $data['category_path']['category'] ?? '—',
                'total_quantity' => $data['total_quantity'],
                'is_available' => $data['is_available'],
            ];
        });
    }

    /**
     * Детальные данные для карточки товара
     */
    public function getDetailedData(): array
    {
        $key = "product.{$this->id}.detailed";
        
        return Cache::remember($key, 3600, function () {
            return array_merge($this->getAllData(), [
                'description' => $this->description,
                'ingredients' => $this->ingredients,
                'weight_grams' => $this->weight_grams,
                'brand_name' => $this->brand?->name,
                'brand_id' => $this->brand?->id,
                'inventory' => $this->getInventoryData(),
                'promotions' => $this->getPromotionsData(),
                'discounts' => $this->getDiscountsData(),
            ]);
        });
    }

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "product.{$this->id}.all",
            "product.{$this->id}.table",
            "product.{$this->id}.detailed",
            "product.{$this->id}.main_image_path",
            "product.{$this->id}.main_image_url",
            "product.{$this->id}.background_image_path",
            "product.{$this->id}.background_image_url",
            "product.{$this->id}.gallery_data",
            "product.{$this->id}.category_path",
            "product.{$this->id}.total_quantity",
            "product.{$this->id}.inventory_data",    // новый
            "product.{$this->id}.promotions_data",   // новый
            "product.{$this->id}.discounts_data",    // новый
        ];
    }

    /**
     * Обновление кешированных полей
     */
    public function updateCacheFields(): void
    {
        $totalQuantity = $this->getTotalQuantity();
        
        $this->updateQuietly([
            'total_quantity' => $totalQuantity,
            'is_available' => $totalQuantity > 0,
        ]);
        
        $this->clearCache();
    }

    public function mutateFormDataBeforeCreate(array $data): array
    {
        // Убираем поле с категорией из данных для сохранения
        $this->sub_category_id = $data['selected_sub_subcategory_id'] ?? null;
        unset($data['selected_sub_subcategory_id']);
        unset($data['sub_subcategory_id']);
        
        return $data;
    }
    
    public function afterCreate(): void
    {
        if ($this->sub_category_id) {
            $this->record->sub_subcategories()->sync([$this->sub_category_id]);
        }
    }


    protected static function booted()
    {
        parent::booted();

        static::creating(function ($product) {
            // Удаляем поле sub_subcategory_id из данных, если оно есть
            if (isset($product->sub_subcategory_id)) {
                $product->sub_subcategory_id = null;
            }
        });

        static::created(function ($product) {
            // Если есть ID под-подкатегории в запросе, привязываем
            if (request()->has('sub_subcategory_id') && request()->input('sub_subcategory_id')) {
                $product->sub_subcategories()->sync([request()->input('sub_subcategory_id')]);
            }
        });

        static::updating(function ($product) {
            // Удаляем поле sub_subcategory_id из данных, если оно есть
            if (isset($product->sub_subcategory_id)) {
                unset($product->sub_subcategory_id);
            }
        });

        static::updated(function ($product) {
            // Если есть ID под-подкатегории в запросе, обновляем связь
            if (request()->has('sub_subcategory_id') && request()->input('sub_subcategory_id')) {
                $product->sub_subcategories()->sync([request()->input('sub_subcategory_id')]);
            }
        });
        
        // static::creating(function ($product) {
        //     if (empty($product->sku)) {
        //         $product->sku = self::generateSku($product);
        //     }
        // });
    }
    /**
     * Scopes
     */
    public function scopeInStock($query)
    {
        return $query->where('is_available', true);
    }
    public function scopeForSelect($query)
    {
        return $query->select('id', 'name');
    }

    public function scopeAvailableInCity($query, string $city)
    {
        return $query->where(function($q) use ($city) {
            $q->whereHas('inventories.warehouse', function($query) use ($city) {
                $query->where('city', $city)->where('quantity', '>', 0);
            })
            ->orWhereHas('suppliers');
        });
    }

    public static function generateSku($product): string
    {
        $brandPrefix = $product->brand ? substr($product->brand->name, 0, 3) : 'UNK';
        $id = str_pad($product->id ?? rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        return strtoupper($brandPrefix) . '-' . $id;
    }
    // Добавить в конец класса Product (перед последней скобкой)

/**
 * Получить данные об остатках на складах
 */
public function getInventoryData(): array
{
    $key = "product.{$this->id}.inventory_data";
    
    return Cache::remember($key, 300, function () {
        return $this->inventories->map(function ($inventory) {
            return [
                'warehouse_id' => $inventory->warehouse_id,
                'warehouse_name' => $inventory->warehouse->name,
                'warehouse_city' => $inventory->warehouse->city,
                'quantity' => $inventory->quantity,
                'weight_quantity' => $inventory->weight_quantity,
                'last_restock_date' => $inventory->last_restock_date?->format('d.m.Y'),
            ];
        })->values()->toArray();
    });
}

/**
 * Получить данные об акциях на товар
 */
public function getPromotionsData(): array
{
    $key = "product.{$this->id}.promotions_data";
    
    return Cache::remember($key, 3600, function () {
        return $this->promotions->map(function ($promotion) {
            return [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'discount_percent' => (float) $promotion->discount_percent,
                'start_date' => $promotion->start_date?->format('d.m.Y'),
                'end_date' => $promotion->end_date?->format('d.m.Y'),
            ];
        })->values()->toArray();
    });
}

/**
 * Получить данные о скидках на товар
 */
    public function getDiscountsData(): array
    {
        $key = "product.{$this->id}.discounts_data";

        return Cache::remember($key, 3600, function () {
            return $this->discounts->map(function ($discount) {
                return [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'type' => $discount->type,
                    'value' => (float) $discount->value,
                    'start_at' => $discount->start_at?->format('d.m.Y'),
                    'end_at' => $discount->end_at?->format('d.m.Y'),
                ];
            })->values()->toArray();
        });
    }


}