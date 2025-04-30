<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Prompts\FormBuilder;
use App\Models\Products;

class MainPageController extends Controller
{
    public function index() {
        $products = Products::all();
        return view ('main',compact('products'));
    }
}
