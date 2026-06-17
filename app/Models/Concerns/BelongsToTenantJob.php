<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Jobs\Middleware\SetTenantContextForJob;
use App\Services\TenantManager;

trait BelongsToTenantJob
{
    public ?string $tenantId = null;

    public function __construct()
    {
        $manager = app(TenantManager::class);

        if ($manager->hasContext()) {
            $this->tenantId = $manager->getCurrentTenantId();
        }
    }

    public function middleware(): array
    {
        return [app(SetTenantContextForJob::class)];
    }
}
