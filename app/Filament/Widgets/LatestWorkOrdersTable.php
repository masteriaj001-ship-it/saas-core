<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

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
                    ->select(['id', 'code', 'title', 'asset_id', 'status', 'priority', 'created_at'])
                    ->with('asset:id,name')
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
                TextColumn::make('asset.name')
                    ->label(__('Activo'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'draft' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => __('Pendiente'),
                        'draft' => __('Borrador'),
                        'in_progress' => __('En Progreso'),
                        'completed' => __('Completada'),
                        'cancelled' => __('Cancelada'),
                        default => $state,
                    }),
                TextColumn::make('created_at')
                    ->label(__('Creada'))
                    ->dateTime(),
            ]);
    }
}
