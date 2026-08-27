<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
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
                        TextInput::make('description')
                            ->label(__('Descripción'))
                            ->maxLength(255),
                        TextInput::make('quantity')
                            ->label(__('Cantidad'))
                            ->required()
                            ->numeric(),
                        TextInput::make('received_quantity')
                            ->label(__('Recibido'))
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        TextInput::make('unit_cost')
                            ->label(__('Costo Unitario'))
                            ->required()
                            ->numeric(),
                        TextInput::make('tax_rate')
                            ->label(__('IVA %'))
                            ->numeric()
                            ->default(19),
                        TextInput::make('batch_number')
                            ->label(__('Lote'))
                            ->maxLength(100),
                        DatePicker::make('expires_at')
                            ->label(__('Vencimiento')),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('item.sku')
                    ->label(__('SKU'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label(__('Item'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('Descripción'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('Cantidad'))
                    ->sortable(),
                TextColumn::make('received_quantity')
                    ->label(__('Recibido'))
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label(__('Costo'))
                    ->sortable()
                    ->money('COP'),
                TextColumn::make('subtotal')
                    ->label(__('Subtotal'))
                    ->sortable()
                    ->money('COP'),
                TextColumn::make('batch_number')
                    ->label(__('Lote'))
                    ->searchable()
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
