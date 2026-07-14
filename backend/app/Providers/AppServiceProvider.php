<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\AdminBadgeService::class);
        // В одном HTTP-запросе каталог переиспользует уже загруженные правила скидок.
        // scoped безопасен для Octane: состояние очищается между запросами.
        $this->app->scoped(\App\Services\PricingService::class);
        $this->app->scoped(\App\Services\PriceCalculatorService::class);
    }

    public function boot(): void
    {
        //
    }
}
