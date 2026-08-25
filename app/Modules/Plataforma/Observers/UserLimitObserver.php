<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Observers;

use App\Models\User;
use App\Modules\Plataforma\Exceptions\PlanLimitExceededException;
use App\Modules\Plataforma\Exceptions\TenantSuspendedException;
use App\Modules\Plataforma\Models\Subscription;
use App\Services\TenantManager;

class UserLimitObserver
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function creating(User $user): void
    {
        if (! $this->tenantManager->hasContext()) {
            return;
        }

        $tenantId = $this->tenantManager->getCurrentTenantId();
        $subscription = Subscription::where('tenant_id', $tenantId)->first();

        if (! $subscription) {
            return;
        }

        if ($subscription->isSuspended()) {
            throw new TenantSuspendedException($subscription->tenant?->name ?? '');
        }

        if ($subscription->isExpired()) {
            return;
        }

        $plan = $subscription->plan;

        if ($plan->max_users === null) {
            return;
        }

        $count = User::where('tenant_id', $tenantId)->count();

        if ($count >= $plan->max_users) {
            throw new PlanLimitExceededException(
                'usuarios',
                $count,
                $plan->max_users,
            );
        }
    }
}
