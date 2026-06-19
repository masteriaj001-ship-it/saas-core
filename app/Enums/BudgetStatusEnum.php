<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BudgetStatusEnum: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Borrador'),
            self::Sent => __('Enviado'),
            self::Approved => __('Aprobado'),
            self::Rejected => __('Rechazado'),
            self::Expired => __('Vencido'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Expired => 'info',
        };
    }
}
