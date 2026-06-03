<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Providers;

use App\Modules\Talleres\Actions\CreateAssetAction;
use App\Modules\Talleres\Actions\CreateWorkOrderAction;
use App\Modules\Talleres\Services\WorkOrderService;
use Illuminate\Support\ServiceProvider;

class TalleresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreateAssetAction::class);
        $this->app->singleton(CreateWorkOrderAction::class);
        $this->app->singleton(WorkOrderService::class);
    }

    public function boot(): void
    {
        //
    }
}
