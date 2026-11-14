<?php

use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\SetRequestTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->preventRequestForgery(except: [
            'livewire*',
            'presupuesto/*/approve',
            'presupuesto/*/reject',
        ]);

        $middleware->alias([
            'module' => EnsureModuleAccess::class,
            'tenant.request' => SetRequestTenantContext::class,
        ]);

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            SetRequestTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
