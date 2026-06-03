<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VehicleTypeEnum: string implements HasColor, HasLabel
{
    case Sedan = 'sedan';
    case Motorcycle = 'motorcycle';
    case PickupTruck = 'pickup_truck';
    case Suv = 'suv';
    case Van = 'van';
    case Truck = 'truck';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sedan => 'Sedán',
            self::Motorcycle => 'Motocicleta',
            self::PickupTruck => 'Pickup',
            self::Suv => 'SUV',
            self::Van => 'Van / Furgón',
            self::Truck => 'Camión',
            self::Other => 'Otro',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Motorcycle => 'warning',
            self::Truck => 'danger',
            default => 'gray',
        };
    }
}
