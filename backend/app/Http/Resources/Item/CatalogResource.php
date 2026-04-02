<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;

class CatalogResource extends JsonResource
{
    public function toArray($request)
    {
        $response = [
            'categories' => CategoryResource::collection($this['categories']),
            'products' => ProductResource::collection($this['products']),
        ];

        // Добавляем пагинацию только если это объект пагинации
        if ($this['products'] instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $response['pagination'] = [
                'current_page' => $this['products']->currentPage(),
                'per_page' => $this['products']->perPage(),
                'total' => $this['products']->total(),
                'last_page' => $this['products']->lastPage(),
                'from' => $this['products']->firstItem(),
                'to' => $this['products']->lastItem(),
            ];
        } else {
            $response['meta'] = [
                'total_products' => $this['products']->count(),
                'has_pagination' => false
            ];
        }

        // Добавляем город если есть (для LocationController)
        if (isset($this['city'])) {
            $response['city'] = $this['city'];
        }

        // Добавляем общее количество товаров
        if (isset($this['total_products'])) {
            $response['total_products'] = $this['total_products'];
        }

        return $response;
    }
}