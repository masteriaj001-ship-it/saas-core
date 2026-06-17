<?php

declare(strict_types=1);

namespace Tests\Doubles\Jobs;

use App\Models\Concerns\BelongsToTenantJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class WithTenantContextJob implements ShouldQueue
{
    use BelongsToTenantJob, Dispatchable, InteractsWithQueue, SerializesModels;

    public function handle(): void {}
}
