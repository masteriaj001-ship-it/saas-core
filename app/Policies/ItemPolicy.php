<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_items');
    }

    public function view(User $user, Item $item): bool
    {
        return $user->can('view_items');
    }

    public function create(User $user): bool
    {
        return $user->can('create_items');
    }

    public function update(User $user, Item $item): bool
    {
        return $user->can('edit_items');
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->can('delete_items');
    }
}
