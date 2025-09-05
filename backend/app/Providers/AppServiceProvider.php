<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use faker\Factory;
use faker\Generator;
use Illuminate\Http\JsonResponse;



class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Services\PriceCalculatorService::class, function ($app) {
            return new \App\Services\PriceCalculatorService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(Generator::class, function () {
        return Factory::create('ru_RU'); // Устанавливаем русскую локаль
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
