<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\ItemResource;
use App\Filament\Resources\WorkOrderResource;
use App\Modules\Talleres\Models\Asset;
use App\Models\Contact;
use App\Models\Item;
use App\Modules\Talleres\Models\WorkOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DemoStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make(__('Activos'), Asset::count())
                ->icon('heroicon-o-cube')
                ->description(__('Total de activos del taller'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 5, 8, 6, 9, 10])
                ->color('info')
                ->url(AssetResource::getUrl('index')),

            Stat::make(__('Repuestos/Insumos'), Item::count())
                ->icon('heroicon-o-squares-2x2')
                ->description(Item::whereColumn('stock', '<', 'min_stock')->count().' '.__('con stock bajo'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->chart([20, 18, 22, 21, 25])
                ->color('warning')
                ->url(ItemResource::getUrl('index')),

            Stat::make(__('Contactos'), Contact::count())
                ->icon('heroicon-o-users')
                ->description(Contact::where('contact_type', 'client')->count().' '.__('clientes'))
                ->descriptionIcon('heroicon-m-user-group')
                ->chart([10, 12, 11, 14, 15])
                ->color('success')
                ->url(ContactResource::getUrl('index')),

            Stat::make(__('Órdenes de Trabajo'), WorkOrder::count())
                ->icon('heroicon-o-wrench')
                ->description(
                    WorkOrder::where('status', 'completed')->count().' '.__('completadas').' / '
                    .WorkOrder::where('status', 'in_progress')->count().' '.__('en progreso')
                )
                ->descriptionIcon('heroicon-m-clock')
                ->chart([15, 12, 18, 14, 20])
                ->color('primary')
                ->url(WorkOrderResource::getUrl('index')),

            Stat::make(__('Stock Bajo'), Item::whereColumn('stock', '<', 'min_stock')->count())
                ->icon('heroicon-o-exclamation-circle')
                ->description(__('Items por debajo del stock mínimo'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->url(ItemResource::getUrl('index')),
        ];
    }
}
