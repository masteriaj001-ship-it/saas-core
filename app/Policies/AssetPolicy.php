<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_assets');
    }

    public function view(User $user, Asset $asset): bool
    {
        if ($user->tenant_id !== $asset->tenant_id && ! $user->is_superadmin) {
            return false;
        }

        return $user->can('view_assets');
    }

    public function create(User $user): bool
    {
        return $user->can('create_assets');
    }

    public function update(User $user, Asset $asset): bool
    {
        if ($user->tenant_id !== $asset->tenant_id && ! $user->is_superadmin) {
            return false;
        }

        return $user->can('edit_assets');
    }

    public function delete(User $user, Asset $asset): bool
    {
        if ($user->tenant_id !== $asset->tenant_id && ! $user->is_superadmin) {
            return false;
        }

        return $user->can('delete_assets');
    }
}
