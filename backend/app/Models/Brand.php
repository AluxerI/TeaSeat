<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\ClearsModelCache;
use App\Traits\ResetsAdminBadges;

class Brand extends Model
{
    use HasFactory, ClearsModelCache, ResetsAdminBadges;

    protected $fillable = [
        'name',
        'country',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function getAllData(): array
    {
        $key = "brand.{$this->id}.all";

        return Cache::remember($key, 3600, function () {
            $stats = DB::table('products')
                ->where('brand_id', $this->id)
                ->selectRaw('
                    COUNT(*) as products_count,
                    COALESCE(SUM(sold_count), 0) as total_sold,
                    AVG(price) as avg_price
                ')
                ->first();

            return [
                'id' => $this->id,
                'name' => $this->name,
                'country' => $this->country,
                // 'logo' => $this->logo, - удаляем
                'products_count' => (int) ($stats->products_count ?? 0),
                'total_sold' => (int) ($stats->total_sold ?? 0),
                'avg_price' => $stats->avg_price ? (float) $stats->avg_price : null,
                'created_at' => $this->created_at?->format('d.m.Y'),
            ];
        });
    }

    public function getTableRow(): array
    {
        $data = $this->getAllData();
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'country' => $data['country'],
            'logo' => $data['logo'],
            'products_count' => $data['products_count'],
            'total_sold' => $data['total_sold'],
        ];
    }

    public static function getCachedCountries(): array
    {
        return Cache::remember('brands.countries', 86400, function () {
            return self::distinct()
                ->whereNotNull('country')
                ->orderBy('country')
                ->pluck('country', 'country')
                ->toArray();
        });
    }

    protected function getCacheKeys(): array
    {
        return [
            "brand.{$this->id}.all",
            'brands.countries',
        ];
    }

    protected static function booted()
    {
        static::saved(function ($brand) {
            $brand->clearCache();
            if ($brand->wasChanged('country')) {
                Cache::forget('brands.countries');
            }
        });

        static::deleted(function ($brand) {
            $brand->clearCache();
            Cache::forget('brands.countries');
        });
    }
}