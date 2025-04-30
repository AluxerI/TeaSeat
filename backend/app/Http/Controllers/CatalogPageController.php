<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CatalogPageController extends Controller
{
    public function index() {
        return view ('catalog');
    }
}
