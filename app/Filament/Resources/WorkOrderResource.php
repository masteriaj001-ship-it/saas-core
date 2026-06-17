<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\WorkOrderStatusEnum;
use App\Filament\Resources\WorkOrderResource\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrderResource\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Filament\Resources\WorkOrderResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\WorkOrderResource\RelationManagers\InspectionsRelationManager;
use App\Filament\Resources\WorkOrderResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\WorkOrderResource\RelationManagers\MediaRelationManager;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Location;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\ServiceCatalog;
use App\Modules\Talleres\Models\WorkOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

    public static function step1Schema(): array
    {
        return [
            Section::make(__('Asociación'))
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    Select::make('contact_id')
                        ->label('Cliente')
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label(__('Nombre'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label(__('Teléfono')),
                        ])
                        ->createOptionUsing(function (array $data): ?string {
                            return Contact::query()->tenant()->create($data)?->id;
                        })
                        ->getSearchResultsUsing(function (string $search): array {
                            return Contact::query()
                                ->tenant()
                                ->where(function ($q) use ($search): void {
                                    $q->where('name', 'ilike', "%{$search}%")
                                        ->orWhere('document_number', 'ilike', "%{$search}%")
                                        ->orWhere('phone', 'ilike', "%{$search}%");
                                })
                                ->limit(15)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value): string {
                            return Contact::query()
                                ->tenant()
                                ->where('id', $value)
                                ->value('name') ?? __('(sin nombre)');
                        }),
                    Select::make('asset_id')
                        ->label('Vehículo')
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label(__('Nombre'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('plate')
                                ->label(__('Placa')),
                            TextInput::make('brand')
                                ->label(__('Marca')),
                            TextInput::make('model')
                                ->label(__('Modelo')),
                        ])
                        ->createOptionUsing(function (array $data): ?string {
                            return Asset::query()->tenant()->create($data)?->id;
                        })
                        ->getSearchResultsUsing(function (string $search): array {
                            return Asset::query()
                                ->tenant()
                                ->where(function ($q) use ($search): void {
                                    $q->where('name', 'ilike', "%{$search}%")
                                        ->orWhere('plate', 'ilike', "%{$search}%")
                                        ->orWhere('brand', 'ilike', "%{$search}%")
                                        ->orWhere('model', 'ilike', "%{$search}%");
                                })
                                ->limit(15)
                                ->get()
                                ->mapWithKeys(fn (Asset $asset) => [
                                    $asset->id => static::formatAssetLabel($asset),
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value): string {
                            $asset = Asset::query()
                                ->tenant()
                                ->where('id', $value)
                                ->first();

                            if (! $asset) {
                                return __('(sin nombre)');
                            }

                            return static::formatAssetLabel($asset);
                        }),
                    Select::make('location_id')
                        ->label('Ubicación')
                        ->searchable()
                        ->relationship('location', 'name')
                        ->getOptionLabelUsing(function ($value): string {
                            return Location::query()
                                ->tenant()
                                ->where('id', $value)
                                ->value('name') ?? __('(sin nombre)');
                        }),
                ]),
            Section::make(__('Asignación'))
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    Select::make('mechanic_id')
                        ->label(__('Mecánico'))
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            return Contact::query()
                                ->tenant()
                                ->where('contact_type', 'employee')
                                ->whereHas('roles', fn ($q) => $q->where('role_code', 'mechanic'))
                                ->where('name', 'ilike', "%{$search}%")
                                ->limit(15)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value): string {
                            return Contact::query()
                                ->tenant()
                                ->where('id', $value)
                                ->value('name') ?? __('(sin nombre)');
                        }),
                    Select::make('advisor_id')
                        ->label(__('Asesor'))
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            return Contact::query()
                                ->tenant()
                                ->where('contact_type', 'employee')
                                ->whereHas('roles', fn ($q) => $q->where('role_code', 'service_advisor'))
                                ->where('name', 'ilike', "%{$search}%")
                                ->limit(15)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value): string {
                            return Contact::query()
                                ->tenant()
                                ->where('id', $value)
                                ->value('name') ?? __('(sin nombre)');
                        }),
                ]),
            Section::make(__('Control'))
                ->columnSpan(1)
                ->schema([
                    Select::make('status')
                        ->label(__('Estado'))
                        ->required()
                        ->default('received')
                        ->options(WorkOrderStatusEnum::class),
                    Select::make('priority')
                        ->label(__('Prioridad'))
                        ->required()
                        ->default('medium')
                        ->options([
                            'low' => __('Baja'),
                            'medium' => __('Media'),
                            'normal' => __('Normal'),
                            'high' => __('Alta'),
                            'urgent' => __('Urgente'),
                        ]),
                ]),
            Section::make(__('Recepción'))
                ->columnSpan(1)
                ->schema([
                    Textarea::make('reception_notes')
                        ->label(__('Notas de recepción'))
                        ->rows(3),
                    Select::make('fuel_level')
                        ->label(__('Nivel de combustible'))
                        ->options([
                            'E' => __('Vacío'),
                            '1/4' => '1/4',
                            '1/2' => '1/2',
                            '3/4' => '3/4',
                            'F' => __('Lleno'),
                        ])
                        ->native(false)
                        ->placeholder(__('— Seleccionar —')),
                    TextInput::make('mileage_km')
                        ->label(__('Kilometraje / Horas de uso'))
                        ->numeric()
                        ->suffix('km'),
                    TextInput::make('battery_level')
                        ->label(__('Nivel de fluido / batería'))
                        ->placeholder('Ej: 12.4V / OK'),
                    Textarea::make('aesthetic_notes')
                        ->label(__('Notas de estado estético'))
                        ->placeholder('Rayones, abolladuras...')
                        ->rows(2),
                ]),
            Section::make(__('Inspección de Ingreso'))
                ->columnSpan(1)
                ->schema([
                ]),
        ];
    }

    public static function step2Schema(): array
    {
        return [
            Section::make(__('Problema'))
                ->columnSpan(2)
                ->schema([
                    TextInput::make('title')
                        ->label(__('Título'))
                        ->required()
                        ->maxLength(255),
                    Textarea::make('service_description')
                        ->label(__('Descripción del servicio'))
                        ->rows(4),
                    Textarea::make('description')
                        ->label(__('Notas internas'))
                        ->rows(3),
                ]),
            Section::make(__('Diagnóstico y Aprobación'))
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    Textarea::make('diagnosis_summary')
                        ->label(__('Resumen de diagnóstico'))
                        ->rows(3)
                        ->columnSpanFull(),
                    Select::make('approval_channel')
                        ->label(__('Canal de aprobación'))
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'phone' => __('Teléfono'),
                            'email' => 'Email',
                        ])
                        ->native(false)
                        ->placeholder(__('— Seleccionar —')),
                    DateTimePicker::make('approval_at')
                        ->label(__('Fecha de aprobación')),
                    DatePicker::make('started_at')
                        ->label(__('Fecha programada')),
                ]),
            Section::make(__('Insumos / Repuestos'))
                ->columnSpan(2)
                ->description(__('Agregue los repuestos o servicios consumidos en esta orden.'))
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->schema([
                            Select::make('type')
                                ->label(__('Tipo'))
                                ->options([
                                    'part' => 'Repuesto',
                                    'service' => 'Servicio',
                                    'labor' => 'Mano de Obra',
                                ])
                                ->default('part')
                                ->placeholder(null)
                                ->live()
                                ->columnSpan(1)
                                ->afterStateHydrated(function (Set $set, $state): void {
                                    if (blank($state)) {
                                        $set('type', 'part');
                                    }
                                })
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $set('item_id', null);
                                    $set('service_catalog_id', null);
                                    $set('unit_price', 0);
                                    $set('subtotal', 0);
                                }),
                            Select::make('item_id')
                                ->label(__('Insumo / Repuesto'))
                                ->placeholder(__('Buscar repuesto...'))
                                ->options([])
                                ->searchable()
                                ->required(fn (Get $get): bool => ($get('type') ?? 'part') === 'part')
                                ->live()
                                ->columnSpan(3)
                                ->hidden(fn (Get $get): bool => ! in_array($get('type') ?? 'part', ['part'], true))
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
                                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                    if (! $state) {
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
                            Select::make('service_catalog_id')
                                ->label(__('Servicio / Mano de obra'))
                                ->placeholder(__('Buscar en catálogo...'))
                                ->options([])
                                ->searchable()
                                ->required(fn (Get $get): bool => in_array($get('type') ?? '', ['service', 'labor'], true))
                                ->live()
                                ->columnSpan(3)
                                ->hidden(fn (Get $get): bool => ! in_array($get('type') ?? 'part', ['service', 'labor'], true))
                                ->getSearchResultsUsing(function (string $search): array {
                                    return ServiceCatalog::query()
                                        ->tenant()
                                        ->where('is_active', true)
                                        ->where('name', 'ilike', "%{$search}%")
                                        ->limit(15)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(function ($value): string {
                                    return ServiceCatalog::query()
                                        ->tenant()
                                        ->where('id', $value)
                                        ->value('name') ?? __('(sin nombre)');
                                })
                                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                    if (! $state) {
                                        return;
                                    }
                                    $service = ServiceCatalog::find($state);
                                    if ($service && ($service->base_price ?? 0) > 0) {
                                        $set('unit_price', $service->base_price);
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
                                    $type = $get('type');
                                    if ($type !== 'part') {
                                        return [];
                                    }
                                    $itemId = $get('item_id');
                                    if (! $itemId) {
                                        return [];
                                    }
                                    $item = Item::query()
                                        ->tenant()
                                        ->find($itemId);
                                    if (! $item || $item->item_type !== 'product' || $item->stock === null) {
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
                                ->columnSpan(1)
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
                                ->columnSpan(1)
                                ->extraAttributes(['class' => 'font-mono bg-gray-50 dark:bg-gray-800']),
                            TextInput::make('description')
                                ->label(__('Nota (opcional)'))
                                ->placeholder(__('Ej: Pantalla original, incluye flex'))
                                ->columnSpan(1)
                                ->extraAttributes(['class' => 'text-sm text-gray-500']),
                        ])
                        ->columns(4)
                        ->addActionLabel(__('Agregar insumo'))
                        ->defaultItems(0)
                        ->collapsible()
                        ->collapsed(false),
                ]),
        ];
    }

    public static function step3Schema(): array
    {
        return [
            Section::make(__('Control de Calidad'))
                ->columnSpan(1)
                ->schema([
                    Toggle::make('qc_passed')
                        ->label(__('Pasó control de calidad'))
                        ->live(),
                    Textarea::make('qc_notes')
                        ->label(__('Notas de calidad'))
                        ->rows(3)
                        ->visible(fn (Get $get): bool => $get('qc_passed') === false),
                    DateTimePicker::make('delivery_at')
                        ->label(__('Fecha de entrega')),
                    DatePicker::make('completed_at')
                        ->label(__('Fecha de finalización')),
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

                        return __('Total:').' $ '.number_format($total, 2, ',', '.');
                    })
                        ->extraAttributes(['class' => 'font-mono text-lg font-bold']),
                ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                ...static::step1Schema(),
                ...static::step2Schema(),
                ...static::step3Schema(),
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
                TextColumn::make('service_description')
                    ->label(__('Servicio'))
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('asset.name')
                    ->label(__('Activo'))
                    ->searchable(),
                TextColumn::make('mechanic.name')
                    ->label(__('Mecánico'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (WorkOrderStatusEnum $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn (WorkOrderStatusEnum $state): string => $state->getLabel()),
                TextColumn::make('priority')
                    ->label(__('Prioridad'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'info',
                        'medium' => 'warning',
                        'normal' => 'success',
                        'high' => 'danger',
                        'urgent' => 'danger',
                        default => 'gray',
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
                        'pending' => __('Pendiente'),
                        'in_progress' => __('En progreso'),
                        'completed' => __('Completada'),
                        'cancelled' => __('Cancelada'),
                    ]),
                SelectFilter::make('priority')
                    ->label(__('Prioridad'))
                    ->options([
                        'low' => __('Baja'),
                        'medium' => __('Media'),
                        'normal' => __('Normal'),
                        'high' => __('Alta'),
                        'urgent' => __('Urgente'),
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
            ActivitiesRelationManager::class,
            InspectionsRelationManager::class,
            MediaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrders::route('/'),
            'create' => CreateWorkOrder::route('/create'),
            'edit' => EditWorkOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }

    public static function formatAssetLabel(Asset $asset): string
    {
        $parts = array_filter([
            $asset->brand,
            $asset->model,
            $asset->year ? (string) $asset->year : null,
        ]);

        $vehicleInfo = ! empty($parts)
            ? implode(' ', $parts)
            : $asset->name;

        return $asset->plate
            ? "{$vehicleInfo} — {$asset->plate}"
            : $vehicleInfo;
    }
}
