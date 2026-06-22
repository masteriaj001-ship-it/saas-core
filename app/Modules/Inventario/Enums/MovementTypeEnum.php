<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Enums;

enum MovementTypeEnum: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function sign(): int
    {
        return match ($this) {
            self::Entry, self::TransferIn => 1,
            self::Exit, self::TransferOut, self::Adjustment => -1,
        };
    }
}
