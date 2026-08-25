<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Console\Commands;

use App\Modules\Plataforma\Models\Subscription;
use Illuminate\Console\Command;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-expired';

    protected $description = 'Check and update expired subscriptions';

    public function handle(): int
    {
        $expiredCount = Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Updated {$expiredCount} expired subscriptions.");

        return Command::SUCCESS;
    }
}
