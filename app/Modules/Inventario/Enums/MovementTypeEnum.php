<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Enums;

enum MovementTypeEnum: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function sign(): int
    {
        return match ($this) {
            self::Entry, self::AdjustmentIn, self::TransferIn => 1,
            self::Exit, self::AdjustmentOut, self::TransferOut => -1,
        };
    }

    public function isExit(): bool
    {
        return $this->sign() === -1;
    }

    public function isAdjustment(): bool
    {
        return in_array($this, [self::AdjustmentIn, self::AdjustmentOut], true);
    }
}
