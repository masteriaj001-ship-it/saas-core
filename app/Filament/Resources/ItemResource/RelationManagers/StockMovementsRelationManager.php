<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Historial de Movimientos';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Fecha'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('movement_type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'entry' => 'success',
                        'exit' => 'danger',
                        'adjustment' => 'warning',
                        'transfer_in' => 'info',
                        'transfer_out' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'entry' => __('Entrada'),
                        'exit' => __('Salida'),
                        'adjustment' => __('Ajuste'),
                        'transfer_in' => __('Transferencia Entrada'),
                        'transfer_out' => __('Transferencia Salida'),
                        default => $state,
                    }),
                TextColumn::make('warehouse.name')
                    ->label(__('Bodega'))
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label(__('Cantidad'))
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
                TextColumn::make('stock_before')
                    ->label(__('Stock Anterior'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_after')
                    ->label(__('Stock Posterior'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reason')
                    ->label(__('Motivo'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('user.name')
                    ->label(__('Usuario'))
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
