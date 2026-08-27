<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PriceListResource\Pages\CreatePriceList;
use App\Filament\Resources\PriceListResource\Pages\EditPriceList;
use App\Filament\Resources\PriceListResource\Pages\ListPriceLists;
use App\Filament\Resources\PriceListResource\RelationManagers\ItemsRelationManager;
use App\Modules\Inventario\Models\PriceList;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return 'Listas de Precios';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getModelLabel(): string
    {
        return 'Lista de Precios';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Listas de Precios';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Información General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('Descripción'))
                            ->rows(3),
                    ]),
                Section::make(__('Configuración'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_default')
                            ->label(__('Por Defecto')),
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
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('Descripción'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label(__('Por Defecto'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label(__('Items'))
                    ->counts('items')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
