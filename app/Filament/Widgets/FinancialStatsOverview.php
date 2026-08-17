<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatusEnum;
use App\Modules\Facturacion\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class FinancialStatsOverview extends BaseWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfWeek = Carbon::now()->startOfWeek();

        $paidToday = Invoice::query()
            ->where('status', InvoiceStatusEnum::Paid)
            ->whereDate('issued_at', $today);

        $paidThisMonth = Invoice::query()
            ->where('status', InvoiceStatusEnum::Paid)
            ->where('issued_at', '>=', $startOfMonth);

        $totalToday = (float) (clone $paidToday)->sum('grand_total');
        $countToday = (clone $paidToday)->count();
        $totalMonth = (float) (clone $paidThisMonth)->sum('grand_total');
        $countMonth = (clone $paidThisMonth)->count();

        $avgTicket = $countMonth > 0 ? round($totalMonth / $countMonth, 0) : 0;

        $totalWeek = (float) Invoice::query()
            ->where('status', InvoiceStatusEnum::Paid)
            ->where('issued_at', '>=', $startOfWeek)
            ->sum('grand_total');

        return [
            Stat::make(__('Ventas Hoy'), '$'.number_format($totalToday, 0, ',', '.'))
                ->icon('heroicon-o-banknotes')
                ->description($countToday.' '.__('facturas pagadas'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make(__('Ingresos Mes'), '$'.number_format($totalMonth, 0, ',', '.'))
                ->icon('heroicon-o-chart-bar')
                ->description($countMonth.' '.__('facturas este mes'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make(__('Ticket Promedio'), '$'.number_format($avgTicket, 0, ',', '.'))
                ->icon('heroicon-o-receipt-refund')
                ->description(__('Promedio por factura este mes'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make(__('Ingresos Semana'), '$'.number_format($totalWeek, 0, ',', '.'))
                ->icon('heroicon-o-calendar-days')
                ->description(__('Acumulado de la semana'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),
        ];
    }
}
