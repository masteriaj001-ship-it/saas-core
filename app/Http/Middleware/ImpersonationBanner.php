<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Modules\Plataforma\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ImpersonationBanner
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonationService = app(ImpersonationService::class);

        if ($impersonationService->isImpersonating()) {
            $tenantId = $impersonationService->getImpersonatingTenantId();
            $tenant = Tenant::find($tenantId);

            View::share('impersonationTenant', $tenant);
            View::share('isImpersonating', true);
        } else {
            View::share('isImpersonating', false);
        }

        return $next($request);
    }
}
