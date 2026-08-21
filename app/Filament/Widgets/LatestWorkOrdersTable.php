<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\WorkOrderStatusEnum;
use App\Modules\Talleres\Models\WorkOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class LatestWorkOrdersTable extends BaseTableWidget
{
    protected static ?int $sort = 3;

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->heading(__('Últimas Órdenes de Trabajo'))
            ->query(
                WorkOrder::query()
                    ->select(['id', 'code', 'title', 'client_vehicle_id', 'status', 'priority', 'created_at'])
                    ->with('clientVehicle:id,plate,brand,model')
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('code')
                    ->label(__('Código'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->limit(30)
                    ->searchable(),
                TextColumn::make('clientVehicle.plate')
                    ->label(__('Placa'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (WorkOrderStatusEnum $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn (WorkOrderStatusEnum $state): string => $state->getLabel()),
                TextColumn::make('created_at')
                    ->label(__('Creada'))
                    ->dateTime(),
            ]);
    }
}
