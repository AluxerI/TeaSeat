<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Resources\Item\CatalogResource;
use App\Models\Category;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    protected $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    public function getProductsInCity(Request $request, string $city)
    {
        $cacheKey = "city_{$city}_products_" . md5($request->fullUrl());
        
        return Cache::remember($cacheKey, 300, function () use ($request, $city) {
            try {
                $filters = $request->only(['category_id', 'brand_id', 'search', 'page', 'per_page']);
                
                $products = $this->locationService->getProductsAvailableInCity($city, $filters);
                
                $categories = Category::with(['subcategories.sub_subcategories'])
                    ->orderBy('name')
                    ->get();
                
                return new CatalogResource([
                    'categories' => $categories,
                    'products' => $products,
                    'total_products' => $products->count(),
                    'city' => $city
                ]);
                
            } catch (\Exception $e) {
                Log::error('Ошибка при получении товаров для города', [
                    'city' => $city,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'message' => 'Ошибка при получении товаров',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }
        });
    }

    public function getProductAvailabilityDetails(string $city, int $productId)
    {
        $cacheKey = "city_{$city}_product_{$productId}_availability";
        
        return Cache::remember($cacheKey, 60, function () use ($city, $productId) {
            try {
                $product = \App\Models\Product::with(['inventories.warehouse'])->findOrFail($productId);
                
                $availability = $this->locationService->enrichProductWithAvailability($product, $city);

                return response()->json([
                    'data' => [
                        'availability' => $availability['availability']
                    ]
                ]);
                
            } catch (\Exception $e) {
                Log::error('Ошибка при получении информации о доступности', [
                    'city' => $city,
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'message' => 'Ошибка при получении информации о доступности',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }
        });
    }

    public function getCities()
    {
        return Cache::remember('available_cities', 3600, function () {
            try {
                $cities = $this->locationService->getAvailableCities();
                
                return response()->json([
                    'data' => $cities,
                    'count' => $cities->count()
                ]);
                
            } catch (\Exception $e) {
                Log::error('Ошибка при получении списка городов', [
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'message' => 'Ошибка при получении списка городов',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }
        });
    }
}