<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\WorkOrderStatusEnum;
use App\Modules\Talleres\Models\WorkOrder;
use Filament\Widgets\BarChartWidget;
use Illuminate\Support\Facades\DB;

class WorkOrderStatusChart extends BarChartWidget
{
    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        return __('Órdenes por Estado');
    }

    protected function getData(): array
    {
        $statuses = WorkOrder::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $labels = collect(WorkOrderStatusEnum::cases())
            ->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()])
            ->toArray();

        $colors = collect(WorkOrderStatusEnum::cases())
            ->map(fn ($e) => match ($e->getColor()) {
                'gray' => '#6b7280',
                'info' => '#3b82f6',
                'warning' => '#eab308',
                'primary' => '#8b5cf6',
                'success' => '#22c55e',
                'danger' => '#ef4444',
                default => '#6b7280',
            })->values()->toArray();

        $data = [];
        foreach (array_keys($labels) as $status) {
            $data[] = $statuses[$status] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => __('Órdenes de Trabajo'),
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_values($labels),
        ];
    }
}
