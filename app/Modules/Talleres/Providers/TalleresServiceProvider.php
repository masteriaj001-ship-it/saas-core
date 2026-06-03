<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Providers;

use App\Modules\Talleres\Actions\CreateAssetAction;
use App\Modules\Talleres\Actions\CreateWorkOrderAction;
use App\Modules\Talleres\Actions\RegisterTenantAction;
use App\Modules\Talleres\Services\WorkOrderService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class TalleresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreateAssetAction::class);
        $this->app->singleton(CreateWorkOrderAction::class);
        $this->app->singleton(RegisterTenantAction::class);
        $this->app->singleton(WorkOrderService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(
            __DIR__.'/../Resources/Views',
            'talleres'
        );

        Blade::componentNamespace(
            'App\\Modules\\Talleres\\Resources\\Views\\Components',
            'talleres'
        );
    }
}
