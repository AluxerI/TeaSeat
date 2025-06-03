<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\NameProduct;

class ProductController extends Controller
{
  
    public function index()
    {
        $tables = DB::table('nameproduct')->get();
        // dd($tables);
        $nameproducts =[
            [
                'nameitem' => 'test product 1',
                'description' => 'very large descript',
                'imageitem' => 'image.png',
            ],
            [
                'nameitem' => 'test product 2',
                'description' => 'very very large descript',
                'imageitem' => 'image2.png',
            ],
        ];
        NameProduct::create([
            'nameitem' => 'test product 3',   
            'description' => 'very very large descript',
            'imageitem' => 'image2.png',
        ]);
        dd('created');
    }

}