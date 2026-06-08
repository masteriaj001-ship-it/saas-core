<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderItemTypeEnum: string implements HasColor, HasLabel
{
    case Part = 'part';
    case Service = 'service';
    case Labor = 'labor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Part => 'Repuesto',
            self::Service => 'Servicio',
            self::Labor => 'Mano de Obra',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Part => 'warning',
            self::Service => 'success',
            self::Labor => 'info',
        };
    }
}
