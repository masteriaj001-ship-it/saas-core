<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Providers;

use App\Modules\Inventario\Models\PurchaseOrder;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Observers\PurchaseOrderObserver;
use App\Modules\Inventario\Observers\StockMovementObserver;
use Illuminate\Support\ServiceProvider;

final class InventarioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        StockMovement::observe(StockMovementObserver::class);
    }
}
