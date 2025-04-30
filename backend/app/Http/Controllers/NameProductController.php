<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NameProduct;
use Illuminate\Support\Facades\DB;

class NameProductController extends Controller
{
    public function index(){
        $tables = nameproduct::all();
        return $tables->toJson();
    }
}
