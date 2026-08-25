<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Actions\StartImpersonationAction;
use App\Modules\Plataforma\Actions\StopImpersonationAction;
use App\Modules\Plataforma\Observers\UserLimitObserver;
use App\Modules\Plataforma\Observers\WorkOrderLimitObserver;
use App\Modules\Plataforma\Services\ImpersonationService;
use App\Modules\Plataforma\Services\SubscriptionService;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class PlataformaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(ImpersonationService::class);
    }

    public function boot(): void
    {
        WorkOrder::observe(WorkOrderLimitObserver::class);
        User::observe(UserLimitObserver::class);

        Schedule::command('subscriptions:check-expired')->hourly();

        $this->registerImpersonationRoutes();
    }

    private function registerImpersonationRoutes(): void
    {
        Route::middleware(['web', 'auth'])->prefix('superadmin')->group(function (): void {
            Route::get('/impersonate/{tenant}', function (string $tenantId) {
                $user = auth()->user();

                if (! $user || ! $user->is_superadmin) {
                    abort(403);
                }

                $tenant = Tenant::findOrFail($tenantId);

                app(StartImpersonationAction::class)->execute($user, $tenant);

                return redirect("/admin/{$tenant->slug}");
            })->name('superadmin.impersonate');

            Route::get('/stop-impersonating', function () {
                $user = auth()->user();

                if (! $user) {
                    abort(401);
                }

                app(StopImpersonationAction::class)->execute($user);

                return redirect('/superadmin');
            })->name('superadmin.stop-impersonating');
        });
    }
}
