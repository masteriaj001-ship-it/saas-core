<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label(__('Descargar PDF'))
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('invoices.pdf', $this->record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
