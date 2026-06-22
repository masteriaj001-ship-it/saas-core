<?php

declare(strict_types=1);

namespace App\Console\Commands\inventory;

use App\Models\Item;
use App\Modules\Inventario\Actions\AdjustItemStockAction;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class MigrateLegacyStock extends Command
{
    protected $signature = 'inventory:migrate-legacy-stock';

    protected $description = 'Migrate current Item.stock values to StockMovement entries';

    public function handle(AdjustItemStockAction $adjustStockAction, TenantManager $tenantManager): int
    {
        $items = Item::withoutTenantScope()->where('stock', '>', 0)->get();

        if ($items->isEmpty()) {
            $this->info('No items with stock to migrate.');

            return self::SUCCESS;
        }

        $this->info("Migrating stock for {$items->count()} items...");

        $migrated = 0;

        foreach ($items as $item) {
            DB::transaction(function () use ($item, $adjustStockAction, $tenantManager, &$migrated) {
                $tenantManager->setTenantContext($item->tenant_id);

                $warehouse = Warehouse::withoutTenantScope()
                    ->where('tenant_id', $item->tenant_id)
                    ->where('is_default', true)
                    ->first();

                if (! $warehouse) {
                    $warehouse = Warehouse::withoutTenantScope()
                        ->create([
                            'tenant_id' => $item->tenant_id,
                            'code' => 'MAIN',
                            'name' => 'Bodega Principal',
                            'is_default' => true,
                            'is_active' => true,
                            'metadata' => '{}',
                        ]);
                }

                $adjustStockAction->execute(
                    item: $item,
                    warehouse: $warehouse,
                    movementType: MovementTypeEnum::Entry,
                    quantity: $item->stock,
                    reason: 'Saldo inicial',
                    unitCost: (float) ($item->cost ?? 0),
                );

                $migrated++;
            });
        }

        $this->info("Migrated {$migrated} items successfully.");

        return self::SUCCESS;
    }
}
