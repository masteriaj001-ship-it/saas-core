<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class TenantManager
{
    private ?string $currentTenantId = null;

    public function setTenantContext(string $tenantId): void
    {
        if (! Str::isUuid($tenantId)) {
            throw new RuntimeException("Invalid tenant UUID format: {$tenantId}");
        }

        DB::statement(
            "SELECT set_config('app.current_tenant_id', ?, false)",
            [$tenantId]
        );

        $this->currentTenantId = $tenantId;
    }

    public function clearTenantContext(): void
    {
        DB::statement(
            "SELECT set_config('app.current_tenant_id', '', false)"
        );
        $this->currentTenantId = null;
    }

    public function getCurrentTenantId(): ?string
    {
        return $this->currentTenantId;
    }

    public function hasContext(): bool
    {
        return $this->currentTenantId !== null;
    }
}
