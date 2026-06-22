<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Services;

use App\Models\Item;
use App\Modules\Inventario\Models\StockMovement;

final class StockSyncService
{
    public function syncItemStock(Item $item): void
    {
        $stock = (int) StockMovement::where('item_id', $item->id)->sum('quantity');

        if ($item->stock !== $stock) {
            $item->update(['stock' => $stock]);
        }
    }

    public function recalculateItemStock(string $itemId): int
    {
        return (int) StockMovement::where('item_id', $itemId)->sum('quantity');
    }
}
