<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderMediaStageEnum: string implements HasColor, HasLabel
{
    case Before = 'before';
    case After = 'after';

    public function getLabel(): string
    {
        return match ($this) {
            self::Before => 'Antes',
            self::After => 'Después',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Before => 'info',
            self::After => 'success',
        };
    }
}
