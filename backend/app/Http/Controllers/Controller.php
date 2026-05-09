<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
=======
abstract class Controller
{
    public function index() {
        return view ('main');
    }
}
>>>>>>> f90afea8 (Загрузка проекта без докерфайлов для фронта)
