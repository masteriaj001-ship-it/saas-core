<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventario\Models\PriceList;

class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['owner', 'editor', 'viewer']);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $user->hasRole(['owner', 'editor', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['owner', 'editor']);
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $user->hasRole(['owner', 'editor']);
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $user->hasRole(['owner']);
    }
}
