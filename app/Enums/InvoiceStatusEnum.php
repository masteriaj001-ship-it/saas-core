<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatusEnum: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Borrador'),
            self::Issued => __('Emitida'),
            self::Paid => __('Pagada'),
            self::Cancelled => __('Anulada'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'info',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }
}
