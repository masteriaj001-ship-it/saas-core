<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Enums;

enum AppointmentStatus: string
{
    case SCHEDULED = 'scheduled';
    case CONFIRMED = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Agendada',
            self::CONFIRMED => 'Confirmada',
            self::IN_PROGRESS => 'En progreso',
            self::COMPLETED => 'Completada',
            self::CANCELLED => 'Cancelada',
            self::NO_SHOW => 'No asistió',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED => 'gray',
            self::CONFIRMED => 'info',
            self::IN_PROGRESS => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::NO_SHOW => 'danger',
        };
    }
}
