<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages\CreateAsset;
use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Modules\Talleres\Models\Asset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Activo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Activos');
    }

    public static function getNavigationLabel(): string
    {
        return __('Activos');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Gestión');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Alias / Referencia interna'))
                            ->placeholder('Ej: Corolla de Juan, Moto roja')
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label(__('Código'))
                            ->maxLength(100),
                        Select::make('asset_type')
                            ->label(__('Tipo'))
                            ->required()
                            ->live()
                            ->options([
                                'vehicle' => __('Vehículos'),
                                'equipment' => __('Equipamiento / Maquinaria'),
                                'phones' => __('Celulares'),
                                'computers' => __('Cómputo'),
                                'space' => __('Espacio / Infraestructura'),
                            ])
                            ->afterStateUpdated(function (string $operation, string $state, $set): void {
                                $defaults = match ($state) {
                                    'phones' => ['marca' => '', 'modelo' => '', 'imei' => '', 'clave_acceso' => '', 'observaciones_fisicas' => ''],
                                    'computers' => ['marca' => '', 'modelo' => '', 'procesador' => '', 'ram' => '', 'almacenamiento' => ''],
                                    'vehicle' => ['marca' => '', 'modelo' => '', 'anio' => '', 'placa' => '', 'color' => '', 'numero_serie' => ''],
                                    default => [],
                                };
                                $set('metadata', $defaults);
                            }),
                        Select::make('status')
                            ->label(__('Estado'))
                            ->required()
                            ->default('active')
                            ->options([
                                'active' => __('Activo'),
                                'maintenance' => __('En mantenimiento'),
                                'disposed' => __('Dado de baja'),
                            ]),
                        DatePicker::make('acquired_at')
                            ->label(__('Fecha de adquisición')),
                    ]),
                Section::make(__('Metadatos'))
                    ->schema([
                        KeyValue::make('metadata')
                            ->label(__('Metadatos'))
                            ->keyLabel(__('Clave'))
                            ->valueLabel(__('Valor'))
                            ->addActionLabel(__('Agregar campo')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                        'equipment' => 'info',
                        'phones' => 'info',
                        'computers' => 'success',
                        'space' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'equipment' => __('Equipamiento / Maquinaria'),
                        'phones' => __('Celulares'),
                        'computers' => __('Cómputo'),
                        'space' => __('Espacio / Infraestructura'),
                        default => $state,
                    }),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'disposed' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => __('Activo'),
                        'maintenance' => __('En mantenimiento'),
                        'disposed' => __('Dado de baja'),
                    }),
                TextColumn::make('acquired_at')
                    ->label(__('Adquisición'))
                    ->date(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options([
                        'active' => __('Activo'),
                        'maintenance' => __('En mantenimiento'),
                        'disposed' => __('Dado de baja'),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('edit_assets')),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('delete_assets')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('delete_assets')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }
}
