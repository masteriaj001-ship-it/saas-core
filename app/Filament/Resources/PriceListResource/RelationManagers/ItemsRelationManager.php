<?php

declare(strict_types=1);

namespace App\Filament\Resources\PriceListResource\RelationManagers;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('item_id')
                            ->label(__('Item'))
                            ->relationship('item', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('price')
                            ->label(__('Precio'))
                            ->required()
                            ->numeric(),
                        TextInput::make('min_quantity')
                            ->label(__('Cantidad Mínima'))
                            ->numeric()
                            ->default(1),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item.name')
            ->columns([
                TextColumn::make('item.sku')
                    ->label(__('SKU'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label(__('Item'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('Precio'))
                    ->sortable()
                    ->money('COP'),
                TextColumn::make('min_quantity')
                    ->label(__('Cantidad Mínima'))
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
