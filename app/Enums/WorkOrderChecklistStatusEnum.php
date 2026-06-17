<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderChecklistStatusEnum: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Done = 'done';
    case Ok = 'ok';
    case Nok = 'nok';
    case Na = 'na';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Done => 'Completado',
            self::Ok => 'Ok',
            self::Nok => 'Nok',
            self::Na => 'N/A',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Done => 'success',
            self::Ok => 'success',
            self::Nok => 'danger',
            self::Na => 'warning',
        };
    }
}
