<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Services;

use App\Models\Item;
use App\Modules\Inventario\Models\ItemCostHistory;
use Illuminate\Database\Eloquent\Model;

class CostingService
{
    public function recalculateAverageCost(
        Item $item,
        float $quantityReceived,
        float $unitCost,
        Model $source,
    ): void {
        $oldCost = (float) $item->average_cost;
        $stockBefore = (float) $item->stock - $quantityReceived;

        $totalCostBefore = $stockBefore * $oldCost;
        $totalCostNew = $totalCostBefore + ($quantityReceived * $unitCost);
        $newStock = $stockBefore + $quantityReceived;

        $newAverageCost = $newStock > 0 ? $totalCostNew / $newStock : 0;

        ItemCostHistory::create([
            'tenant_id' => $item->tenant_id,
            'item_id' => $item->id,
            'previous_cost' => $oldCost,
            'new_cost' => $newAverageCost,
            'quantity_affected' => $quantityReceived,
            'stock_before' => $stockBefore,
            'stock_after' => $newStock,
            'source_type' => get_class($source),
            'source_id' => $source->id,
        ]);

        $item->update(['average_cost' => $newAverageCost]);
    }

    public function getCurrentAverageCost(Item $item): float
    {
        return (float) $item->average_cost;
    }

    public function getHistoricalCost(Item $item, \DateTime $date): float
    {
        $history = ItemCostHistory::where('item_id', $item->id)
            ->where('tenant_id', $item->tenant_id)
            ->whereDate('created_at', '<=', $date)
            ->latest('created_at')
            ->first();

        return $history ? (float) $history->new_cost : (float) $item->average_cost;
    }
}
