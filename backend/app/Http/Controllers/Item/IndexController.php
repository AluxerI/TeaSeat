<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Resources\Item\CatalogResource;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $cacheKey = $this->generateCacheKey($request);

        $responseData = Cache::remember($cacheKey, 300, function () use ($request) {
            $categories = Category::with(["subcategories.sub_subcategories"])
                ->orderBy("name")
                ->get();

            $productsQuery = Product::with([
                "brand",
                "inventories.warehouse",
            ])->whereHas("inventories", function($query) {
                $query->where("quantity", ">", 0);
            });

            $productsQuery = $this->applyFilters($productsQuery, $request);

            $totalProducts = $productsQuery->count();
            $paginationThreshold = 1000;

            if ($totalProducts >= $paginationThreshold) {
                $products = $productsQuery->paginate($request->input("per_page", 24));
            } else {
                $products = $productsQuery->get();
            }

            return (new CatalogResource([
                "categories" => $categories,
                "products" => $products,
                "total_products" => $totalProducts,
            ]))->toResponse($request)->getData(true);
        });

        return $responseData;
    }

    private function generateCacheKey(Request $request): string
    {
        $params = $request->only(["category_id", "brand_id", "search", "page", "per_page", "sort_by", "sort_order"]);
        ksort($params);
        return "catalog_" . md5(json_encode($params));
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->has("category_id")) {
            $query->whereHas("sub_subcategories.subcategory", function($q) use ($request) {
                $q->where("category_id", $request->category_id);
            });
        }

        if ($request->has("brand_id")) {
            $query->where("brand_id", $request->brand_id);
        }

        if ($request->has("search")) {
            $query->where("name", "ilike", "%" . $request->search . "%");
        }

        $sortBy = $request->input("sort_by", "created_at");
        $sortOrder = $request->input("sort_order", "desc");
        $allowedSorts = ["price", "sold_count", "created_at", "name"];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query;
    }
}
