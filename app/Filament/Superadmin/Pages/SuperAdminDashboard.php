<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Pages;

use App\Filament\Superadmin\Widgets\ChurnRiskWidget;
use App\Filament\Superadmin\Widgets\PlanDistributionWidget;
use App\Filament\Superadmin\Widgets\RecentActivityWidget;
use App\Filament\Superadmin\Widgets\TenantStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class SuperAdminDashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Plataforma');
    }

    public static function getNavigationSort(): int
    {
        return 0;
    }

    public function getHeaderWidgets(): array
    {
        return [
            TenantStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            PlanDistributionWidget::class,
            RecentActivityWidget::class,
            ChurnRiskWidget::class,
        ];
    }
}
