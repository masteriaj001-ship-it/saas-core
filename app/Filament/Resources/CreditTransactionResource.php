<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CreditTransactionResource\Pages\ListCreditTransactions;
use App\Modules\Facturacion\Models\CreditTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CreditTransactionResource extends Resource
{
    protected static ?string $model = CreditTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?int $navigationSort = 13;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Transacciones de Crédito');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Facturación');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('creditAccount.contact.name')
                    ->label(__('Cliente'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'charge' => 'danger',
                        'payment' => 'success',
                        'charge_reverse' => 'warning',
                        'payment_reversal' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label(__('Monto'))
                    ->sortable()
                    ->money('COP'),
                TextColumn::make('due_date')
                    ->label(__('Vence'))
                    ->date()
                    ->sortable()
                    ->color(fn (CreditTransaction $record): string => (
                        ! $record->paid_at && $record->due_date && $record->due_date->isPast()
                    ) ? 'danger' : null),
                TextColumn::make('invoice.document_number')
                    ->label(__('Factura'))
                    ->sortable(),
                TextColumn::make('reference')
                    ->label(__('Referencia'))
                    ->limit(30),
                TextColumn::make('created_at')
                    ->label(__('Fecha'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Tipo'))
                    ->options([
                        'charge' => __('Cargo'),
                        'payment' => __('Pago'),
                        'charge_reverse' => __('Reversa de Cargo'),
                        'payment_reversal' => __('Reversa de Pago'),
                    ]),
            ])
            ->actions([])
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
            'index' => ListCreditTransactions::route('/'),
        ];
    }
}
