<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Actions;

use App\Models\Item;
use App\Models\User;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class AdjustItemStockAction
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function execute(
        Item $item,
        Warehouse $warehouse,
        MovementTypeEnum $movementType,
        int $quantity,
        ?string $reason = null,
        ?Model $reference = null,
        ?float $unitCost = null,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }

        $signedQuantity = $quantity * $movementType->sign();

        return DB::transaction(function () use ($item, $warehouse, $movementType, $signedQuantity, $reason, $reference, $unitCost, $user, $notes) {
            $tenantId = $this->tenantManager->getCurrentTenantId()
                ?? throw new \RuntimeException('Tenant context not set.');

            $stockBefore = (int) StockMovement::where('item_id', $item->id)
                ->where('warehouse_id', $warehouse->id)
                ->sum('quantity');

            $stockAfter = $stockBefore + $signedQuantity;

            $movement = StockMovement::create([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'user_id' => $user?->id,
                'movement_type' => $movementType->value,
                'quantity' => $signedQuantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => $unitCost,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'reason' => $reason ?? $movementType->value,
                'notes' => $notes,
            ]);

            $item->update(['stock' => $stockAfter]);

            return $movement;
        });
    }
}
