<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventario\Models\StockMovement;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock_movements');
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $user->can('view_stock_movements');
    }

    public function create(User $user): bool
    {
        return $user->can('create_stock_movements');
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return $user->can('edit_stock_movements');
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return $user->can('delete_stock_movements');
    }
}
