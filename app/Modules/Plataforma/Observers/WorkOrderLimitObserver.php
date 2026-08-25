<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Observers;

use App\Modules\Plataforma\Exceptions\PlanLimitExceededException;
use App\Modules\Plataforma\Exceptions\TenantSuspendedException;
use App\Modules\Plataforma\Models\Subscription;
use App\Services\TenantManager;

class WorkOrderLimitObserver
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function creating(object $model): void
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

        if ($plan->max_work_orders === null) {
            return;
        }

        $currentMonth = now()->startOfMonth();
        $count = $model->newQueryWithoutScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $currentMonth)
            ->count();

        if ($count >= $plan->max_work_orders) {
            throw new PlanLimitExceededException(
                'ordenes de trabajo',
                $count,
                $plan->max_work_orders,
            );
        }
    }
}
