<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Services;

use App\Modules\Inventario\Enums\PurchaseStatus;
use App\Modules\Inventario\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private StockMovementService $stockService,
        private CostingService $costingService,
    ) {}

    public function receive(PurchaseOrder $po, array $receipts): void
    {
        DB::transaction(function () use ($po, $receipts) {
            foreach ($receipts as $receipt) {
                $poItem = $po->items()
                    ->where('item_id', $receipt['item_id'])
                    ->firstOrFail();

                $qtyToReceive = min(
                    $receipt['quantity'],
                    $poItem->pendingQuantity()
                );

                if ($qtyToReceive <= 0) {
                    continue;
                }

                $poItem->increment('received_quantity', $qtyToReceive);

                $this->stockService->registerEntry(
                    item: $poItem->item,
                    warehouse: $po->warehouse,
                    quantity: $qtyToReceive,
                    unitCost: (float) $poItem->unit_cost,
                    reference: $po,
                    notes: "Recepción OC #{$po->code}",
                );

                $this->costingService->recalculateAverageCost(
                    item: $poItem->item,
                    quantityReceived: $qtyToReceive,
                    unitCost: (float) $poItem->unit_cost,
                    source: $po,
                );
            }

            if ($po->isFullyReceived()) {
                $po->update(['status' => PurchaseStatus::RECEIVED, 'received_at' => now()]);
            } else {
                $po->update(['status' => PurchaseStatus::PARTIAL]);
            }
        });
    }

    public function cancel(PurchaseOrder $po): void
    {
        if (! $po->canBeCancelled()) {
            throw new \InvalidArgumentException('No se puede cancelar esta orden de compra.');
        }

        $po->update(['status' => PurchaseStatus::CANCELLED]);
    }

    public function calculateTotals(PurchaseOrder $po): void
    {
        $items = $po->items;

        $subtotal = $items->sum('subtotal');
        $taxTotal = $items->sum('tax_amount');

        $po->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
        ]);
    }
}
