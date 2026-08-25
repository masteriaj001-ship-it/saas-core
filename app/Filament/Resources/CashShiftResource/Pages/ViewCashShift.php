<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashShiftResource\Pages;

use App\Filament\Resources\CashShiftResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCashShift extends ViewRecord
{
    protected static string $resource = CashShiftResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Datos del Turno'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('opened_at')->label(__('Apertura'))->dateTime(),
                        TextEntry::make('closed_at')->label(__('Cierre'))->dateTime(),
                        TextEntry::make('openedBy.name')->label(__('Abierto por')),
                        TextEntry::make('closedBy.name')->label(__('Cerrado por')),
                        TextEntry::make('initial_amount')->label(__('Monto Inicial'))->money('COP'),
                    ]),
                Section::make(__('Resumen'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('totalSales')->label(__('Ventas'))->money('COP'),
                        TextEntry::make('totalExpenses')->label(__('Gastos'))->money('COP'),
                        TextEntry::make('netAmount')->label(__('Neto'))->money('COP'),
                    ]),
                Section::make(__('Cierre'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('expected_cash')->label(__('Efectivo Esperado'))->money('COP'),
                        TextEntry::make('actual_cash')->label(__('Efectivo Contado'))->money('COP'),
                        TextEntry::make('difference')->label(__('Diferencia'))->money('COP'),
                    ]),
                Section::make(__('Detalle'))
                    ->schema([
                        TextEntry::make('status')->label(__('Estado'))->badge(),
                        TextEntry::make('notes')->label(__('Notas')),
                        TextEntry::make('cashMovements_count')->label(__('Total Movimientos'))->count(),
                    ]),
            ]);
    }
}
