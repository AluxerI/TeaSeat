<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Http\Resources\Item\ProductsResource;
use App\Models\Products;

class IndexController extends Controller
{
    public function __invoke()
    {
        $products =  Products::with(['nameProduct','VolumeWarehouse'])->paginate(10);
        // ->whereHas('nameProduct', fn($q) => $q->where('name', 'like', '%Книга%'))    фильтрация
        //  ->orderBy('created_at', 'desc')         сортировка . После неё должно пойти -> paginate(10) , не до этого всего
        // dd($product);
        return ProductsResource::collection($products);
    }
}
