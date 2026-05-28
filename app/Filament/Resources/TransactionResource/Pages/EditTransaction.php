<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\Transactions\TransactionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')
                ->label('Emitir')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Transaction $record): bool =>
                    auth()->user()->can('edit_transactions') && $record->canIssue()
                )
                ->requiresConfirmation()
                ->action(function (Transaction $record) {
                    app(TransactionService::class)->issue($record);
                    Notification::make()
                        ->success()
                        ->title('Transacción emitida')
                        ->send();
                    $this->refreshFormData(['status', 'cufe']);
                }),
            Action::make('cancel')
                ->label('Anular')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Transaction $record): bool =>
                    auth()->user()->can('edit_transactions') && $record->canCancel()
                )
                ->requiresConfirmation()
                ->action(function (Transaction $record) {
                    app(TransactionService::class)->cancel($record);
                    Notification::make()
                        ->success()
                        ->title('Transacción anulada')
                        ->send();
                    $this->refreshFormData(['status', 'cufe']);
                }),
            DeleteAction::make()
                ->visible(fn (Transaction $record): bool =>
                    auth()->user()->can('delete_transactions') && $record->canEdit()
                ),
        ];
    }
}
