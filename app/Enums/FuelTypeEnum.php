<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FuelTypeEnum: string implements HasColor, HasLabel
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case Electric = 'electric';
    case Gas = 'gas';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gasoline => __('Gasolina'),
            self::Diesel => __('Diésel'),
            self::Hybrid => __('Híbrido'),
            self::Electric => __('Eléctrico'),
            self::Gas => __('Gas'),
            self::Other => __('Otro'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Gasoline => 'warning',
            self::Diesel => 'danger',
            self::Hybrid => 'success',
            self::Electric => 'info',
            self::Gas => 'gray',
            self::Other => 'gray',
        };
    }
}
