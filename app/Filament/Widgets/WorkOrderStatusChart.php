<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\WorkOrder;
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

        $labels = [
            'draft'       => __('Borrador'),
            'in_progress' => __('En Progreso'),
            'completed'   => __('Completadas'),
            'cancelled'   => __('Canceladas'),
        ];

        $data = [];
        $colors = ['#6b7280', '#eab308', '#22c55e', '#ef4444'];

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
