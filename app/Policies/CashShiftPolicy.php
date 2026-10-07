<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Caja\Models\CashShift;

class CashShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_cash_shifts');
    }

    public function view(User $user, CashShift $shift): bool
    {
        return $user->can('view_cash_shifts');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cash_shifts');
    }

    public function update(User $user, CashShift $shift): bool
    {
        return $user->can('edit_cash_shifts');
    }

    public function delete(User $user, CashShift $shift): bool
    {
        return $user->can('delete_cash_shifts');
    }
}
