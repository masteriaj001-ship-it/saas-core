<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\ImpersonationLog;
use App\Modules\Plataforma\Services\ImpersonationService;

final class StartImpersonationAction
{
    public function __construct(
        private readonly ImpersonationService $impersonationService,
    ) {}

    public function execute(User $superadmin, Tenant $tenant): ImpersonationLog
    {
        return $this->impersonationService->start($superadmin, $tenant);
    }
}
