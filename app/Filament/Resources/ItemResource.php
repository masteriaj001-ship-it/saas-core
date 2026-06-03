<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages\CreateItem;
use App\Filament\Resources\ItemResource\Pages\EditItem;
use App\Filament\Resources\ItemResource\Pages\ListItems;
use App\Models\Item;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Repuestos/Insumos');
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
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        Select::make('item_type')
                            ->label(__('Tipo'))
                            ->required()
                            ->default('product')
                            ->options([
                                'spare' => __('Repuesto'),
                                'product' => __('Producto'),
                                'service' => __('Servicio'),
                                'raw_material' => __('Materia prima'),
                            ]),
                        Select::make('unit')
                            ->label(__('Unidad'))
                            ->required()
                            ->default('unit')
                            ->options([
                                'unit' => __('Unidad'),
                                'kg' => __('Kg'),
                                'lt' => __('Litro'),
                                'm' => __('Metro'),
                                'piece' => __('Pieza'),
                            ]),
                        TextInput::make('price')
                            ->label(__('Precio'))
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('cost')
                            ->label(__('Costo'))
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('stock')
                            ->label(__('Stock'))
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->required(),
                        TextInput::make('min_stock')
                            ->label(__('Stock mínimo'))
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->required(),
                    ]),
                Section::make(__('Descripción'))
                    ->schema([
                        Textarea::make('description')
                            ->label(__('Descripción'))
                            ->rows(3),
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
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'spare' => 'info',
                        'product' => 'success',
                        'service' => 'warning',
                        'raw_material' => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'spare' => __('Repuesto'),
                        'product' => __('Producto'),
                        'service' => __('Servicio'),
                        'raw_material' => __('Materia prima'),
                    }),
                TextColumn::make('stock')
                    ->label(__('Stock'))
                    ->numeric()
                    ->sortable()
                    ->color(fn (Item $record): string => $record->stock < $record->min_stock ? 'danger' : 'success'),
                TextColumn::make('min_stock')
                    ->label(__('Stock mínimo'))
                    ->numeric(),
                TextColumn::make('price')
                    ->label(__('Precio'))
                    ->numeric(thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('item_type')
                    ->label(__('Tipo'))
                    ->options([
                        'spare' => __('Repuesto'),
                        'product' => __('Producto'),
                        'service' => __('Servicio'),
                        'raw_material' => __('Materia prima'),
                    ]),
                Filter::make('stock_below_min')
                    ->label(__('Stock bajo mínimo'))
                    ->query(fn (Builder $query) => $query->whereColumn('stock', '<', 'min_stock')),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('edit_items')),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('delete_items')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('delete_items')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }
}
