<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Actions;

use App\Models\User;
use App\Modules\Plataforma\Services\ImpersonationService;

final class StopImpersonationAction
{
    public function __construct(
        private readonly ImpersonationService $impersonationService,
    ) {}

    public function execute(User $superadmin): void
    {
        $this->impersonationService->stop($superadmin);
    }
}
