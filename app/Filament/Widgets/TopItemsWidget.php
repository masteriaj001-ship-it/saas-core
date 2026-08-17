<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatusEnum;
use App\Modules\Facturacion\Models\InvoiceItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class TopItemsWidget extends BaseWidget
{
    protected static ?int $sort = 12;

    public function getHeading(): ?string
    {
        return __('Top Artículos del Mes');
    }

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $topItems = InvoiceItem::query()
            ->select('description')
            ->selectRaw('sum(quantity) as total_qty')
            ->selectRaw('sum(total) as total_revenue')
            ->whereHas('invoice', function ($q) use ($startOfMonth) {
                $q->where('status', InvoiceStatusEnum::Paid)
                    ->where('issued_at', '>=', $startOfMonth);
            })
            ->groupBy('description')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        if ($topItems->isEmpty()) {
            return [
                Stat::make(__('Sin ventas'), '$0')
                    ->description(__('Aún no hay ventas este mes'))
                    ->descriptionIcon('heroicon-m-information-circle')
                    ->color('gray'),
            ];
        }

        $stats = [];
        foreach ($topItems as $item) {
            $stats[] = Stat::make(
                $item->description,
                '$'.number_format((float) $item->total_revenue, 0, ',', '.')
            )
                ->description($item->total_qty.' '.__('unidades vendidas'))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary');
        }

        return $stats;
    }
}
