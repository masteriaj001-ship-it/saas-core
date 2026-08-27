<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceCatalogResource\Pages\CreateServiceCatalog;
use App\Filament\Resources\ServiceCatalogResource\Pages\EditServiceCatalog;
use App\Filament\Resources\ServiceCatalogResource\Pages\ListServiceCatalogs;
use App\Modules\Talleres\Models\ServiceCatalog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceCatalogResource extends Resource
{
    protected static ?string $model = ServiceCatalog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return 'Catálogo de Servicios';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gestión';
    }

    public static function getModelLabel(): string
    {
        return 'Catálogo de Servicios';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Catálogos de Servicios';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nombre del servicio'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('base_price')
                            ->label(__('Precio base'))
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01),
                        TextInput::make('estimated_minutes')
                            ->label(__('Duración estimada (min)'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix('min'),
                        Toggle::make('is_active')
                            ->label(__('Activo'))
                            ->default(true),
                        Textarea::make('description')
                            ->label(__('Descripción'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Servicio'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_price')
                    ->label(__('Precio'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('estimated_minutes')
                    ->label(__('Duración'))
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state} min" : '—'),
                TextColumn::make('is_active')
                    ->label(__('Activo'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Sí') : __('No')),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => ListServiceCatalogs::route('/'),
            'create' => CreateServiceCatalog::route('/create'),
            'edit' => EditServiceCatalog::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }
}
