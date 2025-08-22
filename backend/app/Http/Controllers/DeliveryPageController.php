<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliveryPageController extends Controller
{
    public function index() {
        return view ('main');
    }
}
