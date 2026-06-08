<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\WorkOrderResource;
use App\Modules\Facturacion\Actions\GenerateInvoiceFromWorkOrderAction;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
