<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\InvoiceStatusEnum;
use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Resources\InvoiceResource\Pages\EditInvoice;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Talleres\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return 'Facturas';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Facturación';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Encabezado'))
                    ->columns(2)
                    ->schema([
                        Select::make('document_type')
                            ->label(__('Tipo de documento'))
                            ->required()
                            ->options([
                                'invoice' => __('Factura de Venta'),
                                'credit_note' => __('Nota Crédito'),
                            ]),
                        Select::make('status')
                            ->label(__('Estado'))
                            ->required()
                            ->default('draft')
                            ->options([
                                'draft' => __('Borrador'),
                                'issued' => __('Emitida'),
                                'paid' => __('Pagada'),
                                'cancelled' => __('Anulada'),
                            ]),
                        Select::make('contact_id')
                            ->label(__('Cliente'))
                            ->relationship('contact', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('work_order_id')
                            ->label(__('Orden de trabajo'))
                            ->relationship('workOrder', 'code')
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(
                                fn (WorkOrder $record) => "{$record->code} — {$record->title}",
                            )
                            ->nullable(),
                        DateTimePicker::make('issued_at')
                            ->label(__('Fecha de emisión'))
                            ->default(now()),
                        DateTimePicker::make('due_at')
                            ->label(__('Fecha de vencimiento')),
                    ]),
                Section::make(__('Ítems'))
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                TextInput::make('description')
                                    ->label(__('Descripción'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('quantity')
                                    ->label(__('Cantidad'))
                                    ->numeric()
                                    ->default(1)
                                    ->step(0.0001)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItem($get, $set)),
                                TextInput::make('unit_price')
                                    ->label(__('Precio unitario'))
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItem($get, $set)),
                                TextInput::make('discount')
                                    ->label(__('Descuento'))
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItem($get, $set)),
                                TextInput::make('tax_rate')
                                    ->label(__('IVA %'))
                                    ->numeric()
                                    ->default(19.00)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItem($get, $set)),
                                TextInput::make('subtotal')
                                    ->label(__('Subtotal'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->step(0.01),
                                TextInput::make('total')
                                    ->label(__('Total'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->step(0.01),
                            ])
                            ->columns(4)
                            ->columnSpanFull()
                            ->addActionLabel(__('Agregar ítem')),
                    ]),
                Section::make(__('Totales'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('subtotal')
                            ->label(__('Subtotal'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->step(0.01),
                        TextInput::make('discount_total')
                            ->label(__('Descuento total'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->step(0.01),
                        TextInput::make('tax_total')
                            ->label(__('IVA total'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->step(0.01),
                        TextInput::make('grand_total')
                            ->label(__('Total general'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->step(0.01)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label(__('Documento'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact.name')
                    ->label(__('Cliente'))
                    ->searchable(),
                TextColumn::make('workOrder.code')
                    ->label(__('OT'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (InvoiceStatusEnum $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn (InvoiceStatusEnum $state): string => $state->getLabel()),
                TextColumn::make('grand_total')
                    ->label(__('Total'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label(__('Emitida'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Creada'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('download_pdf')
                    ->label(__('PDF'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Invoice $record): string => route('invoices.pdf', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }

    protected static function recalculateItem(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discount = (float) ($get('discount') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);

        $subtotal = ($quantity * $unitPrice) - $discount;
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $set('subtotal', round($subtotal, 2));
        $set('tax_amount', round($taxAmount, 2));
        $set('total', round($total, 2));
    }
}
