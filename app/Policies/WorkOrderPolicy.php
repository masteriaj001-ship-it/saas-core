<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_work_orders');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        if ($user->tenant_id !== $workOrder->tenant_id && ! $user->is_superadmin) {
            return false;
        }

        return $user->can('view_work_orders');
    }

    public function create(User $user): bool
    {
        return $user->can('create_work_orders');
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        if ($user->tenant_id !== $workOrder->tenant_id && ! $user->is_superadmin) {
            return false;
        }

        return $user->can('edit_work_orders');
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        if ($user->tenant_id !== $workOrder->tenant_id && ! $user->is_superadmin) {
            return false;
        }

        return $user->can('delete_work_orders');
    }
}
