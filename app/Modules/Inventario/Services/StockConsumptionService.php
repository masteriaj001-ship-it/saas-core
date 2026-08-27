<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Services;

use App\Modules\Inventario\Models\Warehouse;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use Illuminate\Support\Facades\DB;

class StockConsumptionService
{
    public function __construct(
        private StockMovementService $stockService,
    ) {}

    public function consumeForWorkOrder(WorkOrder $workOrder): void
    {
        $items = $workOrder->items()
            ->whereNotNull('item_id')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $warehouse = Warehouse::where('tenant_id', $workOrder->tenant_id)
            ->where('is_default', true)
            ->first();

        if ($warehouse === null) {
            return;
        }

        DB::transaction(function () use ($workOrder, $items, $warehouse) {
            foreach ($items as $woItem) {
                if ($woItem->quantity <= 0) {
                    continue;
                }

                $movement = $this->stockService->registerExit(
                    item: $woItem->item,
                    warehouse: $warehouse,
                    quantity: (float) $woItem->quantity,
                    unitCost: (float) $woItem->item->average_cost,
                    reference: $workOrder,
                    notes: "Consumo en OT #{$workOrder->code}",
                );

                $woItem->update([
                    'stock_movement_id' => $movement->id,
                    'unit_cost_at_sale' => $woItem->item->average_cost,
                ]);
            }
        });
    }

    public function checkAvailability(WorkOrderItem $workOrderItem): bool
    {
        if ($workOrderItem->item_id === null) {
            return true;
        }

        $item = $workOrderItem->item;

        return $item->stock >= $workOrderItem->quantity;
    }
}
