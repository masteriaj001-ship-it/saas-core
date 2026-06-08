<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentTypeEnum: string implements HasColor, HasLabel
{
    case CC = 'CC';
    case NIT = 'NIT';
    case CE = 'CE';
    case PAS = 'PAS';
    case TI = 'TI';

    public function getLabel(): string
    {
        return match ($this) {
            self::CC => __('Cédula de Ciudadanía'),
            self::NIT => __('NIT'),
            self::CE => __('Cédula de Extranjería'),
            self::PAS => __('Pasaporte'),
            self::TI => __('Tarjeta de Identidad'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CC => 'info',
            self::NIT => 'warning',
            self::CE => 'success',
            self::PAS => 'gray',
            self::TI => 'primary',
        };
    }
}
