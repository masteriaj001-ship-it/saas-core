<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Modules\Plataforma\Services\SubscriptionService;

final class ChangePlanAction
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function execute(
        Tenant $tenant,
        Plan $newPlan,
        User $changedBy,
        ?string $reason = null,
        ?\DateTimeInterface $expiresAt = null,
    ): Subscription {
        return $this->subscriptionService->changePlan(
            tenant: $tenant,
            newPlan: $newPlan,
            changedBy: $changedBy,
            reason: $reason,
            expiresAt: $expiresAt,
        );
    }
}
