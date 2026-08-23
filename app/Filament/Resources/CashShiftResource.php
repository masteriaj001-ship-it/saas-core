<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CashShiftResource\Pages\ListCashShifts;
use App\Modules\Caja\Models\CashShift;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CashShiftResource extends Resource
{
    protected static ?string $model = CashShift::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('Turnos de Caja');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Caja');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Datos del Turno'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('initial_amount')
                            ->label(__('Monto Inicial'))
                            ->numeric()
                            ->required(),
                        Select::make('status')
                            ->label(__('Estado'))
                            ->options([
                                'open' => __('Abierto'),
                                'closed' => __('Cerrado'),
                            ])
                            ->required(),
                    ]),
                Section::make(__('Resumen'))
                    ->schema([
                        TextInput::make('total_sales')
                            ->label(__('Ventas Totales'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('total_expenses')
                            ->label(__('Gastos Totales'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('net_amount')
                            ->label(__('Neto'))
                            ->numeric()
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(CashShift::query()->with('cashMovements'))
            ->columns([
                TextColumn::make('opened_at')
                    ->label(__('Apertura'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label(__('Cierre'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('Abierto por'))
                    ->sortable(),
                TextColumn::make('initial_amount')
                    ->label(__('Monto Inicial'))
                    ->numeric(thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('total_sales')
                    ->label(__('Ventas'))
                    ->numeric(thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('total_expenses')
                    ->label(__('Gastos'))
                    ->numeric(thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('net_amount')
                    ->label(__('Neto'))
                    ->numeric(thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options([
                        'open' => __('Abierto'),
                        'closed' => __('Cerrado'),
                    ]),
            ])
            ->actions([
                // View action would be added here
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashShifts::route('/'),
        ];
    }
}
