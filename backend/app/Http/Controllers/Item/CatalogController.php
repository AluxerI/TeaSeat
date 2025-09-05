<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Resources\Item\CatalogResource;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

    class CatalogController extends Controller
{
    public function __invoke(Request $request)
    {
        // Получаем категории с вложенными отношениями
        $categories = Category::with(['subcategories.sub_subcategories' => function($query) {
            $query->withCount(['products as available_products_count' => function($q) {
                $q->whereHas('inventories', function($q) {
                    $q->where('quantity', '>', 0);
                });
            }]);
        }])->get();

        // Получаем товары в наличии с пагинацией
        $productsQuery = Product::with([
            'sub_subcategories.subcategory.category',
            'brand',
            'inventories.warehouse',
            'promotions'
        ])->whereHas('inventories', function($query) {
            $query->where('quantity', '>', 0);
        });
        // Проверка паггинации
        $totalProducts = $productsQuery->count();
        $paginationThreshold = 1000;
        
        if ($totalProducts >= $paginationThreshold) {
            $products = $productsQuery->paginate($request->get('per_page', 24));
        } else {
            $products = $productsQuery->get();
        }

        return new CatalogResource([
            'categories' => $categories,
            'products' => $products,
            'total_products' => $totalProducts
        ]);
    }
}
