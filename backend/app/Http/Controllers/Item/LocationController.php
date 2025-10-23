<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Resources\Item\CatalogResource;
use App\Models\Category;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    protected $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * Получить товары доступные в городе (включая поставщиков)
     */
    public function getProductsInCity(Request $request, string $city)
    {
        try {
            $filters = $request->only(['category_id', 'brand_id', 'search', 'page', 'per_page', 'availability']);
            
            $products = $this->locationService->getProductsAvailableInCity($city, $filters);
            
        
            // Получаем категории
            $categories = Category::with(['subcategories.sub_subcategories' => function($query) use ($city) {
                $query->withCount(['products as available_products_count' => function($q) use ($city) {
                    $q->whereHas('inventories.warehouse', function($q) use ($city) {
                        $q->where('city', $city);
                    });
                }]);
            }])->get();
        
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
    }

    /**
     * Получить расширенную информацию о доступности товара
     */
    public function getProductAvailabilityDetails(string $city, int $productId)
    {
        try {
            $product = \App\Models\Product::with(['inventories.warehouse'])->findOrFail($productId);
            
            $availability = $this->locationService->enrichProductWithAvailability($product, $city);

            return response()->json([
                'data' => [
                    // 'product' => new ProductResource($product),   // инфа про продукт
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
    }

    /**
     * Получить список доступных городов
     */
    public function getCities()
    {
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
    }
}