<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Talleres\Models\Appointment;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['owner', 'editor', 'viewer', 'mechanic']);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->hasRole(['owner', 'editor', 'viewer', 'mechanic']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['owner', 'editor']);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->hasRole(['owner', 'editor']);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->hasRole(['owner']);
    }
}
