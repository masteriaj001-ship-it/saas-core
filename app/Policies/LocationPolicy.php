<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_locations');
    }

    public function view(User $user, Location $location): bool
    {
        return $user->can('view_locations');
    }

    public function create(User $user): bool
    {
        return $user->can('create_locations');
    }

    public function update(User $user, Location $location): bool
    {
        return $user->can('edit_locations');
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->can('delete_locations');
    }
}
