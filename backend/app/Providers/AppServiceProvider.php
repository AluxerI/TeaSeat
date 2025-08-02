<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use faker\Factory;
use Illuminate\Http\JsonResponse;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(\Faker\Generator::class, function () {
        return Factory::create('ru_RU'); // Устанавливаем русскую локаль
        });
    }
}
