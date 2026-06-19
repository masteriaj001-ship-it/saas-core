<?php

declare(strict_types=1);

namespace App\Filament\Resources\BudgetResource\Pages;

use App\Enums\BudgetStatusEnum;
use App\Filament\Resources\BudgetResource;
use App\Modules\Budget\Models\Budget;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewBudget extends ViewRecord
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label(__('Enviar'))
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (Budget $record): bool => $record->status === BudgetStatusEnum::Draft)
                ->action(function (Budget $record): void {
                    $record->update([
                        'status' => BudgetStatusEnum::Sent,
                        'sent_at' => now(),
                    ]);
                    Notification::make()->title(__('Presupuesto enviado'))->success()->send();
                }),

            Action::make('approve')
                ->label(__('Aprobar'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Budget $record): bool => $record->status === BudgetStatusEnum::Sent)
                ->action(function (Budget $record): void {
                    $record->update([
                        'status' => BudgetStatusEnum::Approved,
                        'approved_at' => now(),
                        'responded_at' => now(),
                    ]);
                    Notification::make()->title(__('Presupuesto aprobado — generando orden'))->success()->send();
                }),

            Action::make('reject')
                ->label(__('Rechazar'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Budget $record): bool => $record->status === BudgetStatusEnum::Sent)
                ->form([
                    Textarea::make('rejection_reason')
                        ->label(__('Motivo de rechazo'))
                        ->required(),
                ])
                ->action(function (Budget $record, array $data): void {
                    $record->update([
                        'status' => BudgetStatusEnum::Rejected,
                        'rejected_at' => now(),
                        'responded_at' => now(),
                        'rejection_reason' => $data['rejection_reason'],
                    ]);
                    Notification::make()->title(__('Presupuesto rechazado'))->success()->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Cliente'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contact_name')->label(__('Nombre')),
                        TextEntry::make('contact_phone')->label(__('Teléfono')),
                        TextEntry::make('contact_email')->label(__('Email')),
                    ]),
                Section::make(__('Vehículo'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('vehicle_data.make')->label(__('Marca')),
                        TextEntry::make('vehicle_data.model')->label(__('Modelo')),
                        TextEntry::make('vehicle_data.plate')->label(__('Placa')),
                        TextEntry::make('vehicle_data.year')->label(__('Año')),
                        TextEntry::make('vehicle_data.color')->label(__('Color')),
                    ]),
                Section::make(__('Resumen'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')->label(__('Subtotal'))->money('COP'),
                        TextEntry::make('discount_total')->label(__('Descuento'))->money('COP'),
                        TextEntry::make('tax_total')->label(__('Impuestos'))->money('COP'),
                        TextEntry::make('grand_total')->label(__('Total'))->money('COP'),
                    ]),
                Section::make(__('Tracking'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->label(__('Estado'))->badge(),
                        TextEntry::make('sent_at')->label(__('Enviado'))->dateTime(),
                        TextEntry::make('responded_at')->label(__('Respondido'))->dateTime(),
                    ]),
            ]);
    }
}
