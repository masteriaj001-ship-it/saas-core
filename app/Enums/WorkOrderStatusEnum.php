<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderStatusEnum: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Received = 'received';
    case Diagnosing = 'diagnosing';
    case Quoted = 'quoted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Received => 'Recibido',
            self::Diagnosing => 'En diagnóstico',
            self::Quoted => 'Cotizado',
            self::InProgress => 'En reparación',
            self::Completed => 'Completado',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Received => 'info',
            self::Diagnosing => 'warning',
            self::Quoted => 'primary',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }
}
