<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Console\Commands;

use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Modules\Plataforma\Models\SubscriptionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-expired';

    protected $description = 'Downgrade expired subscriptions to the free plan';

    public function handle(): int
    {
        $freePlan = Plan::where('name', 'free')->first();

        if (! $freePlan) {
            $this->error('Free plan not found. Aborting.');

            return Command::FAILURE;
        }

        $expiredSubscriptions = Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($expiredSubscriptions as $subscription) {
            $oldPlanId = $subscription->plan_id;

            $subscription->update([
                'plan_id' => $freePlan->id,
                'status' => 'active',
                'expires_at' => null,
            ]);

            SubscriptionLog::create([
                'tenant_id' => $subscription->tenant_id,
                'plan_from' => $oldPlanId,
                'plan_to' => $freePlan->id,
                'changed_by' => null,
                'changed_at' => now(),
                'reason' => 'expired_downgraded_to_free',
            ]);

            $count++;
        }

        $this->info("Downgraded {$count} expired ".Str::plural('subscription', $count).' to free plan.');

        return Command::SUCCESS;
    }
}
