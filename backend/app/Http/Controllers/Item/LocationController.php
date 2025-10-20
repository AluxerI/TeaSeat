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

    /**
     * Получить товары доступные в городе
     */
    public function getProductsInCity(Request $request, string $city)
    {
        try {
            $filters = $request->only(['category_id', 'brand_id', 'search', 'page', 'per_page']);
            
            $products = $this->locationService->getProductsAvailableInCity($city, $filters);
        
            // Получаем категории для выбранного города
            $categories = Category::with(['subcategories.sub_subcategories' => function($query) use ($city) {
                $query->withCount(['products as available_products_count' => function($q) use ($city) {
                    $q->whereHas('inventories.warehouse', function($q) use ($city) {
                        $q->where('city', $city)->where('quantity', '>', 0);
                    });
                }]);
            }])->get();
        
            // Передаем общее количество товаров для CatalogResource
            $totalProducts = $products instanceof \Illuminate\Pagination\LengthAwarePaginator 
                ? $products->total()
                : $products->count();
        
            return new CatalogResource([
                'categories' => $categories,
                'products' => $products,
                'total_products' => $totalProducts
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
     * Проверить доступность товара в городе
     */
    public function checkProductAvailability(Request $request, string $city, int $productId)
    {
        try {
            $product = \App\Models\Product::findOrFail($productId);
            
            $isAvailable = $this->locationService->isProductAvailableInCity($product, $city);
            $quantity = $this->locationService->getProductQuantityInCity($product, $city);

            return response()->json([
                'data' => [
                    'product_id' => $productId,
                    'city' => $city,
                    'is_available' => $isAvailable,
                    'available_quantity' => $quantity
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка при проверке доступности товара', [
                'city' => $city,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при проверке доступности товара',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}