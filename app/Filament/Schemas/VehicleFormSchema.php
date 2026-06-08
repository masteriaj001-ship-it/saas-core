<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use App\Models\Contact;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class VehicleFormSchema
{
    public static function make(): array
    {
        return [
            Section::make(__('Información General'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('Nombre / Alias'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('plate')
                        ->label(__('Placa'))
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
                        ])
                        ->default('sedan'),
                ]),
            Section::make(__('Datos Técnicos'))
                ->columns(2)
                ->schema([
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
        ];
    }
}
