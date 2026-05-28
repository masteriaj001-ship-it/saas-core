<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransactionResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('item_id')
                    ->label('Producto / Servicio')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                        if (!$state) {
                            return;
                        }
                        $item = \App\Models\Item::find($state);
                        if ($item) {
                            $set('unit_price', (string) $item->price);
                        }
                    }),
                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        self::recalculateItem($set, $get);
                    }),
                TextInput::make('unit_price')
                    ->label('Precio unitario')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        self::recalculateItem($set, $get);
                    }),
                Select::make('tax_rate')
                    ->label('IVA %')
                    ->options([
                        0  => '0% (Exento)',
                        5  => '5%',
                        19 => '19%',
                    ])
                    ->default(19)
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        self::recalculateItem($set, $get);
                    }),
                TextInput::make('discount_amount')
                    ->label('Descuento')
                    ->numeric()
                    ->default(0)
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        self::recalculateItem($set, $get);
                    }),
            ]);
    }

    protected static function recalculateItem(callable $set, callable $get): void
    {
        $qty = (float) ($get('quantity') ?? 1);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);
        $discount = (float) ($get('discount_amount') ?? 0);

        $subtotal = $qty * $unitPrice;
        $taxAmount = $subtotal * ($taxRate / 100);
        $totalItemAmount = $subtotal + $taxAmount - $discount;

        $set('tax_amount', round($taxAmount, 2));
        $set('total_item_amount', round($totalItemAmount, 2));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('item.name')
                    ->label('Producto / Servicio'),
                TextColumn::make('quantity')
                    ->label('Cantidad'),
                TextColumn::make('unit_price')
                    ->label('P. Unitario')
                    ->numeric(thousandsSeparator: '.'),
                TextColumn::make('tax_rate')
                    ->label('IVA %')
                    ->formatStateUsing(fn ($state): string => $state . '%'),
                TextColumn::make('tax_amount')
                    ->label('IVA')
                    ->numeric(thousandsSeparator: '.'),
                TextColumn::make('discount_amount')
                    ->label('Dto.')
                    ->numeric(thousandsSeparator: '.'),
                TextColumn::make('total_item_amount')
                    ->label('Total')
                    ->numeric(thousandsSeparator: '.'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
