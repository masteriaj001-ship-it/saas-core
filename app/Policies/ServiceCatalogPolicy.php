<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Talleres\Models\ServiceCatalog;

class ServiceCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_service_catalogs');
    }

    public function view(User $user, ServiceCatalog $catalog): bool
    {
        return $user->can('view_service_catalogs');
    }

    public function create(User $user): bool
    {
        return $user->can('create_service_catalogs');
    }

    public function update(User $user, ServiceCatalog $catalog): bool
    {
        return $user->can('edit_service_catalogs');
    }

    public function delete(User $user, ServiceCatalog $catalog): bool
    {
        return $user->can('delete_service_catalogs');
    }
}
