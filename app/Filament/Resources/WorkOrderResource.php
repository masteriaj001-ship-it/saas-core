<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WorkOrderResource\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrderResource\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Filament\Resources\WorkOrderResource\RelationManagers\ItemsRelationManager;
use App\Models\Item;
use App\Models\WorkOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('Órdenes de Trabajo');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Gestión');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                Section::make(__('Asociación'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('contact_id')
                            ->label(__('Cliente'))
                            ->relationship('contact', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nombre'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->label(__('Teléfono'))
                                    ->tel(),
                                TextInput::make('tax_id')
                                    ->label(__('Documento / NIT')),
                                Select::make('contact_type')
                                    ->label(__('Tipo'))
                                    ->required()
                                    ->default('client')
                                    ->options(['client' => __('Cliente')])
                                    ->selectablePlaceholder(false),
                            ]),
                        Select::make('asset_id')
                            ->label(__('Dispositivo / Recurso'))
                            ->relationship('asset', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Nombre / Placa'))
                                    ->required()
                                    ->maxLength(255),
                                Select::make('asset_type')
                                    ->label(__('Tipo'))
                                    ->required()
                                    ->default('vehicles')
                                    ->options([
                                        'phones'    => __('Celulares'),
                                        'computers' => __('Cómputo'),
                                        'vehicles'  => __('Vehículos'),
                                    ]),
                                TextInput::make('metadata.marca')
                                    ->label(__('Marca')),
                                TextInput::make('metadata.modelo')
                                    ->label(__('Modelo')),
                            ]),
                    ]),
                Section::make(__('Control'))
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status')
                            ->label(__('Estado'))
                            ->required()
                            ->default('pending')
                            ->options([
                                'pending'     => __('Pendiente'),
                                'in_progress' => __('En progreso'),
                                'completed'   => __('Completada'),
                                'cancelled'   => __('Cancelada'),
                            ]),
                        Select::make('priority')
                            ->label(__('Prioridad'))
                            ->required()
                            ->default('medium')
                            ->options([
                                'low'    => __('Baja'),
                                'medium' => __('Media'),
                                'high'   => __('Alta'),
                            ]),
                    ]),
                Section::make(__('Problema'))
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Título'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('Descripción'))
                            ->rows(4),
                    ]),
                Section::make(__('Fechas'))
                    ->columnSpan(1)
                    ->schema([
                        DatePicker::make('started_at')
                            ->label(__('Fecha programada')),
                        DatePicker::make('completed_at')
                            ->label(__('Fecha de finalización')),
                    ]),
                Section::make(__('Insumos / Repuestos'))
                    ->columnSpan(2)
                    ->description(__('Agregue los repuestos o servicios consumidos en esta orden. Seleccione el item y el precio se sugerirá automáticamente.'))
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('item_id')
                                    ->label(__('Insumo / Repuesto'))
                                    ->placeholder(__('Buscar repuesto o servicio...'))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->columnSpan(4)
                                    ->getSearchResultsUsing(function (string $search): array {
                                        return Item::query()
                                            ->tenant()
                                            ->where(function ($q) use ($search): void {
                                                $q->where('name', 'ilike', "%{$search}%")
                                                  ->orWhere('sku', 'ilike', "%{$search}%");
                                            })
                                            ->limit(15)
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value): string {
                                        return Item::query()
                                            ->tenant()
                                            ->where('id', $value)
                                            ->value('name') ?? __('(sin nombre)');
                                    })
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        if (!$state) {
                                            return;
                                        }
                                        $item = Item::find($state);
                                        if ($item && ($item->price ?? 0) > 0) {
                                            $set('unit_price', $item->price);
                                        }
                                        $qty = (float) ($get('quantity') ?? 1);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('subtotal', round($qty * $price, 2));
                                    }),
                                TextInput::make('quantity')
                                    ->label(__('Cant.'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(1)
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->rules(function (callable $get): array {
                                        $itemId = $get('item_id');
                                        if (!$itemId) {
                                            return [];
                                        }
                                        $item = \App\Models\Item::query()
                                            ->tenant()
                                            ->find($itemId);
                                        if (!$item || $item->item_type !== 'product' || $item->stock === null) {
                                            return [];
                                        }
                                        return [
                                            function (string $attribute, mixed $value, \Closure $fail) use ($item): void {
                                                if ((float) $value > (float) $item->stock) {
                                                    $fail(__('Stock insuficiente. Solo quedan :stock unidades disponibles.', ['stock' => $item->stock]));
                                                }
                                            },
                                        ];
                                    })
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $qty = (float) ($state ?? 1);
                                        $set('subtotal', round($qty * $price, 2));
                                    }),
                                TextInput::make('unit_price')
                                    ->label(__('Precio'))
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->prefix('$')
                                    ->columnSpan(2)
                                    ->extraAttributes(['class' => 'font-mono'])
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $qty = (float) ($get('quantity') ?? 1);
                                        $price = (float) ($state ?? 0);
                                        $set('subtotal', round($qty * $price, 2));
                                    }),
                                TextInput::make('subtotal')
                                    ->label(__('Subtotal'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$')
                                    ->columnSpan(2)
                                    ->extraAttributes(['class' => 'font-mono bg-gray-50 dark:bg-gray-800']),
                                TextInput::make('description')
                                    ->label(__('Nota (opcional)'))
                                    ->placeholder(__('Ej: Pantalla original, incluye flex'))
                                    ->columnSpan(1)
                                    ->extraAttributes(['class' => 'text-sm text-gray-500']),
                            ])
                            ->columns(5)
                            ->addActionLabel(__('Agregar insumo'))
                            ->defaultItems(0)
                            ->collapsible()
                            ->collapsed(false),
                    ]),
                Section::make(__('Inspección de Ingreso'))
                    ->columnSpan(1)
                    ->columns(1)
                    ->schema([
                        TextInput::make('metadata.kilometraje')
                            ->label(__('Kilometraje / Horas de uso'))
                            ->placeholder(__('Ej: 45000 km / 1200 h')),
                        TextInput::make('metadata.nivel_bateria')
                            ->label(__('Nivel de fluido / batería'))
                            ->placeholder(__('Ej: 12.4V / OK')),
                        Textarea::make('metadata.notas_esteticas')
                            ->label(__('Notas de estado estético'))
                            ->placeholder(__('Rayones, abolladuras, estado de llantas...'))
                            ->rows(3),
                    ]),
                Section::make(__('Resumen financiero'))
                    ->columnSpan(2)
                    ->schema([
                        Text::make(function (callable $get): string {
                            $items = $get('items') ?? [];
                            $total = 0;
                            foreach ($items as $item) {
                                $total += (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
                            }
                            return __('Total:') . ' $ ' . number_format($total, 2, ',', '.');
                        })
                            ->extraAttributes(['class' => 'font-mono text-lg font-bold']),
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
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('asset.name')
                    ->label(__('Activo'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'     => 'gray',
                        'in_progress' => 'warning',
                        'completed'   => 'success',
                        'cancelled'   => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'     => __('Pendiente'),
                        'in_progress' => __('En progreso'),
                        'completed'   => __('Completada'),
                        'cancelled'   => __('Cancelada'),
                    }),
                TextColumn::make('priority')
                    ->label(__('Prioridad'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low'    => 'info',
                        'medium' => 'warning',
                        'high'   => 'danger',
                    }),
                TextColumn::make('started_at')
                    ->label(__('Programada'))
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Creada'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options([
                        'pending'     => __('Pendiente'),
                        'in_progress' => __('En progreso'),
                        'completed'   => __('Completada'),
                        'cancelled'   => __('Cancelada'),
                    ]),
                SelectFilter::make('priority')
                    ->label(__('Prioridad'))
                    ->options([
                        'low'    => __('Baja'),
                        'medium' => __('Media'),
                        'high'   => __('Alta'),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('edit_work_orders')),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('delete_work_orders')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('delete_work_orders')),
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
            'index'  => ListWorkOrders::route('/'),
            'create' => CreateWorkOrder::route('/create'),
            'edit'   => EditWorkOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }
}
