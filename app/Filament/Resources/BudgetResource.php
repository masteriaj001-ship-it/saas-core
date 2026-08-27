<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BudgetStatusEnum;
use App\Filament\Resources\BudgetResource\Pages\CreateBudget;
use App\Filament\Resources\BudgetResource\Pages\EditBudget;
use App\Filament\Resources\BudgetResource\Pages\ListBudgets;
use App\Filament\Resources\BudgetResource\Pages\ViewBudget;
use App\Modules\Budget\Models\Budget;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Presupuestos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gestión';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Datos del Cliente'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label(__('Teléfono'))
                            ->tel()
                            ->maxLength(40),
                        TextInput::make('contact_email')
                            ->label(__('Email'))
                            ->email()
                            ->maxLength(255),
                    ]),
                Section::make(__('Vehículo'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('vehicle_data.make')
                            ->label(__('Marca')),
                        TextInput::make('vehicle_data.model')
                            ->label(__('Modelo')),
                        TextInput::make('vehicle_data.plate')
                            ->label(__('Placa'))
                            ->maxLength(10),
                        TextInput::make('vehicle_data.year')
                            ->label(__('Año'))
                            ->maxLength(4),
                        TextInput::make('vehicle_data.color')
                            ->label(__('Color')),
                    ]),
                Section::make(__('Items'))
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(6)->schema([
                                    TextInput::make('description')
                                        ->label(__('Descripción'))
                                        ->required()
                                        ->columnSpan(2),
                                    TextInput::make('quantity')
                                        ->label(__('Cantidad'))
                                        ->numeric()
                                        ->default(1)
                                        ->required(),
                                    TextInput::make('unit_price')
                                        ->label(__('Precio Unit.'))
                                        ->numeric()
                                        ->required(),
                                    TextInput::make('discount')
                                        ->label(__('Dto.'))
                                        ->numeric()
                                        ->default(0),
                                    TextInput::make('total')
                                        ->label(__('Total'))
                                        ->numeric()
                                        ->disabled(),
                                ]),
                            ]),
                    ]),
                Section::make(__('Notas'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label(__('Cliente'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle_data')
                    ->label(__('Vehículo'))
                    ->state(fn (Budget $record): string => $record->vehicle_data['plate'] ?? $record->vehicle_data['model'] ?? '—'),
                TextColumn::make('grand_total')
                    ->label(__('Total'))
                    ->numeric(thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge(),
                TextColumn::make('sent_at')
                    ->label(__('Enviado'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options(BudgetStatusEnum::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgets::route('/'),
            'create' => CreateBudget::route('/create'),
            'view' => ViewBudget::route('/{record}'),
            'edit' => EditBudget::route('/{record}/edit'),
        ];
    }
}
