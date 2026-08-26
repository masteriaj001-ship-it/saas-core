<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Widgets;

use App\Modules\Plataforma\Models\Plan;
use Filament\Widgets\ChartWidget;

class PlanDistributionWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getHeight(): ?string
    {
        return '250px';
    }

    public function getHeading(): ?string
    {
        return __('Distribución por Plan');
    }

    protected function getData(): array
    {
        $plans = Plan::withCount('subscriptions')->get();

        return [
            'datasets' => [
                [
                    'data' => $plans->pluck('subscriptions_count')->toArray(),
                ],
            ],
            'labels' => $plans->pluck('label')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
