<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Modules\Plataforma\Models\SubscriptionLog;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function getActiveSubscription(Tenant $tenant): ?Subscription
    {
        return $tenant->subscription;
    }

    public function getActivePlan(Tenant $tenant): ?Plan
    {
        return $this->getActiveSubscription($tenant)?->plan;
    }

    public function isExpired(Tenant $tenant): bool
    {
        $subscription = $this->getActiveSubscription($tenant);

        return $subscription?->isExpired() ?? true;
    }

    public function isSuspended(Tenant $tenant): bool
    {
        $subscription = $this->getActiveSubscription($tenant);

        return $subscription?->isSuspended() ?? true;
    }

    public function isActive(Tenant $tenant): bool
    {
        $subscription = $this->getActiveSubscription($tenant);

        return $subscription?->isActive() ?? false;
    }

    public function changePlan(
        Tenant $tenant,
        Plan $newPlan,
        User $changedBy,
        ?string $reason = null,
        ?\DateTimeInterface $expiresAt = null,
    ): Subscription {
        return DB::transaction(function () use ($tenant, $newPlan, $changedBy, $reason, $expiresAt) {
            $oldSubscription = Subscription::where('tenant_id', $tenant->id)->first();
            $oldPlanId = $oldSubscription?->plan_id;

            if ($oldSubscription) {
                $oldSubscription->update([
                    'plan_id' => $newPlan->id,
                    'expires_at' => $expiresAt,
                    'changed_by' => $changedBy->id,
                    'status' => 'active',
                ]);
                $subscription = $oldSubscription->fresh();
            } else {
                $subscription = Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $newPlan->id,
                    'started_at' => now(),
                    'expires_at' => $expiresAt,
                    'changed_by' => $changedBy->id,
                    'status' => 'active',
                ]);
            }

            SubscriptionLog::create([
                'tenant_id' => $tenant->id,
                'plan_from' => $oldPlanId,
                'plan_to' => $newPlan->id,
                'changed_by' => $changedBy->id,
                'changed_at' => now(),
                'reason' => $reason,
            ]);

            return $subscription;
        });
    }
}
