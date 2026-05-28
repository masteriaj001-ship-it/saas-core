<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_transactions');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->can('view_transactions');
    }

    public function create(User $user): bool
    {
        return $user->can('create_transactions');
    }

    public function update(User $user, Transaction $transaction): bool
    {
        if (!$transaction->canEdit()) {
            return false;
        }

        return $user->can('edit_transactions');
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        if (!$transaction->canEdit()) {
            return false;
        }

        return $user->can('delete_transactions');
    }
}
