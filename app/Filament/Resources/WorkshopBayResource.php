<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WorkshopBayResource\Pages\CreateWorkshopBay;
use App\Filament\Resources\WorkshopBayResource\Pages\EditWorkshopBay;
use App\Filament\Resources\WorkshopBayResource\Pages\ListWorkshopBays;
use App\Modules\Talleres\Models\WorkshopBay;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkshopBayResource extends Resource
{
    protected static ?string $model = WorkshopBay::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return 'Bahías';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Talleres';
    }

    public static function getModelLabel(): string
    {
        return 'Bahía';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Bahías';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Información General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('Código'))
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        Select::make('location_id')
                            ->label(__('Ubicación'))
                            ->relationship('location', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('type')
                            ->label(__('Tipo'))
                            ->required()
                            ->default('standard')
                            ->options([
                                'standard' => __('Estándar'),
                                'lift' => __('Elevador'),
                                'paint' => __('Pintura'),
                                'diagnostic' => __('Diagnóstico'),
                            ]),
                    ]),
                Section::make(__('Metadatos'))
                    ->schema([
                        KeyValue::make('metadata')
                            ->label(__('Metadatos'))
                            ->keyLabel(__('Clave'))
                            ->valueLabel(__('Valor')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('Código'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label(__('Ubicación'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'standard' => 'gray',
                        'lift' => 'info',
                        'paint' => 'warning',
                        'diagnostic' => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'standard' => __('Estándar'),
                        'lift' => __('Elevador'),
                        'paint' => __('Pintura'),
                        'diagnostic' => __('Diagnóstico'),
                        default => $state,
                    }),
                IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label(__('Estado'))
                    ->options([
                        1 => __('Activo'),
                        0 => __('Inactivo'),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListWorkshopBays::route('/'),
            'create' => CreateWorkshopBay::route('/create'),
            'edit' => EditWorkshopBay::route('/{record}/edit'),
        ];
    }
}
