<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use faker\Factory;
use faker\Generator;
use Illuminate\Http\JsonResponse;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }


    public function boot(): void
    {
        $this->app->singleton(Generator::class, function () {
        return Factory::create('ru_RU'); // Устанавливаем русскую локаль
        });
    }
}
