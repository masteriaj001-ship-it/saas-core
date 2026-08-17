<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethodEnum: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Check = 'check';
    case Credit = 'credit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => __('Efectivo'),
            self::Card => __('Tarjeta'),
            self::Transfer => __('Transferencia'),
            self::Check => __('Cheque'),
            self::Credit => __('Crédito'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Cash => 'success',
            self::Card => 'info',
            self::Transfer => 'warning',
            self::Check => 'danger',
            self::Credit => 'gray',
        };
    }
}
