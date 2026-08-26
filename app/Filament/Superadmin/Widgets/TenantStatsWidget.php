<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalTenants = Tenant::withoutGlobalScopes()->count();

        $activeToday = Tenant::withoutGlobalScopes()
            ->where('is_active', true)
            ->count();

        $newThisMonth = Tenant::withoutGlobalScopes()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $churnRisk = Tenant::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereDoesntHave('subscription')
            ->count();

        return [
            Stat::make(__('Total Talleres'), $totalTenants)
                ->description(__('Registrados'))
                ->color('primary'),
            Stat::make(__('Activos'), $activeToday)
                ->description(__('Con acceso'))
                ->color('success'),
            Stat::make(__('Nuevos este mes'), $newThisMonth)
                ->description(__('En '.now()->format('M')))
                ->color('info'),
            Stat::make(__('En riesgo'), $churnRisk)
                ->description(__('Sin suscripción'))
                ->color('danger'),
        ];
    }
}
