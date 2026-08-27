<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Observers;

use App\Modules\Inventario\Enums\PurchaseStatus;
use App\Modules\Inventario\Models\PurchaseOrder;

class PurchaseOrderObserver
{
    public function updating(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->isDirty('status') && $purchaseOrder->status === PurchaseStatus::ORDERED) {
            $purchaseOrder->ordered_at = now();
        }
    }
}
