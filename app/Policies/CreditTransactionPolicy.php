<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Facturacion\Models\CreditTransaction;

class CreditTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_credit_transactions');
    }

    public function view(User $user, CreditTransaction $transaction): bool
    {
        return $user->can('view_credit_transactions');
    }

    public function create(User $user): bool
    {
        return $user->can('create_credit_transactions');
    }

    public function update(User $user, CreditTransaction $transaction): bool
    {
        return $user->can('edit_credit_transactions');
    }

    public function delete(User $user, CreditTransaction $transaction): bool
    {
        return $user->can('delete_credit_transactions');
    }
}
