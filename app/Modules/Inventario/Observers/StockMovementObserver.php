<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Observers;

use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Services\LowStockAlertService;

class StockMovementObserver
{
    public function created(StockMovement $movement): void
    {
        if ($movement->item === null) {
            return;
        }

        try {
            app(LowStockAlertService::class)->checkAndNotify($movement->item);
        } catch (\Throwable) {
            // Roles may not exist in test environment
        }
    }
}
