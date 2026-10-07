<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Facturacion\Models\CreditAccount;

class CreditAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_credit_accounts');
    }

    public function view(User $user, CreditAccount $account): bool
    {
        return $user->can('view_credit_accounts');
    }

    public function create(User $user): bool
    {
        return $user->can('create_credit_accounts');
    }

    public function update(User $user, CreditAccount $account): bool
    {
        return $user->can('edit_credit_accounts');
    }

    public function delete(User $user, CreditAccount $account): bool
    {
        return $user->can('delete_credit_accounts');
    }
}
