<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Modules\Inventario\Models\Supplier;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return 'Proveedores';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getModelLabel(): string
    {
        return 'Proveedor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Proveedores';
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
                        TextInput::make('trade_name')
                            ->label(__('Razón Comercial'))
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make(__('Condiciones Comerciales'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('payment_terms_days')
                            ->label(__('Días Plazo de Pago'))
                            ->numeric()
                            ->default(30),
                        TextInput::make('lead_time_days')
                            ->label(__('Días de Entrega'))
                            ->numeric()
                            ->default(7),
                        TextInput::make('credit_limit')
                            ->label(__('Límite de Crédito'))
                            ->numeric()
                            ->default(0),
                    ]),
                Section::make(__('Notas'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('Notas'))
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
                TextColumn::make('code')
                    ->label(__('Código'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trade_name')
                    ->label(__('Razón Comercial'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact.name')
                    ->label(__('Contacto'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_terms_days')
                    ->label(__('Plazo'))
                    ->sortable(),
                TextColumn::make('credit_limit')
                    ->label(__('Crédito'))
                    ->sortable()
                    ->money('COP'),
                IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
