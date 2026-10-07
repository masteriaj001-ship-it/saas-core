<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;

class ClientVehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_client_vehicles');
    }

    public function view(User $user, ClientVehicle $vehicle): bool
    {
        return $user->can('view_client_vehicles');
    }

    public function create(User $user): bool
    {
        return $user->can('create_client_vehicles');
    }

    public function update(User $user, ClientVehicle $vehicle): bool
    {
        return $user->can('edit_client_vehicles');
    }

    public function delete(User $user, ClientVehicle $vehicle): bool
    {
        return $user->can('delete_client_vehicles');
    }
}
