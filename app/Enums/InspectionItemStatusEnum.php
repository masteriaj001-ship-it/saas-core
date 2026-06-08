<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InspectionItemStatusEnum: string implements HasColor, HasLabel
{
    case Ok = 'ok';
    case Damaged = 'damaged';
    case Missing = 'missing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ok => 'Ok',
            self::Damaged => 'Dañado',
            self::Missing => 'Faltante',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Ok => 'success',
            self::Damaged => 'danger',
            self::Missing => 'warning',
        };
    }
}
