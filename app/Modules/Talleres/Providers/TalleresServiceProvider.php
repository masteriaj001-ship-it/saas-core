<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Providers;

use App\Modules\Talleres\Actions\CreateAssetAction;
use App\Modules\Talleres\Actions\RegisterTenantAction;
use App\Modules\Talleres\Actions\RequestQuoteApprovalAction;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use App\Modules\Talleres\Observers\WorkOrderItemObserver;
use App\Modules\Talleres\Observers\WorkOrderObserver;
use App\Modules\Talleres\Services\MediaService;
use App\Modules\Talleres\Services\WorkOrderCodeGenerator;
use App\Modules\Talleres\Services\WorkOrderWebhookService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class TalleresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreateAssetAction::class);
        $this->app->singleton(RegisterTenantAction::class);
        $this->app->singleton(RequestQuoteApprovalAction::class);
        $this->app->singleton(WorkOrderCodeGenerator::class);
        $this->app->singleton(MediaService::class);
        $this->app->singleton(WorkOrderWebhookService::class);
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

        WorkOrder::observe(WorkOrderObserver::class);
        WorkOrderItem::observe(WorkOrderItemObserver::class);
    }
}
