<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactRoleEnum: string implements HasColor, HasLabel
{
    case Mechanic = 'mechanic';
    case ServiceAdvisor = 'service_advisor';
    case WorkshopManager = 'workshop_manager';
    case Technician = 'technician';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mechanic => __('Mecánico'),
            self::ServiceAdvisor => __('Asesor de Servicio'),
            self::WorkshopManager => __('Jefe de Taller'),
            self::Technician => __('Técnico'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Mechanic => 'info',
            self::ServiceAdvisor => 'success',
            self::WorkshopManager => 'warning',
            self::Technician => 'gray',
        };
    }
}
