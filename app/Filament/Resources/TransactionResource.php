<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Resources\TransactionResource\Pages\EditTransaction;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\RelationManagers\ItemsRelationManager;
use App\Models\Transaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Transacciones';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Facturación';
    }

    public static function getModelLabel(): string
    {
        return 'Transacción';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Transacciones';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('General')
                    ->columns(3)
                    ->schema([
                        Select::make('type')
                            ->label('Tipo')
                            ->required()
                            ->live()
                            ->options([
                                'sale' => 'Venta',
                                'purchase' => 'Compra',
                            ])
                            ->afterStateUpdated(function (callable $set) {
                                $set('contact_id', null);
                            }),
                        Select::make('contact_id')
                            ->label('Contacto')
                            ->required()
                            ->relationship('contact', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('status')
                            ->label('Estado')
                            ->required()
                            ->disabled()
                            ->options([
                                'draft' => 'Borrador',
                                'issued' => 'Emitida',
                                'cancelled' => 'Anulada',
                            ]),
                    ]),
                Section::make('Facturación Electrónica')
                    ->columns(2)
                    ->visible(fn (callable $get) => $get('type') === 'sale')
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('N° Factura')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('resolution_number')
                            ->label('Resolución DIAN')
                            ->maxLength(50),
                        TextInput::make('cufe')
                            ->label('CUFE')
                            ->maxLength(100),
                    ]),
                Section::make('Totales')
                    ->columns(4)
                    ->schema([
                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->disabled()
                            ->default(0),
                        TextInput::make('total_tax')
                            ->label('Total IVA')
                            ->numeric()
                            ->disabled()
                            ->default(0),
                        TextInput::make('total_retentions')
                            ->label('Retenciones')
                            ->numeric()
                            ->default(0),
                        TextInput::make('total_amount')
                            ->label('Total Neto')
                            ->numeric()
                            ->disabled()
                            ->default(0),
                    ]),
                Section::make('Pago')
                    ->columns(2)
                    ->schema([
                        Select::make('payment_method')
                            ->label('Método de pago')
                            ->options([
                                'cash' => 'Efectivo',
                                'transfer' => 'Transferencia',
                                'card' => 'Tarjeta',
                                'check' => 'Cheque',
                                'credit' => 'Crédito',
                            ]),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('N° Factura')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sale' => 'info',
                        'purchase' => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sale' => 'Venta',
                        'purchase' => 'Compra',
                    }),
                TextColumn::make('contact.name')
                    ->label('Contacto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'issued' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'issued' => 'Emitida',
                        'cancelled' => 'Anulada',
                    }),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->numeric(thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('cufe')
                    ->label('CUFE')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'sale' => 'Venta',
                        'purchase' => 'Compra',
                    ]),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'issued' => 'Emitida',
                        'cancelled' => 'Anulada',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Transaction $record): bool => auth()->user()->can('edit_transactions') && $record->canEdit()
                    ),
                DeleteAction::make()
                    ->visible(fn (Transaction $record): bool => auth()->user()->can('delete_transactions') && $record->canEdit()
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('delete_transactions')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }
}
