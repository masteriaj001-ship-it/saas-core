<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Services\TenantManager;
use Closure;

final class SetTenantContextForJob
{
    public function __construct(
        private readonly TenantManager $tenantManager
    ) {}

    public function handle(object $job, Closure $next): void
    {
        if (! empty($job->tenantId)) {
            $this->tenantManager->setTenantContext($job->tenantId);
        }

        try {
            $next($job);
        } finally {
            $this->tenantManager->clearTenantContext();
        }
    }
}
