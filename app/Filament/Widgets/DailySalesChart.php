<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatusEnum;
use App\Modules\Facturacion\Models\Invoice;
use Filament\Widgets\BarChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailySalesChart extends BarChartWidget
{
    protected static ?int $sort = 11;

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('Ventas Últimos 7 Días');
    }

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => Carbon::today()->subDays($i));

        $salesByDay = Invoice::query()
            ->where('status', InvoiceStatusEnum::Paid)
            ->where('issued_at', '>=', Carbon::today()->subDays(6)->startOfDay())
            ->select(
                DB::raw('DATE(issued_at) as date'),
                DB::raw('sum(grand_total) as total'),
                DB::raw('count(*) as count')
            )
            ->groupBy(DB::raw('DATE(issued_at)'))
            ->pluck('total', 'date')
            ->toArray();

        $labels = $days->map(fn ($d) => $d->translatedFormat('D'))->toArray();
        $data = $days->map(fn ($d) => $salesByDay[$d->toDateString()] ?? 0)->toArray();

        return [
            'datasets' => [
                [
                    'label' => __('Ingresos'),
                    'data' => $data,
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#f59e0b',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
