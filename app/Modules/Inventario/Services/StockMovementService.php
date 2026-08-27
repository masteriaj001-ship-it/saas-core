<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Services;

use App\Models\Item;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

class StockMovementService
{
    public function registerEntry(
        Item $item,
        Warehouse $warehouse,
        float $quantity,
        float $unitCost,
        Model $reference,
        ?string $notes = null,
    ): StockMovement {
        $currentStock = (float) $item->stock;
        $newStock = $currentStock + $quantity;

        $movement = StockMovement::create([
            'tenant_id' => $item->tenant_id,
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => auth()->id(),
            'movement_type' => 'entry',
            'quantity' => (int) $quantity,
            'stock_before' => (int) $currentStock,
            'stock_after' => (int) $newStock,
            'unit_cost' => $unitCost,
            'reference_type' => get_class($reference),
            'reference_id' => $reference->id,
            'reason' => $notes ?? 'Entrada de inventario',
        ]);

        $item->increment('stock', (int) $quantity);

        return $movement;
    }

    public function registerExit(
        Item $item,
        Warehouse $warehouse,
        float $quantity,
        float $unitCost,
        Model $reference,
        ?string $notes = null,
    ): StockMovement {
        $currentStock = (float) $item->stock;

        if ($currentStock < $quantity) {
            throw new \InvalidArgumentException(
                "Stock insuficiente para {$item->name}. Disponible: {$currentStock}, Requerido: {$quantity}"
            );
        }

        $newStock = $currentStock - $quantity;

        $movement = StockMovement::create([
            'tenant_id' => $item->tenant_id,
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => auth()->id(),
            'movement_type' => 'exit',
            'quantity' => -(int) $quantity,
            'stock_before' => (int) $currentStock,
            'stock_after' => (int) $newStock,
            'unit_cost' => $unitCost,
            'reference_type' => get_class($reference),
            'reference_id' => $reference->id,
            'reason' => $notes ?? 'Salida de inventario',
        ]);

        $item->decrement('stock', (int) $quantity);

        return $movement;
    }

    public function getCurrentStock(Item $item, ?Warehouse $warehouse = null): float
    {
        $query = StockMovement::where('item_id', $item->id);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        return (float) $query->sum('quantity');
    }
}
