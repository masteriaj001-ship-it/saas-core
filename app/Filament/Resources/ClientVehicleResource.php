<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClientVehicleResource\Pages\CreateClientVehicle;
use App\Filament\Resources\ClientVehicleResource\Pages\EditClientVehicle;
use App\Filament\Resources\ClientVehicleResource\Pages\ListClientVehicles;
use App\Models\Contact;
use App\Modules\Talleres\Models\ClientVehicle;
use Filament\Actions\BulkActionGroup;
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

class ClientVehicleResource extends Resource
{
    protected static ?string $model = ClientVehicle::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Talleres';

    public static function getModelLabel(): string
    {
        return __('Vehículo del Cliente');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Vehículos');
    }

    public static function getNavigationLabel(): string
    {
        return __('Vehículos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Información General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('plate')
                            ->label(__('Placa'))
                            ->required()
                            ->maxLength(20),
                        Select::make('vehicle_type')
                            ->label(__('Tipo de vehículo'))
                            ->options([
                                'sedan' => __('Sedán'),
                                'motorcycle' => __('Motocicleta'),
                                'pickup_truck' => __('Pickup'),
                                'suv' => __('SUV'),
                                'van' => __('Van / Furgón'),
                                'truck' => __('Camión'),
                                'other' => __('Otro'),
                            ]),
                        TextInput::make('brand')
                            ->label(__('Marca'))
                            ->maxLength(100),
                        TextInput::make('model')
                            ->label(__('Modelo'))
                            ->maxLength(100),
                        TextInput::make('version')
                            ->label(__('Versión'))
                            ->maxLength(100),
                        TextInput::make('year')
                            ->label(__('Año'))
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(now()->year + 1),
                    ]),
                Section::make(__('Datos Técnicos'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('vin')
                            ->label(__('VIN'))
                            ->maxLength(100),
                        TextInput::make('engine_number')
                            ->label(__('Número de Motor'))
                            ->maxLength(100),
                        TextInput::make('color')
                            ->label(__('Color'))
                            ->maxLength(50),
                        Select::make('fuel_type')
                            ->label(__('Tipo de Combustible'))
                            ->options([
                                'gasoline' => __('Gasolina'),
                                'diesel' => __('Diésel'),
                                'hybrid' => __('Híbrido'),
                                'electric' => __('Eléctrico'),
                                'gas' => __('Gas'),
                                'other' => __('Otro'),
                            ]),
                        TextInput::make('current_mileage')
                            ->label(__('Kilometraje Actual'))
                            ->numeric()
                            ->minValue(0),
                    ]),
                Section::make(__('Propietario'))
                    ->schema([
                        Select::make('owner_contact_id')
                            ->label(__('Cliente Propietario'))
                            ->searchable()
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
                    ]),
                Section::make(__('Notas'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plate')
                    ->label(__('Placa'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label(__('Marca'))
                    ->searchable(),
                TextColumn::make('model')
                    ->label(__('Modelo'))
                    ->searchable(),
                TextColumn::make('year')
                    ->label(__('Año'))
                    ->sortable(),
                TextColumn::make('vin')
                    ->label(__('VIN'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('owner.name')
                    ->label(__('Propietario'))
                    ->searchable(),
                TextColumn::make('current_mileage')
                    ->label(__('Kilometraje'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('vehicle_type')
                    ->label(__('Tipo'))
                    ->options([
                        'sedan' => __('Sedán'),
                        'motorcycle' => __('Motocicleta'),
                        'pickup_truck' => __('Pickup'),
                        'suv' => __('SUV'),
                        'van' => __('Van / Furgón'),
                        'truck' => __('Camión'),
                        'other' => __('Otro'),
                    ]),
                SelectFilter::make('fuel_type')
                    ->label(__('Combustible'))
                    ->options([
                        'gasoline' => __('Gasolina'),
                        'diesel' => __('Diésel'),
                        'hybrid' => __('Híbrido'),
                        'electric' => __('Eléctrico'),
                        'gas' => __('Gas'),
                        'other' => __('Otro'),
                    ]),
            ])
            ->actions([
                EditAction::make(),
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
            'index' => ListClientVehicles::route('/'),
            'create' => CreateClientVehicle::route('/create'),
            'edit' => EditClientVehicle::route('/{record}/edit'),
        ];
    }
}
