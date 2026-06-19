<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Enums\WorkOrderStatusEnum;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\WorkOrderResource;
use App\Modules\Facturacion\Actions\GenerateInvoiceFromWorkOrderAction;
use App\Modules\Talleres\Actions\RequestQuoteApprovalAction;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('request_quote_approval')
                ->label(__('Solicitar Aprobación'))
                ->icon('heroicon-o-check-badge')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatusEnum::Quoted)
                ->modalWidth('xl')
                ->modalHeading(__('Enlace de aprobación'))
                ->modalDescription(__('Compartí este enlace con el cliente para que apruebe el presupuesto.'))
                ->form(fn (): array => [
                    TextInput::make('approval_url')
                        ->label(__('Link del presupuesto'))
                        ->default(fn (): string => app(RequestQuoteApprovalAction::class)
                            ->execute($this->record))
                        ->readOnly()
                        ->extraAttributes(['onclick' => 'this.select()']),
                ])
                ->action(function (array $data): void {
                    Notification::make()
                        ->title(__('Enlace generado correctamente'))
                        ->success()
                        ->send();
                }),
            Action::make('generate_invoice')
                ->label(__('Generar Factura'))
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('¿Generar factura?'))
                ->modalDescription(__('Se creará una factura borrador con los ítems de esta orden.'))
                ->visible(fn (): bool => $this->record->items()->exists())
                ->action(function (): void {
                    $invoice = app(GenerateInvoiceFromWorkOrderAction::class)
                        ->execute($this->record);

                    $this->redirect(
                        InvoiceResource::getUrl('edit', ['record' => $invoice])
                    );
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
