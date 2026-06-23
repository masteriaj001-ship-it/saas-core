<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Actions;

use App\Models\Item;
use App\Models\User;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Exceptions\InsufficientStockException;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TransferStockAction
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function execute(
        Item $item,
        Warehouse $origin,
        Warehouse $destination,
        int $quantity,
        ?string $reason = null,
        ?User $user = null,
        ?string $notes = null,
    ): array {
        if ($origin->id === $destination->id) {
            throw new \InvalidArgumentException('Origin and destination warehouses must be different.');
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }

        $transferGroupId = (string) Str::uuid();

        $movements = DB::transaction(function () use ($item, $origin, $destination, $quantity, $reason, $user, $notes, $transferGroupId) {
            $tenantId = $this->tenantManager->getCurrentTenantId()
                ?? throw new \RuntimeException('Tenant context not set.');

            Item::where('id', $item->id)->lockForUpdate();

            $originStockBefore = (int) StockMovement::where('item_id', $item->id)
                ->where('warehouse_id', $origin->id)
                ->sum('quantity');

            if ($originStockBefore < $quantity) {
                throw new InsufficientStockException(
                    item: $item,
                    requested: $quantity,
                    available: $originStockBefore,
                );
            }

            $destinationStockBefore = (int) StockMovement::where('item_id', $item->id)
                ->where('warehouse_id', $destination->id)
                ->sum('quantity');

            $transferOut = StockMovement::create([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'warehouse_id' => $origin->id,
                'user_id' => $user?->id,
                'movement_type' => MovementTypeEnum::TransferOut->value,
                'quantity' => -$quantity,
                'stock_before' => $originStockBefore,
                'stock_after' => $originStockBefore - $quantity,
                'reason' => $reason ?? "Transferencia a {$destination->name}",
                'notes' => $notes,
                'transfer_group_id' => $transferGroupId,
            ]);

            $transferIn = StockMovement::create([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'warehouse_id' => $destination->id,
                'user_id' => $user?->id,
                'movement_type' => MovementTypeEnum::TransferIn->value,
                'quantity' => $quantity,
                'stock_before' => $destinationStockBefore,
                'stock_after' => $destinationStockBefore + $quantity,
                'reason' => $reason ?? "Transferencia desde {$origin->name}",
                'notes' => $notes,
                'transfer_group_id' => $transferGroupId,
            ]);

            $totalStock = $originStockBefore + $destinationStockBefore;

            $item->update(['stock' => $totalStock]);

            return ['out' => $transferOut, 'in' => $transferIn];
        });

        return $movements;
    }
}
