<?php

declare(strict_types=1);

namespace App\Modules\Budget\Providers;

use App\Modules\Budget\Models\Budget;
use App\Modules\Budget\Observers\BudgetObserver;
use App\Modules\Budget\Services\BudgetConversionService;
use Illuminate\Support\ServiceProvider;

class BudgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BudgetConversionService::class);
    }

    public function boot(): void
    {
        Budget::observe(BudgetObserver::class);
    }
}
