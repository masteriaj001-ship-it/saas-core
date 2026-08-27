<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages\CreateWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\EditWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\ListWarehouses;
use App\Models\Item;
use App\Modules\Inventario\Actions\AdjustItemStockAction;
use App\Modules\Inventario\Actions\TransferStockAction;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Exceptions\InsufficientStockException;
use App\Modules\Inventario\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Bodegas';
    }

    public static function getNavigationGroup(): string
    {
        return 'Inventario';
    }

    public static function getModelLabel(): string
    {
        return 'Bodega';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Bodegas';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('Código'))
                            ->required()
                            ->maxLength(30),
                        TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        Select::make('location_id')
                            ->label(__('Sede'))
                            ->relationship('location', 'name')
                            ->nullable()
                            ->searchable(),
                        Textarea::make('address')
                            ->label(__('Dirección'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_default')
                            ->label(__('Bodega predeterminada'))
                            ->default(false),
                        Toggle::make('is_active')
                            ->label(__('Activa'))
                            ->default(true),
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
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label(__('Sede'))
                    ->searchable(),
                TextColumn::make('is_default')
                    ->label(__('Predeterminada'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No'),
                TextColumn::make('is_active')
                    ->label(__('Activa'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No'),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('transferStock')
                    ->label(__('Transferir Stock'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->form([
                        Select::make('item_id')
                            ->label(__('Artículo'))
                            ->searchable()
                            ->required()
                            ->placeholder(__('Busque un artículo...'))
                            ->getSearchResultsUsing(fn (string $search): array => Item::where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->limit(20)
                                ->pluck('name', 'id')
                                ->toArray())
                            ->getOptionLabelUsing(fn (string $value): ?string => Item::find($value)?->name),
                        Select::make('destination_warehouse_id')
                            ->label(__('Bodega destino'))
                            ->options(fn (): array => Warehouse::where('is_active', true)->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')
                            ->label(__('Cantidad'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('reason')
                            ->label(__('Motivo'))
                            ->required()
                            ->maxLength(100),
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(2),
                    ])
                    ->action(function (array $data, Warehouse $record): void {
                        $item = Item::findOrFail($data['item_id']);
                        $destination = Warehouse::findOrFail($data['destination_warehouse_id']);

                        try {
                            app(TransferStockAction::class)->execute(
                                item: $item,
                                origin: $record,
                                destination: $destination,
                                quantity: (int) $data['quantity'],
                                reason: $data['reason'],
                                notes: $data['notes'] ?? null,
                                user: auth()->user(),
                            );

                            Notification::make()
                                ->success()
                                ->title(__('Transferencia realizada.'))
                                ->send();
                        } catch (InsufficientStockException $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('Stock insuficiente'))
                                ->body(__('Disponible en origen: :available, solicitado: :requested', [
                                    'available' => $e->available,
                                    'requested' => $e->requested,
                                ]))
                                ->persistent()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('Error'))
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (): bool => auth()->user()?->can('edit_warehouses') ?? false),
                Action::make('adjustStock')
                    ->label(__('Ajustar Stock'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Select::make('item_id')
                            ->label(__('Artículo'))
                            ->options(fn (Warehouse $record): array => Item::where('tenant_id', $record->tenant_id)
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->required(),
                        Select::make('movement_type')
                            ->label(__('Tipo'))
                            ->options([
                                'entry' => __('Entrada'),
                                'exit' => __('Salida'),
                                'adjustment_in' => __('Ajuste (+)'),
                                'adjustment_out' => __('Ajuste (-)'),
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('quantity')
                            ->label(__('Cantidad'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('reason')
                            ->label(__('Motivo'))
                            ->required()
                            ->maxLength(100),
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(2),
                    ])
                    ->action(function (array $data, Warehouse $record): void {
                        $item = Item::findOrFail($data['item_id']);
                        $movementType = MovementTypeEnum::from($data['movement_type']);

                        try {
                            app(AdjustItemStockAction::class)->execute(
                                item: $item,
                                warehouse: $record,
                                movementType: $movementType,
                                quantity: (int) $data['quantity'],
                                reason: $data['reason'],
                                notes: $data['notes'] ?? null,
                                user: auth()->user(),
                            );

                            Notification::make()
                                ->success()
                                ->title(__('Stock ajustado correctamente.'))
                                ->send();
                        } catch (InsufficientStockException $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('Stock insuficiente'))
                                ->body(__('Disponible: :available, solicitado: :requested', [
                                    'available' => $e->available,
                                    'requested' => $e->requested,
                                ]))
                                ->persistent()
                                ->send();
                        }
                    })
                    ->visible(fn (): bool => auth()->user()?->can('edit_warehouses') ?? false),
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('edit_warehouses') ?? false),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('delete_warehouses') ?? false),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_warehouses') ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }
}
