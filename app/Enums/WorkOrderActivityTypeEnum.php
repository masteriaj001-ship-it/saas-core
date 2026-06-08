<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WorkOrderActivityTypeEnum: string implements HasLabel
{
    case StatusChange = 'status_change';
    case Note = 'note';
    case Assignment = 'assignment';
    case Qc = 'qc';

    public function getLabel(): string
    {
        return match ($this) {
            self::StatusChange => 'Cambio de Estado',
            self::Note => 'Nota',
            self::Assignment => 'Asignación',
            self::Qc => 'Control de Calidad',
        };
    }
}
