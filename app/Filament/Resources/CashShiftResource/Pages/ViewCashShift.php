<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashShiftResource\Pages;

use App\Filament\Resources\CashShiftResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewCashShift extends ViewRecord
{
    protected static string $resource = CashShiftResource::class;

    public function infolist(Schema $schema): Schema
    {
        $record = $this->record;

        return $schema
            ->schema([
                Section::make(__('Datos del Turno'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('opened_at')->label(__('Apertura'))->dateTime(),
                        TextEntry::make('closed_at')->label(__('Cierre'))->dateTime(),
                        TextEntry::make('user.name')->label(__('Abierto por')),
                        TextEntry::make('initial_amount')->label(__('Monto Inicial'))->money(),
                    ]),
                Section::make(__('Resumen'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('total_sales')->label(__('Ventas'))->money(),
                        TextEntry::make('total_expenses')->label(__('Gastos'))->money(),
                        TextEntry::make('net_amount')->label(__('Neto'))->money(),
                    ]),
                Section::make(__('Estadísticas'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('cashMovements_count')->label(__('Movimientos'))->count(),
                        TextEntry::make('status')->label(__('Estado'))->badge(),
                    ]),
            ]);
    }
}
