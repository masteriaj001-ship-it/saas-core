<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages\ListStockMovements;
use App\Modules\Inventario\Models\StockMovement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 13;

    public static function getNavigationLabel(): string
    {
        return 'Movimientos de Inventario';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Fecha'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('item.sku')
                    ->label(__('SKU'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label(__('Item'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label(__('Bodega'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('movement_type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'entry' => 'success',
                        'exit' => 'danger',
                        'transfer' => 'info',
                        'adjustment' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'entry' => __('Entrada'),
                        'exit' => __('Salida'),
                        'transfer' => __('Transferencia'),
                        'adjustment' => __('Ajuste'),
                        default => $state,
                    }),
                TextColumn::make('quantity')
                    ->label(__('Cantidad'))
                    ->sortable(),
                TextColumn::make('stock_before')
                    ->label(__('Stock Antes'))
                    ->sortable(),
                TextColumn::make('stock_after')
                    ->label(__('Stock Después'))
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label(__('Costo Unitario'))
                    ->sortable()
                    ->money('COP'),
                TextColumn::make('user.name')
                    ->label(__('Usuario'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reason')
                    ->label(__('Razón'))
                    ->searchable()
                    ->limit(50),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->label(__('Tipo'))
                    ->options([
                        'entry' => __('Entrada'),
                        'exit' => __('Salida'),
                        'transfer' => __('Transferencia'),
                        'adjustment' => __('Ajuste'),
                    ]),
            ])
            ->actions([
                DeleteAction::make()->hidden(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->hidden(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
