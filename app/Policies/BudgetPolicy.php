<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Budget\Models\Budget;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_budgets');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->can('view_budgets');
    }

    public function create(User $user): bool
    {
        return $user->can('create_budgets');
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->can('edit_budgets');
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->can('delete_budgets');
    }
}
