<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
<<<<<<< HEAD
    public function register(): void
    {
        $this->app->singleton(\App\Services\AdminBadgeService::class);
    }

=======
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
>>>>>>> f90afea8 (Загрузка проекта без докерфайлов для фронта)
    public function boot(): void
    {
        //
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> f90afea8 (Загрузка проекта без докерфайлов для фронта)
