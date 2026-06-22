<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Exceptions;

use App\Models\Item;

final class InsufficientStockException extends \RuntimeException
{
    public function __construct(
        public readonly Item $item,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Stock insuficiente para '{$item->name}': solicitado {$requested}, disponible {$available}."
        );
    }
}
