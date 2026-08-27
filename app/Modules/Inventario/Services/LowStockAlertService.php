<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Services;

use App\Models\Item;
use App\Models\User;
use App\Modules\Inventario\Notifications\LowStockNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class LowStockAlertService
{
    public function checkAndNotify(Item $item): void
    {
        if ($this->isLowStock($item)) {
            $this->notify($item);
        }
    }

    public function isLowStock(Item $item): bool
    {
        $minStock = $item->min_stock ?? 0;

        if ($minStock <= 0) {
            return false;
        }

        return (float) $item->stock <= $minStock;
    }

    public function notify(Item $item): void
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

    public function getLowStockItems(): Collection
    {
        return Item::where('tenant_id', tenant('id'))
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->get();
    }
}
