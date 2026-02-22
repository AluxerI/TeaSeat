<?php
// app/Http/Controllers/Item/Category/IndexController.php

namespace App\Http\Controllers\Item\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Item\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        // Получаем категории с подкатегориями и под-подкатегориями
        $categories = Category::with([
            'subcategories.sub_subcategories',
            'images' // подгружаем изображения категорий
        ])
        ->orderBy('name')
        ->get();

        // Возвращаем коллекцию категорий
        return CategoryResource::collection($categories);
    }
}