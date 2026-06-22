<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Actions;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Exceptions\InsufficientStockException;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        return DB::transaction(function () use ($item, $warehouse, $movementType, $signedQuantity, $quantity, $reason, $reference, $unitCost, $user, $notes) {
            $tenantId = $this->tenantManager->getCurrentTenantId()
                ?? throw new \RuntimeException('Tenant context not set.');

            Item::where('id', $item->id)->lockForUpdate();

            $stockBefore = (int) StockMovement::where('item_id', $item->id)
                ->where('warehouse_id', $warehouse->id)
                ->sum('quantity');

            $stockAfter = $stockBefore + $signedQuantity;

            if ($stockAfter < 0 && $movementType->isExit()) {
                $tenant = Tenant::find($tenantId);
                $allowNegative = $tenant?->settings['inventory']['allow_negative_stock'] ?? false;

                if (! $allowNegative) {
                    throw new InsufficientStockException(
                        item: $item,
                        requested: $quantity,
                        available: $stockBefore,
                    );
                }

                Log::warning('Stock negativo permitido por config', [
                    'tenant_id' => $tenantId,
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'movement_type' => $movementType->value,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'reason' => $reason,
                ]);
            }

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
