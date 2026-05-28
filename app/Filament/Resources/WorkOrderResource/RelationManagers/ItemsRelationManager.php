<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    ->label('Insumo / Repuesto')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('unit_price')
                    ->label('Precio unitario')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Textarea::make('description')
                    ->label('Nota'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('item.name')
                    ->label('Insumo'),
                TextColumn::make('quantity')
                    ->label('Cantidad'),
                TextColumn::make('unit_price')
                    ->label('Precio unitario')
                    ->numeric(thousandsSeparator: '.'),
                TextColumn::make('description')
                    ->label('Nota')
                    ->limit(30),
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
