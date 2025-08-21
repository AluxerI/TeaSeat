<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Prompts\FormBuilder;
use App\Models\Product;

class MainPageController extends Controller
{
    public function index() {
        $products = Product::all();
        return view ('main',compact('products'));
    }
}
