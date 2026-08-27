<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Enums;

enum PurchaseStatus: string
{
    case DRAFT = 'draft';
    case ORDERED = 'ordered';
    case PARTIAL = 'partial';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::ORDERED => 'Ordenado',
            self::PARTIAL => 'Parcial',
            self::RECEIVED => 'Recibido',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ORDERED => 'warning',
            self::PARTIAL => 'info',
            self::RECEIVED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
