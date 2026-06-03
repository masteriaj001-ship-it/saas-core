<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\GlobalAssetResource\Pages\ListGlobalAssets;
use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GlobalAssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Activos Globales');
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
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('Código'))
                    ->searchable(),
                TextColumn::make('asset_type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'phones' => 'info',
                        'computers' => 'success',
                        'vehicles' => 'warning',
                        'equipment' => 'info',
                        'space' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __(match ($state) {
                        'phones' => 'Celulares',
                        'computers' => 'Cómputo',
                        'vehicles' => 'Vehículos',
                        'equipment' => 'Equipamiento / Maquinaria',
                        'space' => 'Espacio / Infraestructura',
                        default => $state,
                    })),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'disposed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __(match ($state) {
                        'active' => 'Activo',
                        'maintenance' => 'En mantenimiento',
                        'disposed' => 'Dado de baja',
                        default => $state,
                    })),
                TextColumn::make('created_at')
                    ->label(__('Registrado'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('asset_type')
                    ->label(__('Tipo'))
                    ->options([
                        'phones' => __('Celulares'),
                        'computers' => __('Cómputo'),
                        'vehicles' => __('Vehículos'),
                    ]),
                SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options([
                        'active' => __('Activo'),
                        'maintenance' => __('En mantenimiento'),
                        'disposed' => __('Dado de baja'),
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGlobalAssets::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope('tenant')
            ->whereNull('deleted_at')
            ->with('tenant');
    }
}
