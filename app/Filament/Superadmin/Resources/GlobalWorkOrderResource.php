<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\GlobalWorkOrderResource\Pages\ListGlobalWorkOrders;
use App\Modules\Talleres\Models\WorkOrder;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GlobalWorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Órdenes Globales');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Plataforma');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->label(__('Taller'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('Código'))
                    ->searchable(),
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __(match ($state) {
                        'open' => 'Abierta',
                        'in_progress' => 'En progreso',
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                        default => $state,
                    })),
                TextColumn::make('priority')
                    ->label(__('Prioridad'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'info',
                        'medium' => 'warning',
                        'high' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('contact.name')
                    ->label(__('Cliente'))
                    ->searchable(),
                TextColumn::make('asset.name')
                    ->label(__('Activo'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGlobalWorkOrders::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope('tenant')
            ->whereNull('deleted_at')
            ->with(['tenant', 'contact', 'asset']);
    }
}
