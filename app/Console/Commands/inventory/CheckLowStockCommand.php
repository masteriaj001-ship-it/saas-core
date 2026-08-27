<?php

declare(strict_types=1);

namespace App\Console\Commands\inventory;

use App\Models\Item;
use App\Models\User;
use App\Modules\Inventario\Notifications\LowStockNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckLowStockCommand extends Command
{
    protected $signature = 'inventory:check-low-stock {--tenant= : Filter by specific tenant ID}';

    protected $description = 'Check for items with stock below minimum and send notifications';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $query = Item::whereColumn('stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $lowStockItems = $query->get();

        if ($lowStockItems->isEmpty()) {
            $this->info('No items with low stock found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$lowStockItems->count()} items with low stock:");

        $rows = [];

        foreach ($lowStockItems as $item) {
            $rows[] = [
                $item->name,
                $item->sku,
                $item->stock,
                $item->min_stock,
                $item->tenant_id,
            ];

            $this->notifyTenant($item);
        }

        $this->table(['Item', 'SKU', 'Stock', 'Mínimo', 'Tenant'], $rows);

        return self::SUCCESS;
    }

    private function notifyTenant(Item $item): void
    {
        $users = User::role(['owner', 'editor'])
            ->where('tenant_id', $item->tenant_id)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new LowStockNotification(
            item: $item,
        ));
    }
}
