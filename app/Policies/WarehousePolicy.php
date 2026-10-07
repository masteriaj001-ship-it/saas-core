<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventario\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_warehouses');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('view_warehouses');
    }

    public function create(User $user): bool
    {
        return $user->can('create_warehouses');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('edit_warehouses');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('delete_warehouses');
    }
}
