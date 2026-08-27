<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\ItemsRelationManager;
use App\Modules\Inventario\Enums\PurchaseStatus;
use App\Modules\Inventario\Models\PurchaseOrder;
use App\Modules\Inventario\Services\PurchaseService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 11;

    public static function getNavigationLabel(): string
    {
        return 'Órdenes de Compra';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getModelLabel(): string
    {
        return 'Orden de Compra';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Órdenes de Compra';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Información General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('Código'))
                            ->required()
                            ->maxLength(50),
                        Select::make('supplier_id')
                            ->label(__('Proveedor'))
                            ->relationship('supplier', 'trade_name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('warehouse_id')
                            ->label(__('Bodega'))
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make(__('Fechas'))
                    ->columns(2)
                    ->schema([
                        DatePicker::make('expected_at')
                            ->label(__('Fecha Esperada'))
                            ->required(),
                        Select::make('status')
                            ->label(__('Estado'))
                            ->required()
                            ->default(PurchaseStatus::DRAFT)
                            ->options(PurchaseStatus::class),
                    ]),
                Section::make(__('Notas'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(3),
                    ]),
                Section::make(__('Metadatos'))
                    ->schema([
                        KeyValue::make('metadata')
                            ->label(__('Metadatos'))
                            ->keyLabel(__('Clave'))
                            ->valueLabel(__('Valor')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('Código'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.trade_name')
                    ->label(__('Proveedor'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label(__('Bodega'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (PurchaseStatus $state): string => match ($state) {
                        PurchaseStatus::DRAFT => 'gray',
                        PurchaseStatus::ORDERED => 'info',
                        PurchaseStatus::PARTIAL => 'warning',
                        PurchaseStatus::RECEIVED => 'success',
                        PurchaseStatus::CANCELLED => 'danger',
                    }),
                TextColumn::make('total')
                    ->label(__('Total'))
                    ->sortable()
                    ->money('COP'),
                TextColumn::make('expected_at')
                    ->label(__('Fecha Esperada'))
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options(PurchaseStatus::class),
            ])
            ->actions([
                EditAction::make(),
                Action::make('receive')
                    ->label(__('Recibir'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [
                        PurchaseStatus::ORDERED,
                        PurchaseStatus::PARTIAL,
                    ]))
                    ->form([
                        Repeater::make('receipts')
                            ->label(__('Ítems a Recibir'))
                            ->schema([
                                Select::make('item_id')
                                    ->label(__('Ítem'))
                                    ->options(fn (PurchaseOrder $record) => $record->items()
                                        ->pluck('item.name', 'item_id')
                                        ->toArray())
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $state, PurchaseOrder $record) {
                                        $poItem = $record->items()->where('item_id', $state)->first();
                                        if ($poItem) {
                                            $set('pending_quantity', $poItem->pendingQuantity());
                                            $set('unit_cost', $poItem->unit_cost);
                                        }
                                    }),
                                TextInput::make('quantity')
                                    ->label(__('Cantidad a Recibir'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                                TextInput::make('pending_quantity')
                                    ->label(__('Pendiente'))
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('unit_cost')
                                    ->label(__('Costo Unitario'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->columns(2)
                            ->required()
                            ->minItems(1),
                    ])
                    ->action(function (PurchaseOrder $record, array $data): void {
                        $record->load('items.item');
                        app(PurchaseService::class)->receive($record, $data['receipts']);

                        Notification::make()
                            ->title(__('Recepción registrada'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
