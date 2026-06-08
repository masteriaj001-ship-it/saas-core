<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContactPolicy
{
    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function viewAny(User $user): bool
    {
        return true;
    }
}
