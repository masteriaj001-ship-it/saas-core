<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Services\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrado como Singleton absoluto del ciclo de vida de la petición
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager;
        });

        $this->app->bind(
            \Filament\Auth\Http\Responses\Contracts\LoginResponse::class,
            LoginResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });
    }
}
